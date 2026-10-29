<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Audit;
use App\Services\Tokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OidcController extends Controller
{
    private function error(string $error, int $status = 400)
    {
        return response()->json(['error' => $error], $status);
    }

    public function authorize(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'client_id' => ['required', 'string', 'max:100'], 'redirect_uri' => ['required', 'string', 'max:2048'],
            'response_type' => ['required', 'in:code'], 'scope' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'min:16', 'max:1024'], 'nonce' => ['sometimes', 'required', 'string', 'max:255'],
            'code_challenge' => ['sometimes', 'required', 'regex:/^[A-Za-z0-9_-]{43}$/'],
            'code_challenge_method' => ['required_with:code_challenge', 'in:S256'],
            'prompt' => ['sometimes', 'in:none,login'],
        ]);
        if ($validator->fails()) {
            return $this->error('invalid_request');
        }
        $data = $validator->validated();
        $client = Client::where('client_id', $data['client_id'])->where('active', true)->first();
        if (! $client || ! $client->redirectUris()->where('redirect_uri', $data['redirect_uri'])->exists()) {
            // Never redirect errors to an unverified URI.
            return $this->error('invalid_request');
        }
        $scopes = array_unique(explode(' ', $data['scope']));
        if (! in_array('openid', $scopes, true) || array_diff($scopes, ['openid', 'profile', 'email', 'dni'])) {
            return $this->redirectError($data, 'invalid_scope');
        }
        if ($request->hasAny(['request', 'request_uri', 'claims', 'max_age', 'id_token_hint', 'response_mode'])) {
            return $this->redirectError($data, 'invalid_request');
        }
        if (($data['prompt'] ?? '') === 'login' && $request->user()) {
            // The stored return URL omits prompt so fresh login does not loop.
            UserSession::where('id', $request->session()->get('central_session_id'))->update(['revoked_at' => now()]);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            unset($data['prompt']);
        }
        if (! $request->user()) {
            if (($data['prompt'] ?? '') === 'none') {
                return $this->redirectError($data, 'login_required');
            }
            $request->session()->put('url.intended', route('authorize').'?'.http_build_query($data));

            return redirect()->route('login');
        }
        if ($request->user()->must_change_password) {
            if (($data['prompt'] ?? '') === 'none') {
                return $this->redirectError($data, 'interaction_required');
            }
            $request->session()->put('url.intended', route('authorize').'?'.http_build_query($data));

            return redirect()->route('password.edit');
        }
        $code = Tokens::base64url(random_bytes(32));
        DB::table('auth_codes')->insert([
            'code_hash' => hash('sha256', $code), 'user_id' => $request->user()->id, 'client_id' => $client->id,
            'user_session_id' => $request->session()->get('central_session_id'),
            'redirect_uri' => $data['redirect_uri'], 'scope' => implode(' ', $scopes),
            'nonce' => $data['nonce'] ?? null, 'code_challenge' => $data['code_challenge'] ?? null,
            'expires_at' => now()->addSeconds(config('sso.code_ttl')),
        ]);
        Audit::record('authorization.issued', $request->user()->id, $client->id);

        return $this->redirect($data['redirect_uri'], ['code' => $code, 'state' => $data['state']]);
    }

    private function redirect(string $uri, array $parameters)
    {
        return redirect()->away($uri.(str_contains($uri, '?') ? '&' : '?').http_build_query($parameters, '', '&', PHP_QUERY_RFC3986));
    }

    private function redirectError(array $data, string $error)
    {
        return $this->redirect($data['redirect_uri'], ['error' => $error, 'state' => $data['state']]);
    }

    public function token(Request $request, Tokens $tokens)
    {
        if ($request->input('grant_type') !== 'authorization_code') {
            return $this->error('unsupported_grant_type');
        }
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:128'], 'redirect_uri' => ['required', 'string', 'max:2048'],
            'client_id' => ['sometimes', 'string', 'max:100'], 'client_secret' => ['sometimes', 'string', 'max:255'],
            'code_verifier' => ['sometimes', 'required', 'regex:/^[A-Za-z0-9._~-]{43,128}$/'],
        ]);
        if ($validator->fails()) {
            return $this->error('invalid_request');
        }
        $basic = $request->getUser() !== null;
        if ($basic && $request->hasAny(['client_id', 'client_secret'])) {
            return $this->error('invalid_request');
        }
        $clientId = $basic ? urldecode($request->getUser()) : $request->input('client_id');
        $secret = $basic ? urldecode($request->getPassword() ?? '') : $request->input('client_secret', '');
        $client = Client::where('client_id', $clientId)->where('active', true)->first();
        if (! $client || ! Hash::check($secret, $client->client_secret_hash)) {
            return $this->error('invalid_client', 401)->header('WWW-Authenticate', 'Basic realm="SSO"');
        }

        return DB::transaction(function () use ($request, $client, $tokens) {
            $code = DB::table('auth_codes')->where('code_hash', hash('sha256', $request->input('code')))->lockForUpdate()->first();
            if (! $code || $code->used_at || now()->gte($code->expires_at) || $code->client_id !== $client->id
                || $code->redirect_uri !== $request->input('redirect_uri')
                || ! $client->redirectUris()->where('redirect_uri', $code->redirect_uri)->exists()) {
                return $this->error('invalid_grant');
            }
            $user = User::find($code->user_id);
            $session = UserSession::find($code->user_session_id);
            if (! $user?->active || $user->must_change_password || ! $session?->isValid()) {
                return $this->error('invalid_grant');
            }
            if ($code->code_challenge && ! hash_equals($code->code_challenge, Tokens::base64url(hash('sha256', $request->input('code_verifier', ''), true)))) {
                return $this->error('invalid_grant');
            }
            // Atomic conditional update also prevents replay on databases without row locks.
            if (DB::table('auth_codes')->where('id', $code->id)->whereNull('used_at')->update(['used_at' => now()]) !== 1) {
                return $this->error('invalid_grant');
            }
            $base = ['iss' => config('sso.issuer'), 'sub' => $user->sub, 'iat' => now()->timestamp,
                'exp' => now()->addSeconds(config('sso.token_ttl'))->timestamp,
                'auth_time' => $session->authenticated_at->timestamp, 'sid' => $session->id];
            $access = $tokens->sign($base + ['aud' => config('sso.issuer').'/userinfo', 'client_id' => $client->client_id,
                'token_use' => 'access', 'scope' => $code->scope, 'jti' => (string) Str::uuid()]);
            $identity = $base + ['aud' => $client->client_id, 'auth_method' => 'password', 'amr' => ['pwd'],
                'at_hash' => Tokens::base64url(substr(hash('sha256', $access, true), 0, 16))] + $tokens->identity($user, $code->scope);
            if ($code->nonce !== null) {
                $identity['nonce'] = $code->nonce;
            }
            Audit::record('token.issued', $user->id, $client->id);

            return response()->json(['access_token' => $access, 'token_type' => 'Bearer',
                'expires_in' => config('sso.token_ttl'), 'id_token' => $tokens->sign($identity), 'scope' => $code->scope]);
        });
    }

    public function userinfo(Request $request, Tokens $tokens)
    {
        try {
            $claims = $tokens->decode($request->bearerToken() ?? '');
            $user = User::where('sub', $claims->sub)->where('active', true)->first();
            $session = UserSession::find($claims->sid);
            if (! $user || $user->must_change_password || ! $session?->isValid() || $session->user_id !== $user->id
                || ! Client::where('client_id', $claims->client_id)->where('active', true)->exists()) {
                throw new \UnexpectedValueException('Token inválido.');
            }
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException $e) {
            return $this->error('invalid_token', 401)->header('WWW-Authenticate', 'Bearer error="invalid_token"');
        }

        return response()->json($tokens->identity($user, $claims->scope));
    }

    public function discovery()
    {
        $issuer = config('sso.issuer');

        return response()->json([
            'issuer' => $issuer, 'authorization_endpoint' => $issuer.'/authorize', 'token_endpoint' => $issuer.'/token',
            'userinfo_endpoint' => $issuer.'/userinfo', 'jwks_uri' => $issuer.'/jwks',
            'response_types_supported' => ['code'], 'grant_types_supported' => ['authorization_code'],
            'subject_types_supported' => ['public'], 'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => ['openid', 'profile', 'email', 'dni'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'code_challenge_methods_supported' => ['S256'], 'claims_parameter_supported' => false,
            'request_parameter_supported' => false, 'request_uri_parameter_supported' => false,
        ]);
    }
}
