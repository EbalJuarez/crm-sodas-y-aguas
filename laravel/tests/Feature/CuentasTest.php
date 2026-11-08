<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * L2.2–L2.6 y L2.12–L2.14: administración de cuentas por el administrador.
 */
class CuentasTest extends TestCase
{
    use RefreshDatabase;

    private User $alicia;

    private User $sergio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alicia = User::factory()->create(['name' => 'Alicia Morales']);
        $this->sergio = User::factory()->supervisor()->create(['name' => 'Sergio Ruiz']);
    }

    /** Datos válidos de una cuenta, para enviar al formulario. */
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'name' => 'Nueva Cuenta',
            'email' => 'nueva@crm.test',
            'password' => 'clave-segura-1',
            'password_confirmation' => 'clave-segura-1',
            'rol' => 'supervisor',
            'activo' => '1',
        ], $cambios);
    }

    /** Datos actuales de $usuario, como los reenviaría el formulario de edición. */
    private function datosDe(User $usuario, array $cambios = []): array
    {
        return array_merge([
            'name' => $usuario->name,
            'email' => $usuario->email,
            'rol' => $usuario->rol,
            'supervisor_id' => $usuario->supervisor_id,
            'activo' => $usuario->activo ? '1' : '0',
        ], $cambios);
    }

    public function test_una_cuenta_nueva_creada_por_el_administrador_entra_de_inmediato(): void
    {
        $this->actingAs($this->alicia)
            ->post(route('usuarios.store'), $this->datos())
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHasNoErrors();

        $nueva = User::where('email', 'nueva@crm.test')->firstOrFail();
        $this->assertStringStartsWith('$2y$', $nueva->password); // L2.2: hash bcrypt de Laravel
        $this->assertNull($nueva->email_verified_at);             // y aun así entra: no hay verificación

        $this->post('/logout');
        $this->post('/login', ['email' => 'nueva@crm.test', 'password' => 'clave-segura-1'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($nueva);
    }

    public function test_un_vendedor_se_crea_con_su_supervisor(): void
    {
        $this->actingAs($this->alicia)
            ->post(route('usuarios.store'), $this->datos(['rol' => 'vendedor', 'supervisor_id' => $this->sergio->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->sergio->id, User::where('email', 'nueva@crm.test')->value('supervisor_id'));
    }

    public function test_un_vendedor_sin_supervisor_o_con_uno_inactivo_se_rechaza(): void
    {
        $inactivo = User::factory()->supervisor()->create(['activo' => false]);

        $this->actingAs($this->alicia)
            ->post(route('usuarios.store'), $this->datos(['rol' => 'vendedor']))
            ->assertSessionHasErrors(['supervisor_id' => 'Elija el supervisor del vendedor.']);

        $this->post(route('usuarios.store'), $this->datos(['rol' => 'vendedor', 'supervisor_id' => $inactivo->id]))
            ->assertSessionHasErrors(['supervisor_id' => 'El supervisor debe ser una cuenta de supervisor activa.']);

        $this->post(route('usuarios.store'), $this->datos(['rol' => 'vendedor', 'supervisor_id' => $this->alicia->id]))
            ->assertSessionHasErrors('supervisor_id');

        $this->assertDatabaseMissing('users', ['email' => 'nueva@crm.test']);
    }

    public function test_un_supervisor_enviado_a_un_administrador_o_supervisor_queda_vacio(): void
    {
        $this->actingAs($this->alicia)
            ->post(route('usuarios.store'), $this->datos(['rol' => 'administrador', 'supervisor_id' => $this->sergio->id]))
            ->assertSessionHasNoErrors();

        $this->assertNull(User::where('email', 'nueva@crm.test')->value('supervisor_id'));
    }

    public function test_el_administrador_no_puede_desactivarse(): void
    {
        $this->actingAs($this->alicia)
            ->put(route('usuarios.update', $this->alicia), $this->datosDe($this->alicia, ['activo' => '0']))
            ->assertSessionHasErrors(['activo' => 'No puede desactivar su propia cuenta.']);

        $this->assertTrue($this->alicia->fresh()->activo);
    }

    public function test_el_administrador_no_puede_quitarse_el_rol(): void
    {
        $this->actingAs($this->alicia)
            ->put(route('usuarios.update', $this->alicia), $this->datosDe($this->alicia, ['rol' => 'supervisor']))
            ->assertSessionHasErrors(['rol' => 'No puede quitarse el rol de administrador.']);

        $this->assertSame('administrador', $this->alicia->fresh()->rol);
    }

    public function test_no_se_cambia_el_rol_de_un_supervisor_con_vendedores(): void
    {
        User::factory()->vendedor($this->sergio)->create();

        $this->actingAs($this->alicia)
            ->put(route('usuarios.update', $this->sergio), $this->datosDe($this->sergio, ['rol' => 'administrador']))
            ->assertSessionHasErrors([
                'rol' => 'Este supervisor tiene vendedores a su cargo. Reasigne primero a sus vendedores a otro supervisor.',
            ]);

        $this->assertSame('supervisor', $this->sergio->fresh()->rol);
    }

    public function test_no_se_cambia_el_rol_de_un_vendedor_con_clientes(): void
    {
        $valeria = User::factory()->vendedor($this->sergio)->create();
        DB::table('clientes')->insert([
            'vendedor_id' => $valeria->id, 'nombre' => 'Comercial El Quetzal', 'nit' => '4521817K',
            'telefono' => '55551234', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->alicia)
            ->put(route('usuarios.update', $valeria), $this->datosDe($valeria, ['rol' => 'supervisor']))
            ->assertSessionHasErrors(['rol' => 'Esta cuenta tiene clientes asignados. Reasigne primero sus clientes.']);

        $this->assertSame('vendedor', $valeria->fresh()->rol);
    }

    public function test_si_se_puede_desactivar_a_un_supervisor_con_equipo(): void
    {
        $valeria = User::factory()->vendedor($this->sergio)->create();

        $this->actingAs($this->alicia)
            ->put(route('usuarios.update', $this->sergio), $this->datosDe($this->sergio, ['activo' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->sergio->fresh()->activo);
        $this->assertSame($this->sergio->id, $valeria->fresh()->supervisor_id); // L2.14: conserva su supervisor

        // ... y al editar a Valeria, L2.3 exige elegir un supervisor activo.
        $this->put(route('usuarios.update', $valeria), $this->datosDe($valeria->fresh()))
            ->assertSessionHasErrors('supervisor_id');
    }

    public function test_editar_sin_contrasena_no_cambia_el_hash(): void
    {
        $antes = $this->sergio->password;

        $this->actingAs($this->alicia)
            ->put(route('usuarios.update', $this->sergio), $this->datosDe($this->sergio, ['name' => 'Sergio R.']))
            ->assertSessionHasNoErrors();

        $this->assertSame($antes, $this->sergio->fresh()->password);
        $this->assertSame('Sergio R.', $this->sergio->fresh()->name);
    }

    public function test_los_mensajes_y_los_datos_escritos_vuelven_al_formulario(): void
    {
        $this->actingAs($this->alicia)
            ->from(route('usuarios.create'))
            ->post(route('usuarios.store'), $this->datos(['email' => 'no-es-correo']))
            ->assertRedirect(route('usuarios.create'))
            ->assertSessionHasErrors(['email' => 'Escriba un correo válido.'])
            ->assertSessionHasInput('name', 'Nueva Cuenta');
    }

    public function test_las_pantallas_de_alta_y_edicion_se_muestran(): void
    {
        $this->actingAs($this->alicia)->get(route('usuarios.create'))
            ->assertOk()->assertSee('Crear cuenta')->assertSee('Sergio Ruiz');
        $this->get(route('usuarios.edit', $this->sergio))
            ->assertOk()->assertSee('Guardar cambios')->assertSee('Cuenta activa');
        $this->get(route('dashboard'))->assertSee('Cuentas');
    }

    public function test_un_supervisor_no_administra_cuentas(): void
    {
        $this->actingAs($this->sergio)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($this->sergio)->post(route('usuarios.store'), $this->datos())->assertForbidden();
    }

    public function test_las_cuentas_no_se_borran(): void
    {
        $this->actingAs($this->alicia)->delete('/usuarios/'.$this->sergio->id)->assertStatus(405);
        $this->assertNotNull($this->sergio->fresh());
    }

    public function test_la_lista_escapa_los_nombres(): void
    {
        User::factory()->supervisor()->create(['name' => '<img src=x onerror=alert(1)>']);

        $this->actingAs($this->alicia)->get(route('usuarios.index'))
            ->assertOk()
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }
}
