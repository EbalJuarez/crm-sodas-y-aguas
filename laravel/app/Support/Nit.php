<?php

namespace App\Support;

final class Nit
{
    public static function compactar(?string $valor): string
    {
        $compacto = strtoupper((string) $valor);
        $compacto = preg_replace('/[\s-]+/u', '', $compacto) ?? '';

        return ltrim($compacto, '0');
    }

    public static function esValido(?string $valor): bool
    {
        $nit = self::compactar($valor);

        if ($nit === 'CF') {
            return true;
        }

        if (! preg_match('/^[0-9]{1,11}[0-9K]$/', $nit)) {
            return false;
        }

        $digito = substr($nit, -1);
        $cuerpo = substr($nit, 0, -1);
        $suma = 0;
        $factor = 2;

        for ($indice = strlen($cuerpo) - 1; $indice >= 0; $indice--) {
            $suma += ((int) $cuerpo[$indice]) * $factor;
            $factor++;
        }

        $esperado = (11 - ($suma % 11)) % 11;
        $esperado = $esperado === 10 ? 'K' : (string) $esperado;

        return $digito === $esperado;
    }

    public static function normalizar(?string $valor): ?string
    {
        if (! self::esValido($valor)) {
            return null;
        }

        $nit = self::compactar($valor);

        return $nit === 'CF' ? null : $nit;
    }
}
