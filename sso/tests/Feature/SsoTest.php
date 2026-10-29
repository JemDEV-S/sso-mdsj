<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Tokens;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SsoTest extends TestCase
{
    use RefreshDatabase;

    private static string $privatePath;

    private static string $publicPath;

    private string $password = 'Municipalidad!2026';

    private string $secret = 'test-client-secret-with-enough-entropy';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        if ($config = getenv('OPENSSL_CONF')) {
            $options['config'] = $config;
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $pem, null, $options);
        self::$privatePath = tempnam(sys_get_temp_dir(), 'sso-test-private-');
        self::$publicPath = tempnam(sys_get_temp_dir(), 'sso-test-public-');
        file_put_contents(self::$privatePath, $pem);
        file_put_contents(self::$publicPath, openssl_pkey_get_details($key)['key']);
    }

    public static function tearDownAfterClass(): void
    {
        unlink(self::$privatePath);
        unlink(self::$publicPath);
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['sso.private_key' => self::$privatePath, 'sso.public_key' => self::$publicPath, 'sso.issuer' => 'http://127.0.0.1:8085']);
    }

    private function login(bool $admin = false, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['password' => $this->password, 'is_admin' => $admin]);
        $this->post('/login', ['dni' => $user->dni, 'password' => $this->password])->assertRedirect();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->assertNotNull($user->fresh()->last_login_at);

        return $user;
    }

    private function client(): Client
    {
        $client = Client::create(['client_id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => 'Sistema piloto',
            'client_secret_hash' => Hash::make($this->secret), 'active' => true]);
        $client->redirectUris()->create(['redirect_uri' => 'https://piloto.example/callback']);

        return $client;
    }

    private function authorization(Client $client, array $overrides = []): string
    {
        return '/authorize?'.http_build_query($overrides + ['client_id' => $client->client_id,
            'redirect_uri' => 'https://piloto.example/callback', 'response_type' => 'code',
            'scope' => 'openid profile dni email', 'state' => 'random-state-123456789', 'nonce' => 'random-nonce']);
    }

    private function code(Client $client, array $overrides = []): string
    {
        $response = $this->get($this->authorization($client, $overrides))->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('random-state-123456789', $query['state']);

        return $query['code'];
    }

    private function exchange(Client $client, string $code, array $overrides = [])
    {
        return $this->postJson('/token', $overrides + ['grant_type' => 'authorization_code', 'code' => $code,
            'client_id' => $client->client_id, 'client_secret' => $this->secret, 'redirect_uri' => 'https://piloto.example/callback']);
    }

    public function test_login_and_admin_pages_render(): void
    {
        $this->get('/login')->assertOk()->assertSee('San Jerónimo');
        $this->login(true);
        foreach (['/', '/admin', '/admin/users', '/admin/users/create', '/admin/clients', '/admin/clients/create', '/admin/sessions', '/password'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_authorization_round_trip_signatures_claims_userinfo_and_replay(): void
    {
        $user = $this->login();
        $client = $this->client();
        $code = $this->code($client);
        $this->assertDatabaseMissing('auth_codes', ['code_hash' => $code]);
        $response = $this->exchange($client, $code)->assertOk()->assertJsonPath('token_type', 'Bearer');
        $keys = JWK::parseKeySet($this->get('/jwks')->assertOk()->json());
        $identity = JWT::decode($response->json('id_token'), $keys);
        $this->assertSame($user->sub, $identity->sub);
        $this->assertSame($user->dni, $identity->dni);
        $this->assertSame('random-nonce', $identity->nonce);
        $this->assertSame($client->client_id, $identity->aud);
        $this->assertSame(config('sso.issuer'), $identity->iss);
        $this->assertSame(300, $identity->exp - $identity->iat);
        $this->withToken($response->json('access_token'))->getJson('/userinfo')->assertOk()->assertJsonPath('sub', $user->sub);
        $this->exchange($client, $code)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_login_resumes_original_authorization_and_reuses_central_session(): void
    {
        $client = $this->client();
        $this->get($this->authorization($client))->assertRedirect('/login');
        $this->login();
        $this->code($client);
        $this->code($this->client());
        $this->assertDatabaseCount('user_sessions', 1);
    }

    public function test_redirect_uri_must_match_exactly_and_never_redirects_to_attacker(): void
    {
        $this->get($this->authorization($this->client(), ['redirect_uri' => 'https://attacker.example']))->assertStatus(400)->assertHeaderMissing('Location');
    }

    public function test_expired_code_and_mismatched_client_or_uri_are_rejected(): void
    {
        $this->login();
        $client = $this->client();
        $code = $this->code($client);
        $this->exchange($this->client(), $code)->assertStatus(400);
        $this->exchange($client, $code, ['redirect_uri' => 'https://piloto.example/other'])->assertStatus(400);
        DB::table('auth_codes')->update(['expires_at' => now()->subSecond()]);
        $this->exchange($client, $code)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_wrong_client_secret_is_rejected_without_consuming_code(): void
    {
        $this->login();
        $client = $this->client();
        $code = $this->code($client);
        $this->exchange($client, $code, ['client_secret' => 'wrong'])->assertStatus(401)->assertJsonPath('error', 'invalid_client');
        $this->exchange($client, $code)->assertOk();
    }

    public function test_pkce_s256_verifier_is_required_when_challenge_was_supplied(): void
    {
        $this->login();
        $client = $this->client();
        $verifier = Str::random(64);
        $code = $this->code($client, ['code_challenge' => Tokens::base64url(hash('sha256', $verifier, true)), 'code_challenge_method' => 'S256']);
        $this->exchange($client, $code)->assertStatus(400);
        $this->exchange($client, $code, ['code_verifier' => Str::random(64)])->assertStatus(400);
        $this->exchange($client, $code, ['code_verifier' => $verifier])->assertOk();
    }

    public function test_blocked_user_cannot_login_or_exchange_pending_code(): void
    {
        $user = $this->login();
        $client = $this->client();
        $code = $this->code($client);
        $user->update(['active' => false]);
        $this->exchange($client, $code)->assertStatus(400);
        $this->get('/')->assertRedirect('/login');
        $this->post('/login', ['dni' => $user->dni, 'password' => $this->password])->assertSessionHasErrors('dni');
        $this->assertGuest();
    }

    public function test_regular_user_cannot_access_or_mutate_administration(): void
    {
        $this->login();
        $this->get('/admin')->assertForbidden();
        $this->post('/admin/users', [])->assertForbidden();
        $this->post('/admin/clients', [])->assertForbidden();
    }

    public function test_temporary_password_must_be_changed_before_authorizing(): void
    {
        $user = $this->login(false, ['must_change_password' => true]);
        $client = $this->client();
        $this->get($this->authorization($client))->assertRedirect('/password');
        $this->assertDatabaseCount('auth_codes', 0);
        $this->post('/password', ['current_password' => $this->password, 'password' => 'NuevaClave!2026', 'password_confirmation' => 'NuevaClave!2026'])->assertRedirect();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->assertFalse($user->fresh()->must_change_password);
        $this->code($client);
    }

    public function test_revoked_session_cannot_authorize_or_use_access_token(): void
    {
        $this->login();
        $client = $this->client();
        $token = $this->exchange($client, $this->code($client))->assertOk()->json('access_token');
        UserSession::query()->update(['revoked_at' => now()]);
        $this->withToken($token)->getJson('/userinfo')->assertUnauthorized();
        $this->get('/')->assertRedirect('/login');
    }

    public function test_session_absolute_and_idle_expiration(): void
    {
        $this->login();
        UserSession::query()->update(['expires_at' => now()->subSecond()]);
        $this->get('/')->assertRedirect('/login');
        $this->login();
        UserSession::query()->update(['last_activity_at' => now()->subMinutes(config('session.lifetime') + 1)]);
        $this->get('/')->assertRedirect('/login');
    }

    public function test_logout_invalidates_session(): void
    {
        $user = $this->login();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertNotNull(UserSession::where('user_id', $user->id)->first()->revoked_at);
    }

    public function test_scope_limits_personal_information_and_id_token_is_not_access_token(): void
    {
        $this->login();
        $client = $this->client();
        $response = $this->exchange($client, $this->code($client, ['scope' => 'openid']))->assertOk();
        $this->withToken($response->json('access_token'))->getJson('/userinfo')->assertOk()->assertJsonMissingPath('dni')->assertJsonMissingPath('name');
        $this->withToken($response->json('id_token'))->getJson('/userinfo')->assertUnauthorized();
    }

    public function test_tampered_expired_and_wrong_issuer_tokens_are_rejected(): void
    {
        $user = $this->login();
        $client = $this->client();
        $token = $this->exchange($client, $this->code($client))->assertOk()->json('access_token');
        [$header, $payload, $signature] = explode('.', $token);
        $this->withToken($header.'.'.Tokens::base64url('{"sub":"attacker"}').'.'.$signature)->getJson('/userinfo')->assertUnauthorized();
        $claims = ['sub' => $user->sub, 'iss' => 'https://wrong.example', 'aud' => config('sso.issuer').'/userinfo', 'token_use' => 'access', 'iat' => time(), 'exp' => time() + 300];
        $this->withToken(app(Tokens::class)->sign($claims))->getJson('/userinfo')->assertUnauthorized();
        $this->travel(301)->seconds();
        // JWT's clock is independent of Carbon's test clock.
        JWT::$timestamp = now()->timestamp;
        try {
            $this->withToken($token)->getJson('/userinfo')->assertUnauthorized();
        } finally {
            JWT::$timestamp = null;
        }
    }

    public function test_administrator_can_create_edit_and_block_user_without_changing_sub(): void
    {
        $this->login(true);
        $data = ['dni' => '00123456', 'nombres' => 'Ana', 'apellidos' => 'Quispe', 'email' => '', 'active' => 1,
            'password' => $this->password, 'password_confirmation' => $this->password, 'is_admin' => true];
        $this->post('/admin/users', $data)->assertRedirect('/admin/users');
        $user = User::where('dni', $data['dni'])->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check($this->password, $user->password));
        $this->assertNotSame($this->password, $user->password);
        $sub = $user->sub;
        $this->get('/admin/users/'.$user->id.'/edit')->assertOk();
        unset($data['password'], $data['password_confirmation']);
        $data['dni'] = '00987654';
        $data['active'] = 0;
        $this->put('/admin/users/'.$user->id, $data)->assertRedirect('/admin/users');
        $this->assertSame($sub, $user->fresh()->sub);
        $this->assertFalse($user->fresh()->active);
    }

    public function test_client_registration_hashes_secret_and_rejects_unsafe_redirects(): void
    {
        $this->login(true);
        $data = ['name' => 'Piloto', 'code' => 'piloto', 'active' => 1, 'redirect_uris' => 'https://piloto.example/callback'];
        $response = $this->post('/admin/clients', $data)->assertOk();
        $client = Client::where('code', 'piloto')->firstOrFail();
        $secret = $response->viewData('secret');
        $this->assertTrue(Hash::check($secret, $client->client_secret_hash));
        $this->get('/admin/clients')->assertOk()->assertDontSee($secret);
        $this->get('/admin/clients/'.$client->id.'/edit')->assertOk();
        foreach (['https://piloto.example/#fragment', 'https://user:pass@piloto.example/callback', 'https://*.example/callback', 'http://external.example/callback'] as $uri) {
            $this->post('/admin/clients', ['code' => Str::random(8), 'redirect_uris' => $uri] + $data)->assertSessionHasErrors('redirect_uris');
        }
    }

    public function test_login_attempts_are_limited_by_identity(): void
    {
        $user = User::factory()->create(['password' => $this->password]);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['dni' => $user->dni, 'password' => 'wrong'])->assertSessionHasErrors('dni');
        }
        $this->post('/login', ['dni' => $user->dni, 'password' => $this->password])->assertSessionHasErrors('dni');
        $this->assertGuest();
        $this->assertDatabaseCount('audit_logs', 5);
    }

    public function test_discovery_and_public_key_do_not_expose_secrets(): void
    {
        $this->get('/.well-known/openid-configuration')->assertOk()->assertJsonPath('response_types_supported', ['code']);
        $this->get('/jwks')->assertOk()->assertJsonPath('keys.0.alg', 'RS256')->assertJsonMissingPath('keys.0.d')->assertHeaderMissing('Set-Cookie');
    }

    public function test_csrf_protects_browser_login_and_logout(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->post('/login', ['dni' => '12345678', 'password' => 'example'])->assertStatus(419);
    }

    public function test_confidential_client_can_use_basic_authentication_without_browser_session(): void
    {
        $this->login();
        $client = $this->client();
        $code = $this->code($client);
        $this->app->detectEnvironment(fn () => 'local');
        $this->withServerVariables(['PHP_AUTH_USER' => $client->client_id, 'PHP_AUTH_PW' => $this->secret])
            ->post('/token', ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => 'https://piloto.example/callback'])
            ->assertOk()->assertHeaderMissing('Set-Cookie');
    }

    public function test_disabled_client_is_rejected_at_authorize_token_and_userinfo(): void
    {
        $this->login();
        $client = $this->client();
        $token = $this->exchange($client, $this->code($client))->assertOk()->json('access_token');
        $code = $this->code($client);
        $client->update(['active' => false]);
        $this->get($this->authorization($client))->assertStatus(400);
        $this->exchange($client, $code)->assertUnauthorized();
        $this->withToken($token)->getJson('/userinfo')->assertUnauthorized();
    }

    public function test_administrative_block_and_session_revocation_take_effect(): void
    {
        $admin = $this->login(true);
        $target = User::factory()->create();
        $session = UserSession::create(['user_id' => $target->id, 'session_identifier' => hash('sha256', 'other-browser'),
            'authenticated_at' => now(), 'last_activity_at' => now(), 'expires_at' => now()->addHour()]);
        $this->delete('/admin/sessions/'.$session->id)->assertRedirect();
        $this->assertNotNull($session->fresh()->revoked_at);
        $session->update(['revoked_at' => null]);
        $this->put('/admin/users/'.$target->id, ['dni' => $target->dni, 'nombres' => $target->nombres, 'apellidos' => $target->apellidos, 'active' => 0])->assertRedirect('/admin/users');
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->put('/admin/users/'.$admin->id, ['dni' => $admin->dni, 'nombres' => $admin->nombres, 'apellidos' => $admin->apellidos, 'active' => 0])->assertSessionHasErrors('active');
    }

    public function test_prompt_none_and_invalid_scope_return_protocol_errors(): void
    {
        $client = $this->client();
        $response = $this->get($this->authorization($client, ['prompt' => 'none']))->assertRedirect();
        $this->assertStringContainsString('error=login_required', $response->headers->get('Location'));
        $response = $this->get($this->authorization($client, ['scope' => 'openid administrator']))->assertRedirect();
        $this->assertStringContainsString('error=invalid_scope', $response->headers->get('Location'));
        $this->get($this->authorization($client, ['response_type' => 'token']))->assertStatus(400);
    }

    public function test_sub_cannot_be_reassigned_and_dni_must_be_unique(): void
    {
        $user = User::factory()->create();
        $this->login(true);
        $this->post('/admin/users', ['dni' => $user->dni, 'nombres' => 'Otro', 'apellidos' => 'Usuario', 'active' => 1,
            'password' => $this->password, 'password_confirmation' => $this->password])->assertSessionHasErrors('dni');
        $this->expectException(\LogicException::class);
        $user->sub = (string) Str::uuid();
        $user->save();
    }

    public function test_production_rejects_plain_http(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->get('/login')->assertStatus(400);
    }
}
