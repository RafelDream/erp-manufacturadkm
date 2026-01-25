<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\StockAdjustmentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\StockRequestController;
use App\Http\Controllers\Api\StockRequestApprovalController;
use App\Http\Controllers\Api\StockOutController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\StockInitialController;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', action: [AuthController::class, 'logout']);

        Route::apiResource('units', UnitController::class);
        Route::post('units/{id}/restore', [UnitController::class, 'restore']);

        Route::apiResource('products', ProductController::class);
        Route::post('products/{id}/restore', [ProductController::class, 'restore']);

        Route::apiResource('suppliers', SupplierController::class);
        Route::post('suppliers/{id}/restore', [SupplierController::class, 'restore']);

        Route::apiResource('warehouses', WarehouseController::class);
        Route::post('warehouses/{id}/restore', [WarehouseController::class, 'restore']);

        Route::apiResource('stock-requests', StockRequestController::class);
        Route::post('/stock-requests/{id}/restore', [StockRequestController::class, 'restore']);

        Route::prefix('stock-requests-approval/{id}')->group(function () {
            Route::post('approve', [StockRequestApprovalController::class, 'approve']);
            Route::post('reject', [StockRequestApprovalController::class, 'reject']);
        });

        Route::post('/stock-outs', [StockOutController::class, 'store']);
        Route::post('/initial-stocks', [StockInitialController::class, 'store']);

        Route::apiResource('stock-transfers', StockTransferController::class);
        Route::post('/stock-transfers/{id}/approve', [StockTransferController::class, 'approve']);
        Route::post('/stock-transfers/{id}/reject', [StockTransferController::class, 'reject']);
        Route::post('/stock-transfers/{id}/execute', [StockTransferController::class, 'execute']);

        Route::apiResource('stock-adjustments', StockAdjustmentController::class);
        Route::post('/stock-adjustments/{id}/approved', [StockAdjustmentController::class, 'approve']);
        Route::post('/stock-adjustments/{id}/restore', [StockAdjustmentController::class, 'restore']);

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