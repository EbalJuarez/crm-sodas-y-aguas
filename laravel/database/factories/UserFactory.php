<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * Por omisión, un administrador activo: el CHECK users_supervisor_segun_rol exige supervisor
     * a los vendedores, y el rol por omisión de la columna (vendedor) no lo tendría.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'rol' => 'administrador',
            'supervisor_id' => null,
            'activo' => true,
        ];
    }

    /**
     * Un supervisor activo, sin supervisor propio.
     */
    public function supervisor(): static
    {
        return $this->state(fn (array $attributes) => [
            'rol' => 'supervisor',
            'supervisor_id' => null,
        ]);
    }

    /**
     * Un vendedor del equipo de $supervisor (se crea uno si no se pasa).
     */
    public function vendedor(?User $supervisor = null): static
    {
        return $this->state(fn (array $attributes) => [
            'rol' => 'vendedor',
            'supervisor_id' => $supervisor?->id ?? User::factory()->supervisor(),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
