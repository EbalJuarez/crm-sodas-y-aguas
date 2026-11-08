<?php

namespace App\Http\Requests\Auth;

use App\Http\Middleware\CuentaDelPanel;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Escriba su correo.',
            'email.email' => 'Escriba un correo válido.',
            'password.required' => 'Escriba su contraseña.',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * R1: primero se validan las credenciales SIN abrir sesión; solo una cuenta activa de
     * administrador o supervisor llega a Auth::login().
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::validate($this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Estas credenciales no coinciden con nuestros registros.',
            ]);
        }

        /** @var User $usuario */
        $usuario = Auth::getLastAttempted();

        if (! $usuario->activo) {
            throw ValidationException::withMessages(['email' => CuentaDelPanel::MENSAJE_DESACTIVADA]);
        }

        if (! $usuario->puedeEntrarAlPanel()) {
            throw ValidationException::withMessages(['email' => CuentaDelPanel::MENSAJE_VENDEDOR]);
        }

        Auth::login($usuario, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Demasiados intentos. Vuelva a intentarlo en {$seconds} segundos.",
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
