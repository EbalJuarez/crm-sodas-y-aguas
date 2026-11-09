<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** L5 (equipos y visibilidad) y S5 (panel) con la semilla de verificación. */
class EquiposTest extends TestCase
{
    use RefreshDatabase;

    private User $alicia;

    private User $sergio;

    private User $sandra;

    private User $valeria;

    private User $carlos;

    private User $ivan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VerificacionSeeder::class);

        $usuario = fn (string $correo) => User::where('email', $correo)->firstOrFail();
        $this->alicia = $usuario('admin@crm.test');
        $this->sergio = $usuario('sergio@crm.test');
        $this->sandra = $usuario('sandra@crm.test');
        $this->valeria = $usuario('valeria@crm.test');
        $this->carlos = $usuario('carlos@crm.test');
        $this->ivan = $usuario('ivan@crm.test');
    }

    private function cliente(string $nombre): Cliente
    {
        return Cliente::where('nombre', $nombre)->firstOrFail();
    }

    public function test_los_scopes_definen_lo_que_ve_cada_uno(): void
    {
        // L4.2: Sergio ve los 5 de Valeria y Diego y los 6 disponibles; nada de Carlos ni de Iván.
        $this->assertSame(11, Cliente::query()->visiblesPara($this->sergio)->count());
        $this->assertSame(16, Cliente::query()->visiblesPara($this->sandra)->count()); // 9 + 1 + 6
        $this->assertSame(21, Cliente::query()->visiblesPara($this->alicia)->count());
        $this->assertSame(0, Cliente::query()->visiblesPara($this->valeria)->count());

        $this->assertSame(['Diego Castillo', 'Valeria López'],
            User::query()->equipoDe($this->sergio)->orderBy('name')->pluck('name')->all());
        $this->assertSame(['Carlos Ajú', 'Iván Toj'],
            User::query()->equipoDe($this->sandra)->orderBy('name')->pluck('name')->all());
        // D4: Iván sigue en el equipo de Sandra, pero no recibe clientes nuevos.
        $this->assertSame(['Carlos Ajú'],
            User::query()->asignablesPor($this->sandra)->pluck('name')->all());
        $this->assertSame(4, User::query()->equipoDe($this->alicia)->count());
        $this->assertSame(3, User::query()->asignablesPor($this->alicia)->count());
    }

    public function test_la_policy_responde_404_y_no_403(): void
    {
        $respuesta = Gate::forUser($this->sergio)->inspect('gestionar', $this->cliente('Librería Cantel'));
        $this->assertTrue($respuesta->denied());
        $this->assertSame(404, $respuesta->status());

        $this->assertTrue(Gate::forUser($this->sergio)->allows('gestionar', $this->cliente('Ferretería Xela')));
        $this->assertTrue(Gate::forUser($this->sergio)->allows('gestionar', $this->cliente('Gimnasio Fuerza')));
        $this->assertTrue(Gate::forUser($this->alicia)->allows('gestionar', $this->cliente('Librería Cantel')));
    }

    public function test_s5_sergio_contra_clientes_de_sandra_recibe_404_antes_de_validar(): void
    {
        $this->actingAs($this->sergio);

        foreach (['Librería Cantel', 'Hotel Los Pinos'] as $nombre) { // de Carlos y de Iván
            $cliente = $this->cliente($nombre);
            $antes = $cliente->only(['nombre', 'nit', 'telefono', 'vendedor_id']);

            $this->get("/clientes/{$cliente->id}/edit")->assertNotFound();
            $this->put("/clientes/{$cliente->id}", ['nombre' => '', 'nit' => 'x', 'telefono' => 'x'])->assertNotFound();
            $this->put("/clientes/{$cliente->id}", ['nombre' => 'Cambiado', 'nit' => 'CF', 'telefono' => '39998877'])->assertNotFound();
            $this->patch("/clientes/{$cliente->id}/asignar", [])->assertNotFound();
            $this->patch("/clientes/{$cliente->id}/asignar", ['vendedor_id' => $this->valeria->id])->assertNotFound();
            $this->patch("/clientes/{$cliente->id}/liberar")->assertNotFound();

            $this->assertSame($antes, $cliente->fresh()->only(['nombre', 'nit', 'telefono', 'vendedor_id']));
        }
    }

    public function test_l5_4_sergio_no_ve_a_carlos_ni_a_ivan_ni_sus_clientes(): void
    {
        $this->actingAs($this->sergio)->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('Comercial El Quetzal')
            ->assertSee('Gimnasio Fuerza')
            ->assertDontSee('Librería Cantel')
            ->assertDontSee('Hotel Los Pinos')
            ->assertDontSee('Carlos Ajú')
            ->assertDontSee('Iván Toj');

        // Aunque filtre por el id de Carlos, no aparece nada de él.
        $this->get(route('clientes.index', ['vendedor_id' => $this->carlos->id]))
            ->assertOk()->assertDontSee('Librería Cantel')->assertSee('No hay clientes.');

        $this->get(route('oportunidades.index'))->assertOk()->assertDontSee('Carlos Ajú')->assertDontSee('Iván Toj');
        $this->get(route('equipo.index'))->assertOk()->assertDontSee('Carlos Ajú')->assertDontSee('Iván Toj');
    }

    public function test_l5_2_sergio_no_asigna_a_carlos_ni_a_ivan_ni_a_quien_no_es_vendedor(): void
    {
        $disponible = $this->cliente('Gimnasio Fuerza');
        $this->actingAs($this->sergio);

        foreach ([$this->carlos, $this->ivan, $this->sandra, $this->alicia] as $vendedor) {
            $this->from(route('clientes.index'))
                ->patch(route('clientes.asignar', $disponible), ['vendedor_id' => $vendedor->id])
                ->assertRedirect(route('clientes.index'))
                ->assertSessionHasErrors(['vendedor_id' => 'El vendedor debe ser una cuenta de vendedor activa de su equipo.']);
        }
        $this->assertNull($disponible->fresh()->vendedor_id);

        // Ni Carlos ni Iván aparecen como opción de asignación.
        $this->get(route('clientes.index'))->assertDontSee('Carlos Ajú');

        $this->patch(route('clientes.asignar', $disponible), ['vendedor_id' => $this->valeria->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->valeria->id, $disponible->fresh()->vendedor_id);
    }

    public function test_sandra_no_asigna_a_ivan_desactivado_pero_ve_su_cartera(): void
    {
        $disponible = $this->cliente('Gimnasio Fuerza');

        $this->actingAs($this->sandra)
            ->patch(route('clientes.asignar', $disponible), ['vendedor_id' => $this->ivan->id])
            ->assertSessionHasErrors('vendedor_id');
        $this->assertNull($disponible->fresh()->vendedor_id);

        // D4: Hotel Los Pinos sigue con Iván, marcado, y Sandra lo puede ver y reasignar.
        $this->get(route('equipo.index', ['mes' => '2026-10']))
            ->assertOk()
            ->assertSeeInOrder(['Carlos Ajú', '9 de 10', 'Iván Toj', '(vendedor desactivado)', '1 de 10'], false);
        $this->get(route('clientes.edit', $this->cliente('Hotel Los Pinos')))->assertOk();
    }

    public function test_l5_3_mi_equipo_con_cartera_y_ventas_del_mes(): void
    {
        $this->actingAs($this->sergio)->get(route('equipo.index', ['mes' => '2026-10']))
            ->assertOk()
            ->assertSee('Mi equipo')
            ->assertSee('octubre de 2026')
            ->assertSeeInOrder(['Diego Castillo', '2 de 10', '1', 'Q6,000.00', 'Q112.50'])
            ->assertSeeInOrder(['Valeria López', '3 de 10', '2', 'Q9,250.00', 'Q2,512.88']);

        $this->get(route('equipo.index', ['mes' => '2026-11']))
            ->assertSeeInOrder(['Valeria López', '3 de 10', '1', 'Q3,600.00']);

        $this->get(route('equipo.index', ['mes' => '2026-13']))
            ->assertRedirect(route('equipo.index'))
            ->assertSessionHasErrors(['mes' => 'Elija un mes válido con el formato AAAA-MM (por ejemplo, 2026-10).']);
    }

    public function test_el_administrador_ve_todo(): void
    {
        $this->actingAs($this->alicia)->get(route('equipo.index', ['mes' => '2026-10']))
            ->assertOk()
            ->assertSee('Vendedores')
            ->assertSeeInOrder(['Carlos Ajú', 'Diego Castillo', 'Iván Toj', 'Valeria López'])
            ->assertSee('Q7,200.00');

        $this->get(route('clientes.index'))
            ->assertOk()->assertSee('Librería Cantel')->assertSee('Hotel Los Pinos')->assertSee('Comercial El Quetzal');
        $this->get(route('clientes.edit', $this->cliente('Librería Cantel')))->assertOk();

        $disponible = $this->cliente('Gimnasio Fuerza');
        $this->patch(route('clientes.asignar', $disponible), ['vendedor_id' => $this->carlos->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->carlos->id, $disponible->fresh()->vendedor_id);
    }

    public function test_un_vendedor_no_entra_a_mi_equipo(): void
    {
        $this->actingAs($this->valeria)->get(route('equipo.index'))->assertRedirect();
    }
}
