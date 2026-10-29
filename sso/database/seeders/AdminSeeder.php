<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $data = config('sso.admin');
        Validator::make($data, ['dni' => ['required', 'regex:/^[0-9]{8}$/']])->validate();

        $existing = User::where('dni', $data['dni'])->first();
        if ($existing) {
            if (! $existing->is_admin) {
                throw new RuntimeException('El DNI configurado pertenece a un usuario sin privilegios administrativos. No se modificó la cuenta.');
            }

            $this->command?->info('El administrador ya existe. No se modificaron sus datos ni su contraseña.');

            return;
        }

        Validator::make($data, [
            'nombres' => ['required', 'string', 'max:150'],
            'apellidos' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:72', Password::min(5)->letters()],
        ])->validate();

        DB::transaction(function () use ($data) {
            $user = new User($data);
            $user->is_admin = true;
            $user->active = true;
            $user->must_change_password = true;
            $user->source = 'MANUAL';
            $user->save();

            Audit::record('admin.created', $user->id);
        });

        $this->command?->info('Administrador creado. Deberá cambiar su contraseña en el primer acceso.');
    }
}
