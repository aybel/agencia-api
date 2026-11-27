<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Todas las rutas se sirven bajo el prefijo /api (ej. GET /api/ping).
*/

Route::prefix('v1')->group(function () {
    Route::get('/ping', fn () => response()->json(['status' => 'ok Acceso a la API correcto']));

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        // Fuera de auth:api a propósito: debe aceptar un token expirado dentro de la ventana de refresco.
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:30,1');

        Route::middleware('auth:api')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            // Rutas para regresar el menu
            // mediante el permiso del usuario
            Route::get('menu', [MenuController::class, 'getUserMenu']);
            Route::post('logout', [AuthController::class, 'logout']);

            // Rutas para administrar sucursales
            Route::prefix('branches')->group(function () {
                Route::get('/filter', [BranchController::class, 'filter'])->middleware('permission:branches.view_any');
                Route::post('/filter', [BranchController::class, 'filter'])->middleware('permission:cars.view_any');
                Route::post('/', [BranchController::class, 'store'])->middleware('permission:branches.create');
                Route::put('/{id}', [BranchController::class, 'update'])->middleware('permission:branches.update');
                Route::delete('/{id}', [BranchController::class, 'destroy'])->middleware('permission:branches.delete');
            });
        });
    });
});
