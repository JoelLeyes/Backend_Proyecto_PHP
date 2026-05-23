<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Servicios Pro Backend',
    ]);
});

Route::prefix('auth')->group(function () {
    Route::get('{provider}/redirect', [AuthController::class, 'redirigirOAuth']);
    Route::get('{provider}/callback', [AuthController::class, 'manejarCallbackOAuth']);
});
