<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * L2.1: no hay registro público. Las cuentas las crea el administrador en el panel.
 */
class RegistrationTest extends TestCase
{
    public function test_la_pagina_de_registro_no_existe(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_no_se_puede_enviar_un_registro(): void
    {
        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@crm.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }

    public function test_no_existe_la_ruta_con_nombre_register(): void
    {
        $this->assertFalse(Route::has('register'));
    }

    public function test_el_login_no_enlaza_a_un_registro(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('/register');
    }
}
