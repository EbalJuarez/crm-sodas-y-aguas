<?php

namespace App\Support;

/**
 * Formato de correo común al panel y a la API (L7.2). La misma expresión está en
 * api/src/normalizar.js y las dos se prueban con docs/casos-normalizacion.json.
 *
 * Parte local: «dot-atom» (letras, dígitos y los símbolos permitidos por la RFC, más letras y
 * marcas Unicode). Dominio: etiquetas de 1 a 62 caracteres, letras ASCII, dígitos y guiones
 * internos. Se usa junto a la regla `email` de Laravel: todo lo que acepta esta expresión también
 * lo acepta `email` (se comprobó al escribirla), así que el panel acepta exactamente lo mismo que la API.
 * Deja fuera las formas raras que `email` sí acepta: comillas, comentarios y [IP].
 */
final class Correo
{
    // `D`: el $ no acepta un salto de línea final (en JS, $ ya se comporta así).
    public const PATRON = "/^[A-Za-z0-9!#$%&'*+\\/=?^_`{|}~\\p{L}\\p{M}\\p{N}-]+(?:\\.[A-Za-z0-9!#$%&'*+\\/=?^_`{|}~\\p{L}\\p{M}\\p{N}-]+)*"
        .'@[A-Za-z0-9](?:[A-Za-z0-9-]{0,60}[A-Za-z0-9])?(?:\\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,60}[A-Za-z0-9])?)*$/uD';

    public static function esValido(?string $valor): bool
    {
        return is_string($valor) && preg_match(self::PATRON, $valor) === 1;
    }
}
