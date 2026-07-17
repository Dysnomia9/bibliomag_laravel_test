<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CodigoAccesoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EntradaController;
use App\Http\Controllers\Api\LibroController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\PrestamoController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\ReservaLibroController;
use App\Http\Controllers\Api\SalaController;
use App\Http\Controllers\Api\StaffController;
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
    Route::post('/salas/scan-logia', [SalaController::class, 'scanLogia']);
    Route::post('/reservas', [SalaController::class, 'storeReserva']);
    Route::delete('/reservas/{reserva}', [SalaController::class, 'destroyReserva']);
    Route::patch('/reservas/{reserva}/devolver', [SalaController::class, 'devolverReserva']);

    Route::get('/staff', [StaffController::class, 'index']);

    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::get('/usuarios/rut/{rut}', [UsuarioController::class, 'porRut']);

    Route::get('/entrada', [EntradaController::class, 'index']);
    Route::post('/entrada', [EntradaController::class, 'store']);
    Route::post('/entrada/externo', [EntradaController::class, 'storeExterno']);
    Route::post('/entrada/convenio', [EntradaController::class, 'storeConvenio']);

    Route::get('/prestamos', [PrestamoController::class, 'index']);
    Route::post('/prestamos', [PrestamoController::class, 'store']);
    Route::patch('/prestamos/{prestamo}/devolver', [PrestamoController::class, 'devolver']);
    Route::patch('/prestamos/{prestamo}/multa/pagar', [PrestamoController::class, 'pagarMulta']);

    Route::get('/libros', [LibroController::class, 'index']);
    Route::get('/libros/{codigo}', [LibroController::class, 'buscarPorCodigo']);
    Route::patch('/libros/{libro}/estado', [LibroController::class, 'cambiarEstado']);

    Route::middleware('admin')->group(function () {
        Route::post('/libros', [LibroController::class, 'store']);
        Route::patch('/libros/{libro}', [LibroController::class, 'update']);
    });

    Route::get('/reservas-libro', [ReservaLibroController::class, 'index']);
    Route::post('/reservas-libro', [ReservaLibroController::class, 'store']);
    Route::patch('/reservas-libro/{reservaLibro}/cancelar', [ReservaLibroController::class, 'cancelar']);

    Route::get('/reportes/opciones', [ReporteController::class, 'opciones']);
    Route::get('/reportes/resumen', [ReporteController::class, 'resumen']);

    Route::get('/codigo-acceso', [CodigoAccesoController::class, 'show']);
    Route::post('/codigo-acceso/regenerar', [CodigoAccesoController::class, 'regenerar']);
});

Route::middleware(['auth:sanctum', 'usuario'])->group(function () {
    Route::get('/auth/usuario/me', [UsuarioAuthController::class, 'me']);
    Route::post('/auth/usuario/logout', [UsuarioAuthController::class, 'logout']);

    Route::get('/mi/estado', [PortalController::class, 'estado']);
    Route::post('/mi/entrada', [PortalController::class, 'registrarEntrada']);
    Route::get('/mi/catalogo', [PortalController::class, 'catalogo']);

    Route::get('/mi/salas', [PortalController::class, 'salas']);
    Route::post('/mi/reservas', [PortalController::class, 'reservarSala']);
    Route::delete('/mi/reservas/{reserva}', [PortalController::class, 'cancelarReservaSala']);
});
