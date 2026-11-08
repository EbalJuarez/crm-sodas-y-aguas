<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a ciertos roles: ->middleware('rol:administrador').
 * Es una función que el rol no tiene (no un dato ajeno), por eso responde 403 y no 404.
 */
class SoloRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        abort_if($usuario === null || ! in_array($usuario->rol, $roles, true), 403);

        return $next($request);
    }
}
