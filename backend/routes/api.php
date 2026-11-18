<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Todas las rutas se sirven bajo el prefijo /api (ej. GET /api/ping).
*/

Route::get('/ping', fn() => response()->json(['status' => 'ok']));

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        // Fuera de auth:api a propósito: debe aceptar un token expirado dentro de la ventana de refresco.
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:30,1');

        Route::middleware('auth:api')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });
});
