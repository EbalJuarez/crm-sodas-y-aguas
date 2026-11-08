<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientesSemillaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VerificacionSeeder::class);
    }

    public function test_l4_1_y_l4_2_respetan_la_visibilidad_y_los_filtros(): void
    {
        $alicia = User::where('email', 'admin@crm.test')->firstOrFail();
        $sergio = User::where('email', 'sergio@crm.test')->firstOrFail();

        $this->actingAs($alicia)->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('Comercial El Quetzal')
            ->assertSee('Hotel Los Pinos');

        $this->actingAs($sergio)->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('Comercial El Quetzal')
            ->assertSee('Agencia Cuatro Caminos')
            ->assertDontSee('Librería Cantel')
            ->assertDontSee('Hotel Los Pinos');

        $this->get(route('clientes.index', ['vendedor_id' => 'sin_asignar']))
            ->assertSee('Agencia Cuatro Caminos')
            ->assertDontSee('Comercial El Quetzal');
    }

    public function test_l4_3_l4_4_l4_5_y_l4_6_validan_identidad(): void
    {
        $sergio = User::where('email', 'sergio@crm.test')->firstOrFail();
        $datos = [
            'nombre' => 'Cliente de prueba',
            'nit' => '452 1817-k',
            'telefono' => '+502 5555-1234',
            'correo' => null,
        ];

        $this->actingAs($sergio)->post(route('clientes.store'), $datos)->assertSessionHasErrors('nit');
        $this->post(route('clientes.store'), array_merge($datos, ['nit' => '98765434']))
            ->assertSessionHasErrors('telefono');
        $this->post(route('clientes.store'), array_merge($datos, [
            'nombre' => 'Cliente CF nuevo',
            'nit' => 'CF',
            'telefono' => '55551235',
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes', ['nombre' => 'Cliente CF nuevo', 'nit' => null]);

        $this->post(route('clientes.store'), array_merge($datos, [
            'nombre' => 'Cliente inválido',
            'nit' => '4521817-1',
            'telefono' => '0212 3456',
        ]))->assertSessionHasErrors(['nit', 'telefono']);
    }

    public function test_l4_7_rechaza_la_cartera_numero_once(): void
    {
        $alicia = User::where('email', 'admin@crm.test')->firstOrFail();
        $carlos = User::where('email', 'carlos@crm.test')->firstOrFail();
        $disponibles = Cliente::whereNull('vendedor_id')->orderBy('id')->take(2)->get();

        $this->actingAs($alicia)->patch(route('clientes.asignar', $disponibles[0]), ['vendedor_id' => $carlos->id])
            ->assertSessionHasNoErrors();
        $this->patch(route('clientes.asignar', $disponibles[1]), ['vendedor_id' => $carlos->id])
            ->assertSessionHasErrors('vendedor_id');

        $this->assertSame(10, Cliente::where('vendedor_id', $carlos->id)->count());
    }

    public function test_l4_8_reasigna_abiertas_y_conserva_cerradas_e_historial(): void
    {
        $alicia = User::where('email', 'admin@crm.test')->firstOrFail();
        $valeria = User::where('email', 'valeria@crm.test')->firstOrFail();
        $diego = User::where('email', 'diego@crm.test')->firstOrFail();
        $cliente = Cliente::where('nombre', 'Comercial El Quetzal')->firstOrFail();
        $actividadAntes = DB::table('actividades')->where('cliente_id', $cliente->id)->first();
        $historialAntes = DB::table('historial_etapas')->count();

        $this->actingAs($alicia)->patch(route('clientes.asignar', $cliente), ['vendedor_id' => $diego->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($diego->id, DB::table('oportunidades')->where('titulo', 'Ampliación a bodega')->value('vendedor_id'));
        $this->assertSame($diego->id, DB::table('oportunidades')->where('titulo', 'Seguimiento anual')->value('vendedor_id'));
        $this->assertSame($valeria->id, DB::table('oportunidades')->where('titulo', 'Renovación de planes')->value('vendedor_id'));
        $this->assertSame($actividadAntes->vendedor_id, DB::table('actividades')->where('id', $actividadAntes->id)->value('vendedor_id'));
        $this->assertSame($historialAntes, DB::table('historial_etapas')->count());
        $this->assertSame(2, Cliente::where('vendedor_id', $valeria->id)->count());
        $this->assertSame(3, Cliente::where('vendedor_id', $diego->id)->count());
    }

    public function test_l4_9_rechaza_liberar_con_oportunidades_abiertas(): void
    {
        $sergio = User::where('email', 'sergio@crm.test')->firstOrFail();
        $cliente = Cliente::where('nombre', 'Comercial El Quetzal')->firstOrFail();

        $this->actingAs($sergio)->patch(route('clientes.liberar', $cliente))
            ->assertSessionHasErrors([
                'cliente' => 'Este cliente tiene oportunidades abiertas. Reasigne el cliente (sus oportunidades abiertas pasan al nuevo vendedor) o pida al vendedor que las cierre.',
            ]);
        $this->assertNotNull($cliente->fresh()->vendedor_id);
    }

    public function test_l4_13_libera_cliente_con_solo_oportunidades_cerradas(): void
    {
        $sergio = User::where('email', 'sergio@crm.test')->firstOrFail();
        $valeria = User::where('email', 'valeria@crm.test')->firstOrFail();
        $cliente = Cliente::where('nombre', 'María Coyoy')->firstOrFail();
        $actividad = DB::table('actividades')->where('cliente_id', $cliente->id)->firstOrFail();

        $this->actingAs($sergio)->patch(route('clientes.liberar', $cliente))
            ->assertSessionHasNoErrors();

        $this->assertNull($cliente->fresh()->vendedor_id);
        $this->assertSame(2, Cliente::where('vendedor_id', $valeria->id)->count());
        $this->assertSame(7, Cliente::whereNull('vendedor_id')->count());
        $this->assertSame($valeria->id, DB::table('oportunidades')->where('cliente_id', $cliente->id)->value('vendedor_id'));
        $this->assertSame($valeria->id, DB::table('actividades')->where('id', $actividad->id)->value('vendedor_id'));
    }

    public function test_l4_15_y_l4_16_respetan_vendedores_desactivados(): void
    {
        $alicia = User::where('email', 'admin@crm.test')->firstOrFail();
        $sandra = User::where('email', 'sandra@crm.test')->firstOrFail();
        $carlos = User::where('email', 'carlos@crm.test')->firstOrFail();
        $hotel = Cliente::where('nombre', 'Hotel Los Pinos')->firstOrFail();

        $this->actingAs($alicia)->get(route('clientes.index'))
            ->assertSee('Hotel Los Pinos')
            ->assertSee('vendedor desactivado');
        $this->get(route('clientes.index', ['vendedor_id' => 'sin_asignar']))
            ->assertDontSee('Hotel Los Pinos');

        $this->actingAs($sandra)->patch(route('clientes.asignar', $hotel), ['vendedor_id' => $carlos->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($carlos->id, $hotel->fresh()->vendedor_id);
        $this->assertSame(10, Cliente::where('vendedor_id', $carlos->id)->count());
    }

    public function test_l4_17_reactivar_vendedor_no_mueve_su_cartera(): void
    {
        $alicia = User::where('email', 'admin@crm.test')->firstOrFail();
        $ivan = User::where('email', 'ivan@crm.test')->firstOrFail();
        $hotel = Cliente::where('nombre', 'Hotel Los Pinos')->firstOrFail();

        $this->actingAs($alicia)->put(route('usuarios.update', $ivan), [
            'name' => $ivan->name,
            'email' => $ivan->email,
            'rol' => $ivan->rol,
            'supervisor_id' => $ivan->supervisor_id,
            'activo' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame($ivan->id, $hotel->fresh()->vendedor_id);
    }

    public function test_l4_14_editar_con_valores_repetidos_falla_y_sin_cambios_no_choca(): void
    {
        $sergio = User::where('email', 'sergio@crm.test')->firstOrFail();
        $cliente = Cliente::where('nombre', 'Ferretería Xela')->firstOrFail();

        $datos = [
            'nombre' => $cliente->nombre,
            'nit' => '4521817K',
            'telefono' => '55551234',
            'correo' => $cliente->correo,
        ];

        $this->actingAs($sergio)->put(route('clientes.update', $cliente), $datos)
            ->assertSessionHasErrors(['nit', 'telefono']);

        $this->put(route('clientes.update', $cliente), [
            'nombre' => $cliente->nombre,
            'nit' => $cliente->nit,
            'telefono' => $cliente->telefono,
            'correo' => $cliente->correo,
        ])->assertSessionHasNoErrors();
    }

    public function test_l4_10_y_l4_11_controlan_eliminacion(): void
    {
        $alicia = User::where('email', 'admin@crm.test')->firstOrFail();
        $cliente = Cliente::where('nombre', 'Agencia Cuatro Caminos')->firstOrFail();

        $this->actingAs($alicia)->delete(route('clientes.destroy', $cliente))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('clientes', ['id' => $cliente->id]);

        $conOportunidad = Cliente::where('nombre', 'Comercial El Quetzal')->firstOrFail();
        $this->delete(route('clientes.destroy', $conOportunidad))
            ->assertSessionHasErrors('cliente');
        $this->assertNotNull($conOportunidad->fresh());
    }

    public function test_l4_12_no_permite_asignar_a_vendedor_desactivado(): void
    {
        $alicia = User::where('email', 'admin@crm.test')->firstOrFail();
        $ivan = User::where('email', 'ivan@crm.test')->firstOrFail();
        $cliente = Cliente::whereNull('vendedor_id')->firstOrFail();

        $this->actingAs($alicia)->patch(route('clientes.asignar', $cliente), ['vendedor_id' => $ivan->id])
            ->assertSessionHasErrors('vendedor_id');
        $this->assertNull($cliente->fresh()->vendedor_id);
    }
}
