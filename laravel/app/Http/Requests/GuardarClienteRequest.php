<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Support\Correo;
use App\Support\Nit;
use App\Support\Telefono;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarClienteRequest extends FormRequest
{
    /**
     * Se ejecuta antes de validar: un cliente de otro equipo responde 404 aunque los datos
     * sean inválidos (si no, el 302 con errores confirmaría que existe: R2, S5).
     */
    public function authorize(): bool
    {
        $cliente = $this->route('cliente');

        if ($cliente instanceof Cliente) {
            abort_unless($cliente->esVisiblePara($this->user()), 404);
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        // Solo se normalizan textos; cualquier otro tipo (p. ej. un arreglo) lo rechaza la regla `string`.
        $datos = [];
        $nombre = $this->input('nombre');
        $nit = $this->input('nit');
        $telefono = $this->input('telefono');

        if (is_string($nombre)) {
            $datos['nombre'] = trim($nombre);
        }

        if (is_string($nit) && Nit::esValido($nit)) {
            $datos['nit'] = strtoupper(Nit::compactar($nit));
        }

        if (is_string($telefono) && Telefono::esValido($telefono)) {
            $datos['telefono'] = Telefono::normalizar($telefono);
        }

        $this->merge($datos);
    }

    /**
     * Alta y edición exigen los mismos campos (spec 3.4: nombre, NIT y teléfono obligatorios);
     * el formulario del panel siempre envía los cuatro. `bail`: un solo mensaje por campo (L7.1).
     */
    public function rules(): array
    {
        $cliente = $this->route('cliente');
        $ignorar = $cliente instanceof Cliente ? $cliente->id : null;

        return [
            'nombre' => ['bail', 'required', 'string', 'max:150'],
            'nit' => ['bail', 'required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! Nit::esValido($value)) {
                    $fail('Escriba un NIT válido (con su dígito verificador) o CF.');
                }
            }, Rule::unique('clientes', 'nit')->ignore($ignorar)],
            'telefono' => ['bail', 'required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! Telefono::esValido($value)) {
                    $fail('Escriba un teléfono de Guatemala de 8 dígitos.');
                }
            }, Rule::unique('clientes', 'telefono')->ignore($ignorar)],
            // `email` + la expresión común con la API (Correo::PATRON): las dos puertas aceptan lo mismo (L7.2).
            'correo' => ['bail', 'nullable', 'string', 'email', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! Correo::esValido($value)) {
                    $fail('Escriba un correo válido.');
                }
            }, 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escriba el nombre del cliente.',
            'nombre.string' => 'Escriba el nombre del cliente.',
            'nombre.max' => 'El nombre puede tener hasta 150 caracteres.',
            'nit.required' => 'Escriba un NIT válido (con su dígito verificador) o CF.',
            'nit.string' => 'Escriba un NIT válido (con su dígito verificador) o CF.',
            'nit.unique' => 'Ya existe un cliente con ese NIT.',
            'telefono.required' => 'Escriba un teléfono de Guatemala de 8 dígitos.',
            'telefono.string' => 'Escriba un teléfono de Guatemala de 8 dígitos.',
            'telefono.unique' => 'Ya existe un cliente con ese teléfono.',
            'correo.string' => 'Escriba un correo válido.',
            'correo.email' => 'Escriba un correo válido.',
            'correo.max' => 'El correo puede tener hasta 150 caracteres.',
        ];
    }
}
