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

        return [
            'nombre' => [
                'required',
                'string',
                'max:120',
                Rule::unique('productos', 'nombre')->ignore($producto instanceof Producto ? $producto->id : null),
            ],
            'precio' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'unidades_por_caja' => ['nullable', 'integer', 'between:1,999'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escriba el nombre del producto.',
            'nombre.max' => 'El nombre puede tener hasta 120 caracteres.',
            'nombre.unique' => 'Ya existe un producto con ese nombre.',
            'precio.required' => 'Escriba el precio.',
            'precio.numeric' => 'Escriba un precio válido.',
            'precio.gt' => 'El precio debe ser mayor que cero.',
            'precio.decimal' => 'El precio puede tener como máximo dos decimales.',
            'unidades_por_caja.integer' => 'Las unidades por caja deben ser un número entero.',
            'unidades_por_caja.between' => 'Las unidades por caja deben estar entre 1 y 999.',
        ];
    }
}
