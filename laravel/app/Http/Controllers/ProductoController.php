<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarProductoRequest;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductoController extends Controller
{
    public function index(): View
    {
        return view('productos.index', [
            'productos' => Producto::query()->orderBy('nombre')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('productos.create', ['producto' => new Producto]);
    }

    public function store(GuardarProductoRequest $request): RedirectResponse
    {
        $producto = new Producto;
        $this->asignar($producto, $request->validated());
        $producto->save();

        return redirect()->route('productos.index')->with('status', 'Producto creado.');
    }

    public function edit(Producto $producto): View
    {
        return view('productos.edit', ['producto' => $producto]);
    }

    public function update(GuardarProductoRequest $request, Producto $producto): RedirectResponse
    {
        $this->asignar($producto, $request->validated());
        $producto->save();

        return redirect()->route('productos.index')->with('status', 'Producto actualizado.');
    }

    public function cambiarEstado(Request $request, Producto $producto): RedirectResponse
    {
        $datos = $request->validate(
            ['activo' => ['required', 'boolean']],
            [
                'activo.required' => 'Indique el estado del producto.',
                'activo.boolean' => 'Indique un estado válido para el producto.',
            ],
        );

        $producto->activo = (bool) $datos['activo'];
        $producto->save();

        return redirect()->route('productos.index')->with('status', $producto->activo
            ? 'Producto activado.'
            : 'Producto desactivado.');
    }

    private function asignar(Producto $producto, array $datos): void
    {
        $producto->nombre = $datos['nombre'];
        $producto->precio = $datos['precio'];
        $producto->unidades_por_caja = $datos['unidades_por_caja'] ?? null;
    }
}
