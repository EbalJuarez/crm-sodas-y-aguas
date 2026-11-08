<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cliente (punto de venta). vendedor_id NULL = disponible (R3).
 * Por ahora solo se usa para saber si un vendedor tiene clientes asignados (D6);
 * la administración de clientes llega con L4.
 */
#[Fillable(['vendedor_id', 'nombre', 'nit', 'telefono', 'correo'])]
class Cliente extends Model
{
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
     * L5/S5: el administrador ve todos; un supervisor ve los disponibles y los de su equipo.
     * Lo que no ve responde 404 (no 403), para no confirmar que existe.
     */
    public function esVisiblePara(User $usuario): bool
    {
        if ($usuario->esAdministrador() || $this->vendedor_id === null) {
            return true;
        }

        return User::whereKey($this->vendedor_id)->where('supervisor_id', $usuario->id)->exists();
    }
}
