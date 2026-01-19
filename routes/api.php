<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', action: [AuthController::class, 'logout']);

        Route::apiResource('units', UnitController::class);
        Route::post('units/{id}/restore', [UnitController::class, 'restore']);

        Route::apiResource('products', ProductController::class);
        Route::post('products/{id}/restore', [ProductController::class, 'restore']);
    });
});

/*
|--------------------------------------------------------------------------
| SUPER ADMIN ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:super-admin'])->prefix('v1')->group(function () {
    Route::get('/admin/dashboard', function () {
        return response()->json(['message' => 'Super Admin Access OK']);
    });
    Route::apiResource('users', UserController::class);
    Route::post('users/{id}/restore', [UserController::class, 'restore']);
});