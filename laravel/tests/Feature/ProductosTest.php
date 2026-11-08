<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductosTest extends TestCase
{
    use RefreshDatabase;

    private User $alicia;

    private User $sergio;

    private User $valeria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alicia = User::factory()->create(['name' => 'Alicia Morales']);
        $this->sergio = User::factory()->supervisor()->create(['name' => 'Sergio Ruiz']);
        $this->valeria = User::factory()->vendedor($this->sergio)->create(['name' => 'Valeria López']);
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Garrafa de agua',
            'precio' => '125.50',
            'unidades_por_caja' => null,
        ], $cambios);
    }

    public function test_el_administrador_puede_crear_y_editar_un_producto(): void
    {
        $this->actingAs($this->alicia)
            ->post(route('productos.store'), $this->datos())
            ->assertRedirect(route('productos.index'))
            ->assertSessionHasNoErrors();

        $producto = Producto::where('nombre', 'Garrafa de agua')->firstOrFail();

        $this->assertSame('125.50', $producto->precio);
        $this->assertNull($producto->unidades_por_caja);

        $this->put(route('productos.update', $producto), $this->datos([
            'nombre' => 'Garrafa de agua grande',
            'precio' => '150.75',
            'unidades_por_caja' => '24',
        ]))->assertRedirect(route('productos.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'nombre' => 'Garrafa de agua grande',
            'precio' => '150.75',
            'unidades_por_caja' => 24,
        ]);
    }

    public function test_rechaza_precios_invalidos(): void
    {
        foreach (['0', '-1', 'texto', '10.555'] as $precio) {
            $this->actingAs($this->alicia)
                ->from(route('productos.create'))
                ->post(route('productos.store'), $this->datos(['precio' => $precio]))
                ->assertRedirect(route('productos.create'))
                ->assertSessionHasErrors('precio');
        }
    }

    public function test_rechaza_nombres_duplicados(): void
    {
        Producto::create($this->datos());

        $this->actingAs($this->alicia)
            ->from(route('productos.create'))
            ->post(route('productos.store'), $this->datos())
            ->assertRedirect(route('productos.create'))
            ->assertSessionHasErrors(['nombre' => 'Ya existe un producto con ese nombre.']);
    }

    public function test_desactiva_sin_eliminar_fisicamente(): void
    {
        $producto = Producto::create($this->datos());

        $this->actingAs($this->alicia)
            ->patch(route('productos.estado', $producto), ['activo' => '0'])
            ->assertRedirect(route('productos.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'activo' => 0]);
        $this->assertNotNull(Producto::find($producto->id));
    }

    public function test_cambiar_precio_no_modifica_el_precio_historico(): void
    {
        $producto = Producto::create($this->datos(['nombre' => 'Plan histórico', 'precio' => '2600.00']));
        $clienteId = DB::table('clientes')->insertGetId([
            'nombre' => 'Cliente histórico',
            'nit' => null,
            'telefono' => '55551234',
            'correo' => null,
            'vendedor_id' => $this->valeria->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $oportunidadId = DB::table('oportunidades')->insertGetId([
            'cliente_id' => $clienteId,
            'vendedor_id' => $this->valeria->id,
            'titulo' => 'Venta histórica',
            'etapa' => 'ganada',
            'motivo_perdida' => null,
            'fecha_cierre' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('oportunidad_productos')->insert([
            'oportunidad_id' => $oportunidadId,
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'precio_unitario' => '2600.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->alicia)
            ->put(route('productos.update', $producto), $this->datos([
                'nombre' => 'Plan histórico',
                'precio' => '2750.50',
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'precio' => '2750.50']);
        $this->assertDatabaseHas('oportunidad_productos', [
            'oportunidad_id' => $oportunidadId,
            'precio_unitario' => '2600.00',
        ]);
    }

    public function test_valida_unidades_por_caja(): void
    {
        foreach (['0', '1000', '2.5'] as $unidades) {
            $this->actingAs($this->alicia)
                ->from(route('productos.create'))
                ->post(route('productos.store'), $this->datos(['unidades_por_caja' => $unidades]))
                ->assertRedirect(route('productos.create'))
                ->assertSessionHasErrors('unidades_por_caja');
        }
    }

    public function test_solo_el_administrador_accede_al_catalogo(): void
    {
        $this->actingAs($this->sergio)->get(route('productos.index'))->assertForbidden();
        $this->actingAs($this->sergio)->post(route('productos.store'), $this->datos())->assertForbidden();

        $this->actingAs($this->valeria)->get(route('productos.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->actingAs($this->valeria)->post(route('productos.store'), $this->datos())
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_no_existe_eliminacion_fisica(): void
    {
        $producto = Producto::create($this->datos());

        $this->actingAs($this->alicia)
            ->delete('/productos/'.$producto->id)
            ->assertStatus(405);

        $this->assertNotNull($producto->fresh());
    }
}
