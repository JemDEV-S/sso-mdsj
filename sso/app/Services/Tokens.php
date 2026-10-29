<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Tokens
{
    public static function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public function jwk(): array
    {
        $key = openssl_pkey_get_public(file_get_contents(config('sso.public_key')));
        $details = openssl_pkey_get_details($key);

        return ['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256',
            'kid' => hash('sha256', $details['rsa']['n']),
            'n' => self::base64url($details['rsa']['n']), 'e' => self::base64url($details['rsa']['e'])];
    }

    public function sign(array $claims): string
    {
        return JWT::encode($claims, file_get_contents(config('sso.private_key')), 'RS256', $this->jwk()['kid']);
    }

    public function decode(string $token): object
    {
        $claims = JWT::decode($token, new Key(file_get_contents(config('sso.public_key')), 'RS256'));
        if (($claims->iss ?? null) !== config('sso.issuer') || ($claims->aud ?? null) !== config('sso.issuer').'/userinfo'
            || ($claims->token_use ?? null) !== 'access') {
            throw new \UnexpectedValueException('Token inválido.');
        }

        return $claims;
    }

    public function identity(User $user, string $scope): array
    {
        $claims = ['sub' => $user->sub];
        $scopes = explode(' ', $scope);
        if (in_array('profile', $scopes, true)) {
            $claims += ['name' => $user->name, 'given_name' => $user->nombres, 'family_name' => $user->apellidos];
        }
        if (in_array('dni', $scopes, true)) {
            $claims['dni'] = $user->dni;
        }
        if (in_array('email', $scopes, true) && $user->email) {
            $claims['email'] = $user->email;
        }

        return $claims;
    }
}
