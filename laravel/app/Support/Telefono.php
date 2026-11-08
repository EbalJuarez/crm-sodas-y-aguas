<?php

namespace App\Support;

final class Telefono
{
    public static function normalizar(?string $valor): ?string
    {
        $telefono = preg_replace('/[\s.()\-]+/u', '', (string) $valor) ?? '';

        if (str_starts_with($telefono, '+502')) {
            $telefono = substr($telefono, 4);
        } elseif (preg_match('/^502[0-9]{8}$/', $telefono)) {
            $telefono = substr($telefono, 3);
        }

        return preg_match('/^[2-9][0-9]{7}$/', $telefono) ? $telefono : null;
    }

    public static function esValido(?string $valor): bool
    {
        return self::normalizar($valor) !== null;
    }
}
