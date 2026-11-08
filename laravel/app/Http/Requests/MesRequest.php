<?php

namespace App\Http\Requests;

use App\Support\ZonaGuatemala;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ?mes=AAAA-MM del reporte del mes y de «Mi equipo». Un mes inválido (2026-13, octubre, un
 * arreglo) vuelve a la misma página, sin ?mes (mes actual), con el mensaje junto al selector:
 * nunca un 500.
 */
class MesRequest extends FormRequest
{
    public const MENSAJE = 'Elija un mes válido con el formato AAAA-MM (por ejemplo, 2026-10).';

    public function rules(): array
    {
        return [
            'mes' => ['bail', 'nullable', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! ZonaGuatemala::esMesValido($value)) {
                    $fail(self::MENSAJE);
                }
            }],
        ];
    }

    public function messages(): array
    {
        return ['mes.string' => self::MENSAJE];
    }

    /** La misma página sin la cadena de consulta (mes actual). */
    protected function getRedirectUrl(): string
    {
        return $this->url();
    }

    /** El mes elegido, o el mes actual en hora de Guatemala. */
    public function mes(): string
    {
        return $this->validated('mes') ?? ZonaGuatemala::mesActual();
    }
}
