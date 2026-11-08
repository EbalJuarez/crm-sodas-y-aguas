<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de cuentas por el administrador (L2.2–L2.6, L2.12–L2.14).
 * La ruta ya exige rol administrador; aquí solo se validan los datos.
 */
class GuardarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** La cuenta que se edita, o null si es un alta. */
    private function usuarioEditado(): ?User
    {
        $usuario = $this->route('usuario');

        return $usuario instanceof User ? $usuario : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $editado = $this->usuarioEditado();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editado?->id)],
            'password' => [$editado ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'rol' => ['required', Rule::in(User::ROLES)],
            // Solo los vendedores tienen supervisor (L2.4): para otros roles el campo se descarta.
            // Debe ser un supervisor ACTIVO (L2.3); esto cruza filas, por eso no está en el CHECK.
            'supervisor_id' => [
                'exclude_unless:rol,vendedor',
                'required',
                'integer',
                Rule::notIn(array_filter([$editado?->id])),
                Rule::exists('users', 'id')->where('rol', 'supervisor')->where('activo', true),
            ],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $editado = $this->usuarioEditado();
                if ($editado === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                // L2.6: el administrador no se desactiva ni se quita el rol a sí mismo.
                if ($editado->is($this->user())) {
                    if (! $this->boolean('activo')) {
                        $validator->errors()->add('activo', 'No puede desactivar su propia cuenta.');
                    }
                    if ($this->input('rol') !== 'administrador') {
                        $validator->errors()->add('rol', 'No puede quitarse el rol de administrador.');
                    }
                }

                // D6: cambiar el rol se bloquea si tiene equipo o cartera.
                $motivo = $editado->motivoParaNoCambiarRol((string) $this->input('rol'));
                if ($motivo !== null) {
                    $validator->errors()->add('rol', $motivo);
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Escriba el nombre.',
            'name.max' => 'El nombre puede tener hasta 255 caracteres.',
            'email.required' => 'Escriba el correo.',
            'email.email' => 'Escriba un correo válido.',
            'email.max' => 'El correo puede tener hasta 255 caracteres.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.required' => 'Escriba una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación no coincide con la contraseña.',
            'rol.required' => 'Elija un rol.',
            'rol.in' => 'Elija administrador, supervisor o vendedor.',
            'supervisor_id.required' => 'Elija el supervisor del vendedor.',
            'supervisor_id.integer' => 'Elija un supervisor de la lista.',
            'supervisor_id.not_in' => 'Una cuenta no puede ser su propio supervisor.',
            'supervisor_id.exists' => 'El supervisor debe ser una cuenta de supervisor activa.',
            'activo.required' => 'Indique si la cuenta está activa.',
            'activo.boolean' => 'Indique si la cuenta está activa.',
        ];
    }
}
