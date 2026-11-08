<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\OportunidadController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard'); // Sin 'verified': las cuentas las crea el administrador.

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

// L2: cuentas, solo para el administrador. Sin show ni destroy (las cuentas no se borran).
Route::middleware(['auth', 'rol:administrador'])->group(function () {
    Route::resource('usuarios', UsuarioController::class)
        ->except(['show', 'destroy'])
        ->parameters(['usuarios' => 'usuario']);

    Route::resource('productos', ProductoController::class)->except(['show', 'destroy']);
    Route::patch('/productos/{producto}/estado', [ProductoController::class, 'cambiarEstado'])
        ->name('productos.estado');

    // L4 (enunciado): solo el administrador elimina clientes; un supervisor recibe 403.
    Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');
});

Route::middleware(['auth', 'rol:administrador,supervisor'])->group(function () {
    Route::resource('clientes', ClienteController::class)->except(['show', 'destroy']);
    Route::patch('/clientes/{cliente}/asignar', [ClienteController::class, 'asignar'])
        ->name('clientes.asignar');
    Route::patch('/clientes/{cliente}/liberar', [ClienteController::class, 'liberar'])
        ->name('clientes.liberar');

    // L6: solo lectura (L6.3). Supervisor = su equipo; administrador = toda la empresa.
    Route::get('/oportunidades', [OportunidadController::class, 'index'])->name('oportunidades.index');
    Route::get('/reportes/mes', [ReporteController::class, 'mes'])->name('reportes.mes');
});

require __DIR__.'/auth.php';
