<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EntradaController;
use App\Http\Controllers\Api\LibroController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\PrestamoController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\ReservaLibroController;
use App\Http\Controllers\Api\SalaController;
use App\Http\Controllers\Api\UsuarioAuthController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/auth/usuario/login', [UsuarioAuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware(['auth:sanctum', 'staff'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard/resumen', [DashboardController::class, 'resumen']);

    Route::get('/salas', [SalaController::class, 'index']);
    Route::post('/reservas', [SalaController::class, 'storeReserva']);
    Route::delete('/reservas/{reserva}', [SalaController::class, 'destroyReserva']);

    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::get('/usuarios/rut/{rut}', [UsuarioController::class, 'porRut']);

    Route::get('/entrada', [EntradaController::class, 'index']);
    Route::post('/entrada', [EntradaController::class, 'store']);

    Route::get('/prestamos', [PrestamoController::class, 'index']);
    Route::post('/prestamos', [PrestamoController::class, 'store']);
    Route::patch('/prestamos/{prestamo}/devolver', [PrestamoController::class, 'devolver']);

    Route::get('/libros/{codigo}', [LibroController::class, 'buscarPorCodigo']);

    Route::get('/reservas-libro', [ReservaLibroController::class, 'index']);
    Route::post('/reservas-libro', [ReservaLibroController::class, 'store']);
    Route::patch('/reservas-libro/{reservaLibro}/cancelar', [ReservaLibroController::class, 'cancelar']);

    Route::get('/reportes/opciones', [ReporteController::class, 'opciones']);
    Route::get('/reportes/resumen', [ReporteController::class, 'resumen']);
});

Route::middleware(['auth:sanctum', 'usuario'])->group(function () {
    Route::get('/auth/usuario/me', [UsuarioAuthController::class, 'me']);
    Route::post('/auth/usuario/logout', [UsuarioAuthController::class, 'logout']);

    Route::get('/mi/estado', [PortalController::class, 'estado']);
    Route::post('/mi/entrada', [PortalController::class, 'registrarEntrada']);
    Route::get('/mi/catalogo', [PortalController::class, 'catalogo']);
});
