<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * R1 / L2.9: una cuenta desactivada, o que ya no es de administrador o supervisor, pierde la sesión
 * en su siguiente petición.
 *
 * El guard de sesión de Laravel vuelve a leer al usuario de la base en cada petición (por su id),
 * así que $request->user() ya trae el rol y el estado actuales: si el administrador lo desactivó
 * hace un segundo, aquí se ve.
 */
class CuentaDelPanel
{
    public const MENSAJE_DESACTIVADA = 'Su cuenta está desactivada. Comuníquese con el administrador.';

    public const MENSAJE_VENDEDOR = 'Su cuenta es de vendedor. Ingrese desde la aplicación de ventas.';

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario !== null && ! $usuario->puedeEntrarAlPanel()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $mensaje = $usuario->activo ? self::MENSAJE_VENDEDOR : self::MENSAJE_DESACTIVADA;

            return redirect()->route('login')->withErrors(['email' => $mensaje]);
        }

        return $next($request);
    }
}
