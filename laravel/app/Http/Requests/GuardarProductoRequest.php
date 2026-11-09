<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $producto = $this->route('producto');

        // `bail`: un solo mensaje por campo (L7.1). El máximo es el de DECIMAL(10,2):
        // sin él, un precio mayor llegaba a MySQL y respondía 500.
        return [
            'nombre' => [
                'bail',
                'required',
                'string',
                'max:120',
                Rule::unique('productos', 'nombre')->ignore($producto instanceof Producto ? $producto->id : null),
            ],
            'precio' => ['bail', 'required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'unidades_por_caja' => ['bail', 'nullable', 'integer', 'between:1,999'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escriba el nombre del producto.',
            'nombre.string' => 'Escriba el nombre del producto.',
            'nombre.max' => 'El nombre puede tener hasta 120 caracteres.',
            'nombre.unique' => 'Ya existe un producto con ese nombre.',
            'precio.required' => 'Escriba el precio.',
            'precio.numeric' => 'Escriba un precio válido.',
            'precio.gt' => 'El precio debe ser mayor que cero.',
            'precio.decimal' => 'Escriba el precio con dos decimales como máximo (por ejemplo, 1200.50).',
            'precio.max' => 'El precio no puede ser mayor que Q99,999,999.99.',
            'unidades_por_caja.integer' => 'Las unidades por caja deben ser un número entero.',
            'unidades_por_caja.between' => 'Las unidades por caja deben estar entre 1 y 999.',
        ];
    }
}
