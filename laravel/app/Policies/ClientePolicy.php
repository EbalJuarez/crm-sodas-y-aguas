<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * L5 / S5: un cliente que el usuario no ve (de otro equipo) responde 404, no 403, al ver, editar,
 * asignar, reasignar o liberar: la respuesta no confirma que exista (R2).
 *
 * Se aplica con el middleware `can:gestionar,cliente` en las rutas. El middleware corre antes de
 * resolver el FormRequest del controlador, así que el 404 llega ANTES de validar el cuerpo.
 */
class ClientePolicy
{
    public function gestionar(User $usuario, Cliente $cliente): Response
    {
        return $cliente->esVisiblePara($usuario)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
