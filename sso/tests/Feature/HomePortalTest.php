<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class HomePortalTest extends TestCase
{
    use RefreshDatabase;

    private string $password = 'Municipalidad!2026';

    private function makeClient(array $overrides = []): Client
    {
        return Client::create($overrides + [
            'client_id' => (string) Str::uuid(),
            'code' => Str::random(10),
            'name' => 'Sistema piloto',
            'client_secret_hash' => Hash::make('secret-with-enough-entropy-xx'),
            'active' => true,
        ]);
    }

    /**
     * Autentica con un login real (post /login), como los demás tests: el
     * middleware CentralSession exige una sesión central registrada, así que
     * actingAs() no basta.
     */
    private function login(): User
    {
        $user = User::factory()->create([
            'password' => $this->password,
            'must_change_password' => false,
        ]);
        $this->post('/login', ['dni' => $user->dni, 'password' => $this->password])->assertRedirect();
        $this->withCookie(config('session.cookie'), session()->getId());

        return $user;
    }

    public function test_home_muestra_los_sistemas_con_launch_url(): void
    {
        $this->makeClient([
            'name' => 'SGD SSO Bridge',
            'launch_url' => 'http://127.0.0.1:8095/bridge/login',
            'description' => 'Sistema de Gestión Documental',
        ]);

        $this->login();
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Tus sistemas');
        $response->assertSee('SGD SSO Bridge');
        $response->assertSee('Sistema de Gestión Documental');
        $response->assertSee('http://127.0.0.1:8095/bridge/login');
    }

    public function test_home_omite_clientes_sin_launch_url_o_inactivos(): void
    {
        // Sin launch_url: no debe aparecer.
        $this->makeClient(['name' => 'Sistema Sin URL', 'launch_url' => null]);
        // Inactivo aunque tenga launch_url: no debe aparecer.
        $this->makeClient(['name' => 'Sistema Inactivo', 'active' => false,
            'launch_url' => 'http://ejemplo/login']);

        $this->login();
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Sistema Sin URL');
        $response->assertDontSee('Sistema Inactivo');
    }

    public function test_home_sin_sistemas_muestra_mensaje_vacio(): void
    {
        $this->login();
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('No tienes sistemas disponibles');
    }
}
