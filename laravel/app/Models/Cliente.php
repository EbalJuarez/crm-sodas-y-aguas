<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cliente (punto de venta). vendedor_id NULL = disponible (R3).
 */
#[Fillable(['vendedor_id', 'nombre', 'nit', 'telefono', 'correo'])]
class Cliente extends Model
{
    /** R3: cartera máxima de un vendedor (igual en la API). */
    public const MAXIMO_CARTERA = 10;

    protected $table = 'clientes';

    protected function casts(): array
    {
        return ['vendedor_id' => 'integer'];
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    /**
     * L5/S5 — única definición de visibilidad del panel: el administrador ve todos; un supervisor
     * ve los disponibles y los de su equipo (activos o no: D4). Cualquier otra cuenta, ninguno.
     * Se usa en las listas, en ClientePolicy (404 de lo ajeno) y en los bloqueos de asignar/liberar.
     */
    public function scopeVisiblesPara(Builder $consulta, User $usuario): void
    {
        if ($usuario->esAdministrador()) {
            return;
        }

        if (! $usuario->esSupervisor()) {
            $consulta->whereKey([]);

            return;
        }

        $consulta->where(fn (Builder $visibles) => $visibles
            ->whereNull('vendedor_id')
            ->orWhereIn('vendedor_id', User::query()->equipoDe($usuario)->select('id')));
    }

    /** Lo que no ve responde 404 (no 403), para no confirmar que existe (ClientePolicy). */
    public function esVisiblePara(User $usuario): bool
    {
        return static::query()->visiblesPara($usuario)->whereKey($this->getKey())->exists();
    }
}
