<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'precio', 'activo', 'unidades_por_caja'])]
class Producto extends Model
{
    protected $table = 'productos';

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'activo' => 'boolean',
            'unidades_por_caja' => 'integer',
        ];
    }
}
