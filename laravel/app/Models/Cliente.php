<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cliente (punto de venta). vendedor_id NULL = disponible (R3).
 * Por ahora solo se usa para saber si un vendedor tiene clientes asignados (D6);
 * la administración de clientes llega con L4.
 */
class Cliente extends Model
{
    protected $table = 'clientes';

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }
}
