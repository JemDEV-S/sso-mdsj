<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'sso:create-admin';

    protected $description = 'Crea un administrador mediante ingreso interactivo de credenciales';

    public function handle(): int
    {
        $data = [
            'dni' => $this->ask('DNI (8 dígitos)'), 'nombres' => $this->ask('Nombres'),
            'apellidos' => $this->ask('Apellidos'), 'email' => $this->ask('Correo (opcional)'),
            'password' => $this->secret('Contraseña (12 caracteres, mayúscula, minúscula, número y símbolo)'),
            'password_confirmation' => $this->secret('Repita la contraseña'),
        ];
        $validator = Validator::make($data, [
            'dni' => ['required', 'regex:/^[0-9]{8}$/', 'unique:users,dni'],
            'nombres' => ['required', 'string', 'max:150'], 'apellidos' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'confirmed', 'max:72', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

return self::FAILURE;
        }
        unset($data['password_confirmation']);
        $user = new User($data);
        $user->is_admin = true;
        $user->must_change_password = false;
        $user->save();
        Audit::record('admin.created', $user->id);
        $this->info('Administrador creado.');

        return self::SUCCESS;
    }
}
