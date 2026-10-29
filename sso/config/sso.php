<?php

return [
    'admin' => [
        'dni' => env('SSO_ADMIN_DNI'),
        'nombres' => env('SSO_ADMIN_NOMBRES'),
        'apellidos' => env('SSO_ADMIN_APELLIDOS'),
        'email' => env('SSO_ADMIN_EMAIL'),
        'password' => env('SSO_ADMIN_PASSWORD'),
    ],
    'issuer' => rtrim(env('SSO_ISSUER', env('APP_URL', 'http://127.0.0.1:8085')), '/'),
    'private_key' => env('SSO_PRIVATE_KEY', storage_path('app/private/keys/sso-private.pem')),
    'public_key' => env('SSO_PUBLIC_KEY', storage_path('app/private/keys/sso-public.pem')),
    'token_ttl' => (int) env('SSO_TOKEN_TTL', 300),
    'code_ttl' => (int) env('SSO_CODE_TTL', 60),
    'session_hours' => (int) env('SSO_SESSION_HOURS', 8),
    'login_attempts' => (int) env('SSO_LOGIN_ATTEMPTS', 5),
    'lock_seconds' => (int) env('SSO_LOCK_SECONDS', 900),
];
