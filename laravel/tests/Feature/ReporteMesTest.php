<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use App\Services\ReporteMes;
use App\Support\Dinero;
use App\Support\ZonaGuatemala;
use Carbon\CarbonImmutable;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** L6 con la semilla de verificación: cifras de control de la sección 11 del enunciado. */
class ReporteMesTest extends TestCase
{
    use RefreshDatabase;

    private ReporteMes $reporte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VerificacionSeeder::class);
        $this->reporte = new ReporteMes;
    }

    private function usuario(string $correo): User
    {
        return User::where('email', $correo)->firstOrFail();
    }

    /** @return array<string, array{ventas: int, total: string, pronostico: string}> nombre => cifras */
    private function porNombre(array $reporte): array
    {
        $filas = [];
        foreach ($reporte['vendedores'] as $fila) {
            $filas[$fila['nombre']] = ['ventas' => $fila['ventas'], 'total' => $fila['total'], 'pronostico' => $fila['pronostico']];
        }

        return $filas;
    }

    public function test_l6_5_empresa_por_vendedor_y_por_producto_en_octubre(): void
    {
        $reporte = $this->reporte->generar($this->usuario('admin@crm.test'), '2026-10');

        $this->assertSame(['ventas' => 4, 'total' => '22450.00', 'pronostico' => '6751.13'], $reporte['totales']);
        $this->assertSame([
            'Carlos Ajú' => ['ventas' => 1, 'total' => '7200.00', 'pronostico' => '4125.75'],
            'Diego Castillo' => ['ventas' => 1, 'total' => '6000.00', 'pronostico' => '112.50'],
            'Iván Toj' => ['ventas' => 0, 'total' => '0.00', 'pronostico' => '0.00'],
            'Valeria López' => ['ventas' => 2, 'total' => '9250.00', 'pronostico' => '2512.88'],
        ], $this->porNombre($reporte));

        $productos = array_map(fn ($fila) => [$fila['nombre'], $fila['cantidad'], $fila['total']], $reporte['productos']);
        $this->assertSame([
            ['Instalación', 1, '450.00'],
            ['Plan avanzado', 2, '5200.00'],
            ['Plan básico', 5, '6000.00'],
            ['Soporte anual', 3, '10800.00'],
        ], $productos);
    }

    public function test_l6_6_supervisores_ven_solo_su_equipo(): void
    {
        $sergio = $this->reporte->generar($this->usuario('sergio@crm.test'), '2026-10');
        $this->assertSame(['ventas' => 3, 'total' => '15250.00', 'pronostico' => '2625.38'], $sergio['totales']);
        $this->assertSame(['Diego Castillo', 'Valeria López'], array_keys($this->porNombre($sergio)));

        $sandra = $this->reporte->generar($this->usuario('sandra@crm.test'), '2026-10');
        $this->assertSame(['ventas' => 1, 'total' => '7200.00', 'pronostico' => '4125.75'], $sandra['totales']);
        $this->assertSame(['Carlos Ajú', 'Iván Toj'], array_keys($this->porNombre($sandra)));
        $this->assertSame([['Soporte anual', 2, '7200.00']],
            array_map(fn ($fila) => [$fila['nombre'], $fila['cantidad'], $fila['total']], $sandra['productos']));
    }

    public function test_l6_7_noviembre_y_un_mes_sin_ventas_da_ceros(): void
    {
        $alicia = $this->usuario('admin@crm.test');

        $noviembre = $this->porNombre($this->reporte->generar($alicia, '2026-11'));
        $this->assertSame(1, $noviembre['Valeria López']['ventas']);
        $this->assertSame('3600.00', $noviembre['Valeria López']['total']);

        $vacio = $this->reporte->generar($alicia, '2025-01');
        $this->assertSame(0, $vacio['totales']['ventas']);
        $this->assertSame('0.00', $vacio['totales']['total']);
        $this->assertSame('6751.13', $vacio['totales']['pronostico']); // el pronóstico no depende del mes
        $this->assertSame([], $vacio['productos']);
    }

    public function test_el_mes_se_agrupa_en_hora_de_guatemala_y_no_en_utc(): void
    {
        // Equipo para sucursal cierra 2026-11-01 03:30 UTC = 31/10 21:30 en Guatemala: es de octubre.
        $this->assertSame(['2026-10-01 06:00:00', '2026-11-01 06:00:00'], ZonaGuatemala::limitesDelMes('2026-10'));
        $this->assertSame(['2026-12-01 06:00:00', '2027-01-01 06:00:00'], ZonaGuatemala::limitesDelMes('2026-12'));

        // Con agrupación en UTC, Valeria daría Q5,650.00 en octubre (error 1).
        $valeria = $this->porNombre($this->reporte->generar($this->usuario('admin@crm.test'), '2026-10'))['Valeria López'];
        $this->assertNotSame('5650.00', $valeria['total']);
        $this->assertSame('9250.00', $valeria['total']);
    }

    public function test_l3_5_el_total_usa_el_precio_de_la_linea_y_no_el_del_catalogo(): void
    {
        // Con el precio del catálogo (Plan avanzado 2750.50), Valeria daría Q9,551.00 (error 2).
        Producto::where('nombre', 'Plan avanzado')->update(['precio' => '9999.99']);

        $reporte = $this->reporte->generar($this->usuario('admin@crm.test'), '2026-10');
        $this->assertSame('9250.00', $this->porNombre($reporte)['Valeria López']['total']);
        $this->assertSame('22450.00', $reporte['totales']['total']);
        $this->assertSame('6751.13', $reporte['totales']['pronostico']);
    }

    public function test_el_mes_actual_y_las_fechas_se_calculan_en_hora_de_guatemala(): void
    {
        $this->assertSame('2026-10', ZonaGuatemala::mesActual(CarbonImmutable::parse('2026-11-01 05:59:59', 'UTC')));
        $this->assertSame('2026-11', ZonaGuatemala::mesActual(CarbonImmutable::parse('2026-11-01 06:00:00', 'UTC')));
        $this->assertSame('31/10/2026 21:30', ZonaGuatemala::formatear('2026-11-01 03:30:00'));
        $this->assertSame('Q22,450.00', Dinero::formatear('22450.00'));
        $this->assertSame('Q0.00', Dinero::formatear('0.00'));
    }

    public function test_la_pagina_del_reporte_muestra_las_cifras_y_el_mes_actual_por_omision(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 03:00:00', 'UTC')); // 31/10 21:00 en Guatemala

        $this->actingAs($this->usuario('admin@crm.test'))->get(route('reportes.mes'))
            ->assertOk()
            ->assertSee('octubre de 2026')
            ->assertSee('Q22,450.00')
            ->assertSee('Q6,751.13')
            ->assertSee('Iván Toj')
            ->assertSee('(vendedor desactivado)', false);

        $this->actingAs($this->usuario('sergio@crm.test'))->get(route('reportes.mes', ['mes' => '2026-10']))
            ->assertOk()
            ->assertSee('Q15,250.00')
            ->assertSee('Q2,625.38')
            ->assertDontSee('Carlos Ajú');
    }

    public function test_un_mes_invalido_se_rechaza_con_mensaje_y_no_con_500(): void
    {
        $alicia = $this->usuario('admin@crm.test');

        foreach (['2026-13', 'octubre', '2026-1', '2026-10x', '1999-12'] as $mes) {
            $this->actingAs($alicia)->get(route('reportes.mes', ['mes' => $mes]))
                ->assertRedirect(route('reportes.mes'))
                ->assertSessionHasErrors(['mes' => 'Elija un mes válido con el formato AAAA-MM (por ejemplo, 2026-10).']);
        }

        $this->get('/reportes/mes?mes[]=2026-10')
            ->assertRedirect(route('reportes.mes'))
            ->assertSessionHasErrors('mes');

        $this->followingRedirects()->get(route('reportes.mes', ['mes' => '2026-13']))
            ->assertOk()
            ->assertSee('Elija un mes válido con el formato AAAA-MM (por ejemplo, 2026-10).');
    }

    public function test_l6_1_l6_2_y_l6_4_lista_de_oportunidades(): void
    {
        $alicia = $this->usuario('admin@crm.test');
        $sergio = $this->usuario('sergio@crm.test');
        $valeria = $this->usuario('valeria@crm.test');
        $carlos = $this->usuario('carlos@crm.test');

        $this->assertSame(11, $this->reporte->listaDeOportunidades($alicia)->count());
        $this->assertSame(9, $this->reporte->listaDeOportunidades($sergio)->count());
        $this->assertSame(0, $this->reporte->listaDeOportunidades($sergio, null, $carlos->id)->count());
        $this->assertSame(2, $this->reporte->listaDeOportunidades($alicia, 'negociacion')->count());
        $this->assertSame(3, $this->reporte->listaDeOportunidades($sergio, 'ganada', $valeria->id)->count());

        $renovacion = $this->reporte->listaDeOportunidades($alicia)->where('o.titulo', 'Renovación de planes')->first();
        $this->assertSame('5650.00', (string) $renovacion->total);
        $sinLineas = $this->reporte->listaDeOportunidades($alicia)->where('o.titulo', 'Seguimiento anual')->first();
        $this->assertSame('0.00', (string) $sinLineas->total);

        $this->actingAs($alicia)->get(route('oportunidades.index'))
            ->assertOk()
            ->assertSee('Equipo para sucursal')
            ->assertSee('31/10/2026 21:30')
            ->assertSee('Q5,650.00')
            ->assertSee('Cotización de equipo');

        $this->actingAs($sergio)->get(route('oportunidades.index'))
            ->assertOk()
            ->assertSee('Renovación de planes')
            ->assertDontSee('Cotización de equipo')
            ->assertDontSee('Carlos Ajú');
    }

    public function test_filtros_invalidos_de_la_lista_responden_400(): void
    {
        $this->actingAs($this->usuario('admin@crm.test'));

        $this->get('/oportunidades?etapa=xyz')->assertStatus(400);
        $this->get('/oportunidades?etapa[]=ganada')->assertStatus(400);
        $this->get('/oportunidades?vendedor_id=abc')->assertStatus(400);
        $this->get('/oportunidades?vendedor_id[]=4')->assertStatus(400);
        $this->get('/oportunidades?etapa=')->assertOk();
    }

    public function test_l6_3_el_panel_no_modifica_oportunidades_y_el_vendedor_no_entra(): void
    {
        $id = DB::table('oportunidades')->where('titulo', 'Seguimiento anual')->value('id');
        $this->actingAs($this->usuario('admin@crm.test'));

        $this->post('/oportunidades', ['titulo' => 'X'])->assertStatus(405);
        $this->patch("/oportunidades/{$id}", ['titulo' => 'X'])->assertNotFound();
        $this->patch("/oportunidades/{$id}/etapa", ['etapa' => 'calificado'])->assertNotFound();
        $this->assertSame('prospecto', DB::table('oportunidades')->where('id', $id)->value('etapa'));

        $this->actingAs($this->usuario('valeria@crm.test'))->get(route('reportes.mes'))->assertRedirect();
    }
}
