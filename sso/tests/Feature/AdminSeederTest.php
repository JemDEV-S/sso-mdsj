<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sso.admin' => [
            'dni' => '00123456', 'nombres' => 'Administrador', 'apellidos' => 'Pruebas',
            'email' => null, 'password' => 'Temporal!2026Segura',
        ]]);
    }

    public function test_database_seeder_creates_admin_with_uuid_hash_and_audit(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::sole();
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->active);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Str::isUuid($user->sub));
        $this->assertTrue(Hash::check(config('sso.admin.password'), $user->password));
        $this->assertNotSame(config('sso.admin.password'), $user->password);
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.created', 'user_id' => $user->id]);

        $before = $user->getAttributes();
        $user->update(['active' => false]);
        config(['sso.admin.password' => 'OtraTemporal!2026']);
        $this->seed(AdminSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame($before['password'], $user->fresh()->password);
        $this->assertSame($before['sub'], $user->fresh()->sub);
        $this->assertFalse($user->fresh()->active);
    }

    public function test_existing_regular_user_is_not_promoted(): void
    {
        $user = User::factory()->create(['dni' => config('sso.admin.dni')]);
        try {
            $this->seed(AdminSeeder::class);
            $this->fail('El seeder debía rechazar el DNI existente.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('sin privilegios', $exception->getMessage());
            $this->assertFalse($user->fresh()->is_admin);
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public function test_missing_configuration_and_weak_password_create_no_accounts(): void
    {
        foreach ([['dni' => null], ['password' => 'weak']] as $invalid) {
            $original = config('sso.admin');
            config(['sso.admin' => array_replace($original, $invalid)]);
            try {
                $this->seed(AdminSeeder::class);
                $this->fail('El seeder debía rechazar los datos inválidos.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey(array_key_first($invalid), $exception->errors());
                $this->assertDatabaseCount('users', 0);
            }
            config(['sso.admin' => $original]);
        }
    }
}
