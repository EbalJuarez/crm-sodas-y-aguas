<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// rol, supervisor_id y activo no son asignables en masa: solo los asigna, campo por campo,
// la administración de cuentas (UsuarioController), nunca el perfil.
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = ['administrador', 'supervisor', 'vendedor'];

    /** Roles que entran por el panel (R1). El vendedor entra por la SPA. */
    public const ROLES_DEL_PANEL = ['administrador', 'supervisor'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /** Vendedores a cargo de este supervisor (su equipo). */
    public function vendedores(): HasMany
    {
        return $this->hasMany(User::class, 'supervisor_id');
    }

    /** Clientes asignados a este vendedor (su cartera). */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'vendedor_id');
    }

    public function esAdministrador(): bool
    {
        return $this->rol === 'administrador';
    }

    public function esSupervisor(): bool
    {
        return $this->rol === 'supervisor';
    }

    public function esVendedor(): bool
    {
        return $this->rol === 'vendedor';
    }

    /**
     * D6: un supervisor con vendedores o un vendedor con clientes no cambia de rol hasta que se
     * reasignen. Devuelve el mensaje para el usuario, o null si el cambio se permite.
     * Desactivar no pasa por aquí: siempre se permite (D4).
     */
    public function motivoParaNoCambiarRol(string $rolNuevo): ?string
    {
        if ($rolNuevo === $this->rol) {
            return null;
        }

        if ($this->esSupervisor() && $this->vendedores()->exists()) {
            return 'Este supervisor tiene vendedores a su cargo. Reasigne primero a sus vendedores a otro supervisor.';
        }

        if ($this->esVendedor() && $this->clientes()->exists()) {
            return 'Esta cuenta tiene clientes asignados. Reasigne primero sus clientes.';
        }

        return null;
    }

    /** R1: entra al panel solo una cuenta activa de administrador o supervisor. */
    public function puedeEntrarAlPanel(): bool
    {
        return $this->activo && in_array($this->rol, self::ROLES_DEL_PANEL, true);
    }
}
