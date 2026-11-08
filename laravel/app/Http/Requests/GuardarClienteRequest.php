<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Support\Nit;
use App\Support\Telefono;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GuardarClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $datos = [];

        if ($this->exists('nombre')) {
            $datos['nombre'] = trim((string) $this->input('nombre'));
        }

        if ($this->exists('nit') && Nit::esValido($this->input('nit'))) {
            $datos['nit'] = strtoupper(Nit::compactar($this->input('nit')));
        }

        if ($this->exists('telefono') && Telefono::esValido($this->input('telefono'))) {
            $datos['telefono'] = Telefono::normalizar($this->input('telefono'));
        }

        $this->merge($datos);
    }

    public function rules(): array
    {
        $cliente = $this->route('cliente');
        $esEdicion = $cliente instanceof Cliente;
        $presencia = $esEdicion ? 'sometimes' : 'required';

        return [
            'nombre' => [$presencia, 'string', 'max:150'],
            'nit' => [$presencia, 'string', 'max:12', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! Nit::esValido($value)) {
                    $fail('Escriba un NIT válido (con su dígito verificador) o CF.');
                }
            }, Rule::unique('clientes', 'nit')->ignore($cliente?->id)],
            'telefono' => [$presencia, 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! Telefono::esValido($value)) {
                    $fail('Escriba un teléfono de Guatemala de 8 dígitos.');
                }
            }, Rule::unique('clientes', 'telefono')->ignore($cliente?->id)],
            'correo' => ['sometimes', 'nullable', 'email', 'max:150'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->exists('nit') && ! Nit::esValido($this->input('nit'))) {
                $validator->errors()->add('nit', 'Escriba un NIT válido (con su dígito verificador) o CF.');
            }

            if ($this->exists('telefono') && ! Telefono::esValido($this->input('telefono'))) {
                $validator->errors()->add('telefono', 'Escriba un teléfono de Guatemala de 8 dígitos.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escriba el nombre del cliente.',
            'nombre.max' => 'El nombre puede tener hasta 150 caracteres.',
            'nit.required' => 'Escriba un NIT válido (con su dígito verificador) o CF.',
            'nit.max' => 'El NIT puede tener hasta 12 caracteres.',
            'nit.unique' => 'Ya existe un cliente con ese NIT.',
            'telefono.required' => 'Escriba un teléfono de Guatemala de 8 dígitos.',
            'telefono.unique' => 'Ya existe un cliente con ese teléfono.',
            'correo.email' => 'Escriba un correo válido.',
            'correo.max' => 'El correo puede tener hasta 150 caracteres.',
        ];
    }
}
