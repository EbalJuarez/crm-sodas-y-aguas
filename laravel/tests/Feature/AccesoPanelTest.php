<?php

namespace Tests\Feature;

use App\Http\Middleware\CuentaDelPanel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L2.7–L2.10 y R1: quién entra al panel y cuándo pierde la sesión.
 */
class AccesoPanelTest extends TestCase
{
    use RefreshDatabase;

    private function entrar(User $usuario, string $clave = 'password')
    {
        return $this->post('/login', ['email' => $usuario->email, 'password' => $clave]);
    }

    public function test_el_administrador_entra(): void
    {
        $alicia = User::factory()->create();

        $this->entrar($alicia)->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($alicia);
    }

    public function test_el_supervisor_entra(): void
    {
        $sergio = User::factory()->supervisor()->create();

        $this->entrar($sergio)->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($sergio);
    }

    public function test_el_vendedor_no_entra_y_se_le_envia_a_la_spa(): void
    {
        $valeria = User::factory()->vendedor()->create();

        $this->entrar($valeria)->assertSessionHasErrors(['email' => CuentaDelPanel::MENSAJE_VENDEDOR]);
        $this->assertGuest();
    }

    public function test_una_cuenta_desactivada_no_entra(): void
    {
        $ivan = User::factory()->create(['activo' => false]);

        $this->entrar($ivan)->assertSessionHasErrors(['email' => CuentaDelPanel::MENSAJE_DESACTIVADA]);
        $this->assertGuest();
    }

    public function test_una_clave_incorrecta_no_entra(): void
    {
        $alicia = User::factory()->create();

        $this->entrar($alicia, 'otra-clave')
            ->assertSessionHasErrors(['email' => 'Estas credenciales no coinciden con nuestros registros.']);
        $this->assertGuest();
    }

    public function test_una_cuenta_desactivada_pierde_la_sesion_en_la_siguiente_peticion(): void
    {
        $sergio = User::factory()->supervisor()->create();

        $this->actingAs($sergio)->get('/dashboard')->assertOk();

        // Alicia lo desactiva desde otra sesión.
        $sergio->forceFill(['activo' => false])->save();

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => CuentaDelPanel::MENSAJE_DESACTIVADA]);
        $this->assertGuest();
    }

    public function test_un_vendedor_con_sesion_abierta_es_expulsado(): void
    {
        $valeria = User::factory()->vendedor()->create();

        $this->actingAs($valeria)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_el_panel_no_exige_verificar_el_correo(): void
    {
        $alicia = User::factory()->unverified()->create();

        $this->actingAs($alicia)->get('/dashboard')->assertOk();
    }
}
