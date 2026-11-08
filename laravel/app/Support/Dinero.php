<?php

namespace App\Support;

/**
 * Formato de quetzales para mostrar: «Q9,250.00». Trabaja sobre el texto DECIMAL que devuelve
 * la base; no convierte a float ni hace cuentas (los totales los calcula SQL).
 */
final class Dinero
{
    public static function formatear(string|int|null $monto): string
    {
        $texto = (string) ($monto ?? '0');
        $negativo = str_starts_with($texto, '-');
        [$entero, $decimales] = array_pad(explode('.', ltrim($texto, '-'), 2), 2, '');

        $entero = ltrim($entero, '0') === '' ? '0' : ltrim($entero, '0');
        $conComas = strrev(implode(',', str_split(strrev($entero), 3)));
        $decimales = str_pad(substr($decimales, 0, 2), 2, '0');

        return ($negativo ? '-' : '').'Q'.$conComas.'.'.$decimales;
    }
}
