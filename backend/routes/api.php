<?php

use App\Http\Controllers\Auth\AccesoController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::get('estado', [AccesoController::class, 'estado']);
    Route::post('login', [AccesoController::class, 'login']);
    Route::get('configurar', [AccesoController::class, 'datosConfigurar']);
    Route::post('configurar', [AccesoController::class, 'configurar'])->middleware('throttle:10,1');
    Route::post('codigo', [AccesoController::class, 'codigo'])->middleware('throttle:10,1');
    Route::post('logout', [AccesoController::class, 'logout']);
});

// Todo lo de negocio va aquí: solo con sesión completa (contraseña + 2FA).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('yo', fn () => request()->user()->resumen());
});
