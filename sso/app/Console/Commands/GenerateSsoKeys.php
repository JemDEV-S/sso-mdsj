<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateSsoKeys extends Command
{
    protected $signature = 'sso:keys';

    protected $description = 'Genera las claves RSA del SSO sin sobrescribir claves existentes';

    public function handle(): int
    {
        $private = config('sso.private_key');
        $public = config('sso.public_key');
        if (file_exists($private) || file_exists($public)) {
            $this->error('Ya existen claves. No se sobrescribieron.');

            return self::FAILURE;
        }
        $options = ['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        if ($config = getenv('OPENSSL_CONF')) {
            $options['config'] = $config;
        }
        $key = openssl_pkey_new($options);
        if (! $key || ! openssl_pkey_export($key, $pem, null, $options)) {
            $this->error('No se pudo generar la clave RSA. Verifique OPENSSL_CONF.');

            return self::FAILURE;
        }
        foreach (array_unique([dirname($private), dirname($public)]) as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
        }
        file_put_contents($private, $pem, LOCK_EX);
        chmod($private, 0600);
        file_put_contents($public, openssl_pkey_get_details($key)['key'], LOCK_EX);
        $this->info('Claves RSA generadas fuera del directorio público.');

        return self::SUCCESS;
    }
}
