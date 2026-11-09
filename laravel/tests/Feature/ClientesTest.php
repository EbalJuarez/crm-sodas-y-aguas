<?php

namespace Tests\Feature;

use App\Http\Requests\GuardarClienteRequest;
use App\Models\Cliente;
use App\Models\User;
use App\Support\Correo;
use App\Support\Nit;
use App\Support\Telefono;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ClientesTest extends TestCase
{
    use RefreshDatabase;

    private User $alicia;

    private User $sergio;

    private User $sandra;

    private User $valeria;

    private User $diego;

    private User $carlos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alicia = User::factory()->create(['name' => 'Alicia Morales']);
        $this->sergio = User::factory()->supervisor()->create(['name' => 'Sergio Ruiz']);
        $this->sandra = User::factory()->supervisor()->create(['name' => 'Sandra Pérez']);
        $this->valeria = User::factory()->vendedor($this->sergio)->create(['name' => 'Valeria López']);
        $this->diego = User::factory()->vendedor($this->sergio)->create(['name' => 'Diego Castillo']);
        $this->carlos = User::factory()->vendedor($this->sandra)->create(['name' => 'Carlos Ajú']);
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Comercial Nueva',
            'nit' => '4521817-K',
            'telefono' => '+502 5555-1234',
            'correo' => 'cliente@example.test',
        ], $cambios);
    }

    private function crearCliente(?int $vendedorId = null, array $cambios = []): Cliente
    {
        return Cliente::create(array_merge([
            'vendedor_id' => $vendedorId,
            'nombre' => 'Cliente '.fake()->unique()->numerify('####'),
            'nit' => null,
            'telefono' => '5555'.fake()->unique()->numerify('####'),
            'correo' => null,
        ], $cambios));
    }

    public function test_recorre_los_casos_de_normalizacion_compartidos(): void
    {
        $casos = json_decode(file_get_contents(base_path('../docs/casos-normalizacion.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($casos['nit'] as $caso) {
            $this->assertSame($caso['valido'], Nit::esValido($caso['entrada']), $caso['entrada']);
            $this->assertSame($caso['normalizado'], Nit::normalizar($caso['entrada']), $caso['entrada']);
        }

        foreach ($casos['telefono'] as $caso) {
            $this->assertSame($caso['valido'], Telefono::esValido($caso['entrada']), $caso['entrada']);
            $this->assertSame($caso['normalizado'], Telefono::normalizar($caso['entrada']), $caso['entrada']);
        }

        // La regla completa del campo en el panel (`email` + Correo::PATRON + max:150).
        $reglaCorreo = ['correo' => (new GuardarClienteRequest)->rules()['correo']];
        foreach ($casos['correo'] as $caso) {
            $acepta = Validator::make(['correo' => $caso['entrada']], $reglaCorreo)->passes();
            $this->assertSame($caso['valido'], $acepta, json_encode($caso['entrada']));
            $this->assertSame($caso['valido'], Correo::esValido($caso['entrada']), json_encode($caso['entrada']));
        }
    }

    public function test_busqueda_con_filtros_en_forma_de_arreglo_responde_400(): void
    {
        $this->actingAs($this->alicia)->get('/clientes?q[]=x')->assertStatus(400)
            ->assertSee('La solicitud no es válida.');
        $this->get('/clientes?q[a]=x')->assertStatus(400);
        $this->get('/clientes?vendedor_id[]=4')->assertStatus(400);
        $this->get('/clientes?q=')->assertOk();
    }

    public function test_busqueda_toma_porcentaje_y_guion_bajo_como_texto(): void
    {
        $this->crearCliente(null, ['nombre' => 'Tienda 100% agua', 'telefono' => '55551234']);
        $this->crearCliente(null, ['nombre' => 'Tienda Central', 'telefono' => '55551235']);

        $this->actingAs($this->alicia)->get(route('clientes.index', ['q' => '%']))
            ->assertOk()->assertSee('Tienda 100% agua')->assertDontSee('Tienda Central');
        $this->get(route('clientes.index', ['q' => '_']))
            ->assertOk()->assertDontSee('Tienda 100% agua')->assertDontSee('Tienda Central');
        $this->get(route('clientes.index', ['q' => "' OR 1=1 -- "]))
            ->assertOk()->assertDontSee('Tienda Central');
    }

    public function test_crea_cliente_normalizado_y_acepta_varios_cf(): void
    {
        $this->actingAs($this->alicia)
            ->post(route('clientes.store'), $this->datos())
            ->assertRedirect(route('clientes.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clientes', [
            'nombre' => 'Comercial Nueva',
            'nit' => '4521817K',
            'telefono' => '55551234',
        ]);

        $this->post(route('clientes.store'), $this->datos([
            'nombre' => 'Hogar CF 1',
            'nit' => 'cf',
            'telefono' => '55551235',
        ]))->assertSessionHasNoErrors();
        $this->post(route('clientes.store'), $this->datos([
            'nombre' => 'Hogar CF 2',
            'nit' => 'CF',
            'telefono' => '55551236',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, Cliente::whereNull('nit')->count());
    }

    public function test_rechaza_datos_invalidos_y_duplicados_normalizados(): void
    {
        $this->actingAs($this->alicia)->post(route('clientes.store'), $this->datos());

        $this->from(route('clientes.create'))
            ->post(route('clientes.store'), $this->datos([
                'nombre' => '',
                'nit' => '4521817-1',
                'telefono' => '0212 3456',
            ]))
            ->assertRedirect(route('clientes.create'))
            ->assertSessionHasErrors(['nombre', 'nit', 'telefono']);

        $this->post(route('clientes.store'), $this->datos([
            'nombre' => 'Duplicado NIT',
            'telefono' => '55551235',
        ]))->assertSessionHasErrors('nit');

        $this->post(route('clientes.store'), $this->datos([
            'nombre' => 'Duplicado teléfono',
            'nit' => '98765434',
        ]))->assertSessionHasErrors('telefono');
    }

    public function test_lista_busca_por_nombre_nit_y_filtra_por_vendedor_o_disponible(): void
    {
        $equipo = $this->crearCliente($this->valeria->id, ['nombre' => 'Comercial El Quetzal', 'nit' => '4521817K', 'telefono' => '55551234']);
        $this->crearCliente($this->carlos->id, ['nombre' => 'Cliente Sandra', 'telefono' => '55551235']);
        $this->crearCliente(null, ['nombre' => 'Cliente disponible', 'telefono' => '55551236']);

        $this->actingAs($this->sergio)->get(route('clientes.index', ['q' => '452 1817-k']))
            ->assertOk()->assertSee('Comercial El Quetzal')->assertDontSee('Cliente Sandra');
        $this->get(route('clientes.index', ['vendedor_id' => $this->valeria->id]))
            ->assertSee('Comercial El Quetzal')->assertDontSee('Cliente disponible');
        $this->get(route('clientes.index', ['vendedor_id' => 'sin_asignar']))
            ->assertSee('Cliente disponible')->assertDontSee('Comercial El Quetzal');

        $this->assertNotNull($equipo->id);
    }

    public function test_supervisor_no_ve_clientes_ajenos_y_admin_ve_todos(): void
    {
        $ajeno = $this->crearCliente($this->carlos->id, ['nombre' => 'Cliente ajeno', 'telefono' => '55551234']);

        $this->actingAs($this->sergio)->get(route('clientes.index'))
            ->assertOk()->assertDontSee('Cliente ajeno');
        $this->get(route('clientes.edit', $ajeno))->assertNotFound();
        $this->get('/clientes/999999/edit')->assertNotFound();
        $this->actingAs($this->alicia)->get(route('clientes.edit', $ajeno))
            ->assertOk()->assertSee('Cliente ajeno');
    }

    public function test_asigna_y_no_permite_el_cliente_numero_once(): void
    {
        for ($i = 0; $i < 9; $i++) {
            $this->crearCliente($this->carlos->id);
        }
        $primero = $this->crearCliente();
        $segundo = $this->crearCliente();

        $this->actingAs($this->alicia)
            ->patch(route('clientes.asignar', $primero), ['vendedor_id' => $this->carlos->id])
            ->assertSessionHasNoErrors();
        $this->patch(route('clientes.asignar', $segundo), ['vendedor_id' => $this->carlos->id])
            ->assertSessionHasErrors(['vendedor_id' => 'La cartera del vendedor ya tiene 10 clientes, el máximo permitido.']);

        $this->assertSame(10, Cliente::where('vendedor_id', $this->carlos->id)->count());
        $this->assertNull($segundo->fresh()->vendedor_id);
    }

    public function test_no_asigna_a_vendedor_desactivado(): void
    {
        $inactivo = User::factory()->vendedor($this->sergio)->create(['activo' => false]);
        $cliente = $this->crearCliente();

        $this->actingAs($this->sergio)
            ->patch(route('clientes.asignar', $cliente), ['vendedor_id' => $inactivo->id])
            ->assertSessionHasErrors('vendedor_id');
        $this->assertNull($cliente->fresh()->vendedor_id);
    }

    public function test_reasigna_solo_oportunidades_abiertas_y_no_toca_actividades(): void
    {
        $cliente = $this->crearCliente($this->valeria->id);
        $abierta = $this->crearOportunidad($cliente->id, $this->valeria->id, 'negociacion');
        $cerrada = $this->crearOportunidad($cliente->id, $this->valeria->id, 'ganada');
        $actividadId = DB::table('actividades')->insertGetId([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $this->valeria->id,
            'tipo' => 'llamada',
            'descripcion' => 'Actividad histórica',
            'vence_en' => null,
            'completada' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->sergio)
            ->patch(route('clientes.asignar', $cliente), ['vendedor_id' => $this->diego->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->diego->id, DB::table('oportunidades')->where('id', $abierta)->value('vendedor_id'));
        $this->assertSame($this->valeria->id, DB::table('oportunidades')->where('id', $cerrada)->value('vendedor_id'));
        $this->assertSame($this->valeria->id, DB::table('actividades')->where('id', $actividadId)->value('vendedor_id'));
    }

    public function test_libera_clientes_sin_oportunidades_abiertas_y_rechaza_los_demas(): void
    {
        $cerrado = $this->crearCliente($this->valeria->id);
        $this->crearOportunidad($cerrado->id, $this->valeria->id, 'ganada');
        $this->actingAs($this->sergio)->patch(route('clientes.liberar', $cerrado))
            ->assertSessionHasNoErrors();
        $this->assertNull($cerrado->fresh()->vendedor_id);

        $abierto = $this->crearCliente($this->valeria->id);
        $this->crearOportunidad($abierto->id, $this->valeria->id, 'propuesta');
        $this->patch(route('clientes.liberar', $abierto))
            ->assertSessionHasErrors('cliente');
        $this->assertSame($this->valeria->id, $abierto->fresh()->vendedor_id);
    }

    public function test_elimina_solo_clientes_sin_oportunidades_y_borra_sus_actividades(): void
    {
        $cliente = $this->crearCliente();
        DB::table('actividades')->insert([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $this->valeria->id,
            'tipo' => 'tarea',
            'descripcion' => 'Actividad que debe borrarse',
            'vence_en' => null,
            'completada' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($this->alicia)->delete(route('clientes.destroy', $cliente))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('clientes', ['id' => $cliente->id]);
        $this->assertDatabaseMissing('actividades', ['cliente_id' => $cliente->id]);

        $conOportunidad = $this->crearCliente();
        $this->crearOportunidad($conOportunidad->id, $this->valeria->id, 'ganada');
        $this->delete(route('clientes.destroy', $conOportunidad))
            ->assertSessionHasErrors('cliente');
        $this->assertNotNull($conOportunidad->fresh());
    }

    public function test_vendedor_no_entra_al_panel_de_clientes(): void
    {
        $this->actingAs($this->valeria)->get(route('clientes.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_dos_asignaciones_no_superan_el_maximo_de_cartera(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->crearCliente($this->diego->id);
        }
        $cliente = $this->crearCliente();

        $this->actingAs($this->sergio)
            ->patch(route('clientes.asignar', $cliente), ['vendedor_id' => $this->diego->id])
            ->assertSessionHasErrors('vendedor_id');
        $this->assertSame(10, Cliente::where('vendedor_id', $this->diego->id)->count());
    }

    public function test_lo_ajeno_responde_404_antes_de_validar_en_cada_accion(): void
    {
        $ajeno = $this->crearCliente($this->carlos->id, ['nombre' => 'Cliente de Carlos']);
        $inexistente = 999999;
        $invalidos = ['nombre' => '', 'nit' => '1', 'telefono' => '1'];

        foreach ([$ajeno->id, $inexistente] as $id) {
            $this->actingAs($this->sergio)->get("/clientes/{$id}/edit")->assertNotFound();
            $this->put("/clientes/{$id}", $invalidos)->assertNotFound();
            $this->put("/clientes/{$id}", $this->datos(['nit' => 'CF', 'telefono' => '39998877']))->assertNotFound();
            $this->patch("/clientes/{$id}/asignar", [])->assertNotFound();
            $this->patch("/clientes/{$id}/asignar", ['vendedor_id' => $this->valeria->id])->assertNotFound();
            $this->patch("/clientes/{$id}/liberar")->assertNotFound();
        }

        $this->assertSame('Cliente de Carlos', $ajeno->fresh()->nombre);
        $this->assertSame($this->carlos->id, $ajeno->fresh()->vendedor_id);

        // Con un cliente visible, los datos inválidos sí vuelven al formulario con errores.
        $propio = $this->crearCliente($this->valeria->id);
        $this->from(route('clientes.edit', $propio))->put(route('clientes.update', $propio), $invalidos)
            ->assertRedirect(route('clientes.edit', $propio))
            ->assertSessionHasErrors(['nit', 'telefono']);
    }

    public function test_editar_exige_los_campos_y_responde_en_espanol(): void
    {
        $cliente = $this->crearCliente($this->valeria->id, ['nombre' => 'Ferretería Xela', 'nit' => '98765434', 'telefono' => '77612345']);
        $base = ['nombre' => 'Ferretería Xela', 'nit' => '98765434', 'telefono' => '77612345', 'correo' => ''];

        $casos = [
            'nombre' => ['', 'Escriba el nombre del cliente.'],
            'nit' => ['', 'Escriba un NIT válido (con su dígito verificador) o CF.'],
            'telefono' => ['', 'Escriba un teléfono de Guatemala de 8 dígitos.'],
        ];

        foreach ($casos as $campo => [$valor, $mensaje]) {
            $respuesta = $this->actingAs($this->alicia)->from(route('clientes.edit', $cliente))
                ->put(route('clientes.update', $cliente), array_merge($base, [$campo => $valor]))
                ->assertRedirect(route('clientes.edit', $cliente))
                ->assertSessionHasErrors([$campo => $mensaje]);
            if ($campo !== 'nombre') {
                $respuesta->assertSessionHasInput('nombre', 'Ferretería Xela');
            }
            $this->assertCount(1, session('errors')->get($campo), "Un solo mensaje para {$campo}");
        }

        $this->put(route('clientes.update', $cliente), array_merge($base, ['nombre' => '   ']))
            ->assertSessionHasErrors(['nombre' => 'Escriba el nombre del cliente.']);
        $this->put(route('clientes.update', $cliente), array_merge($base, ['nit' => '1234567890123']))
            ->assertSessionHasErrors(['nit' => 'Escriba un NIT válido (con su dígito verificador) o CF.']);
        $this->assertSame('Ferretería Xela', $cliente->fresh()->nombre);

        // Un mensaje de respaldo (regla sin mensaje propio) también sale en español.
        // Un arreglo en lugar de texto no da 500: vuelve con el mensaje del campo.
        $this->put(route('clientes.update', $cliente), array_merge($base, ['nombre' => ['x'], 'nit' => ['x'], 'telefono' => ['x']]))
            ->assertSessionHasErrors([
                'nombre' => 'Escriba el nombre del cliente.',
                'nit' => 'Escriba un NIT válido (con su dígito verificador) o CF.',
                'telefono' => 'Escriba un teléfono de Guatemala de 8 dígitos.',
            ]);
        $this->assertSame('Escriba un texto en el nombre.', __('validation.string', ['attribute' => __('validation.attributes.nombre')]));
    }

    public function test_la_paginacion_esta_en_espanol(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->crearCliente();
        }

        $this->actingAs($this->alicia)->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('Mostrando')
            ->assertSee('resultados')
            ->assertDontSee('Showing');
    }

    public function test_solo_el_administrador_elimina_clientes(): void
    {
        $delEquipo = $this->crearCliente($this->valeria->id);
        $disponible = $this->crearCliente();

        $this->actingAs($this->sergio)->get(route('clientes.index'))
            ->assertOk()
            ->assertDontSee('Eliminar');
        $this->delete(route('clientes.destroy', $delEquipo))->assertForbidden();
        $this->delete(route('clientes.destroy', $disponible))->assertForbidden();
        $this->assertNotNull($delEquipo->fresh());
        $this->assertNotNull($disponible->fresh());

        $this->actingAs($this->alicia)->get(route('clientes.index'))->assertSee('Eliminar');
        $this->delete(route('clientes.destroy', $disponible))->assertSessionHasNoErrors();
        $this->assertNull($disponible->fresh());
    }

    public function test_supervisor_recibe_403_tanto_para_cliente_existente_como_inexistente_al_eliminar(): void
    {
        $cliente = $this->crearCliente($this->valeria->id);

        $this->actingAs($this->sergio)->delete(route('clientes.destroy', $cliente))
            ->assertForbidden();
        $this->actingAs($this->sergio)->delete(route('clientes.destroy', 999999))
            ->assertForbidden();

        $this->actingAs($this->alicia)->delete(route('clientes.destroy', 999999))
            ->assertNotFound();

        $this->assertNotNull($cliente->fresh());
    }

    public function test_asignar_y_liberar_bloquean_primero_users_y_luego_clientes(): void
    {
        $cliente = $this->crearCliente();
        $bloqueos = [];
        DB::listen(function ($consulta) use (&$bloqueos): void {
            if (str_contains($consulta->sql, 'for update')) {
                $bloqueos[] = preg_match('/from `(\w+)`/', $consulta->sql, $m) ? $m[1] : $consulta->sql;
            }
        });

        $this->actingAs($this->alicia)->patch(route('clientes.asignar', $cliente), ['vendedor_id' => $this->valeria->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(['users', 'clientes'], $bloqueos, 'asignar');

        $bloqueos = [];
        $this->patch(route('clientes.liberar', $cliente))->assertSessionHasNoErrors();
        $this->assertSame(['users', 'clientes'], $bloqueos, 'liberar');
        $this->assertNull($cliente->fresh()->vendedor_id);
    }

    public function test_las_demas_puertas_con_formrequest_rechazan_al_rol_antes_de_validar(): void
    {
        $producto = DB::table('productos')->insertGetId(['nombre' => 'Producto X', 'precio' => 10, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->sergio)->put("/productos/{$producto}", ['precio' => 'abc'])->assertForbidden();
        $this->put("/usuarios/{$this->valeria->id}", ['email' => 'no-es-correo'])->assertForbidden();
        $this->patch("/productos/{$producto}/estado", ['activo' => 'x'])->assertForbidden();
    }

    private function crearOportunidad(int $clienteId, int $vendedorId, string $etapa): int
    {
        return DB::table('oportunidades')->insertGetId([
            'cliente_id' => $clienteId,
            'vendedor_id' => $vendedorId,
            'titulo' => 'Oportunidad '.$etapa,
            'etapa' => $etapa,
            'motivo_perdida' => $etapa === 'perdida' ? 'Motivo' : null,
            'fecha_cierre' => in_array($etapa, ['ganada', 'perdida'], true) ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
