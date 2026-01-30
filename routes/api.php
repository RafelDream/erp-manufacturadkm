<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\WarehouseController;

use App\Http\Controllers\Api\StockOutController;
use App\Http\Controllers\Api\StockInitialController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\StockAdjustmentController;

use App\Http\Controllers\Api\StockRequestController;
use App\Http\Controllers\Api\StockRequestApprovalController;

use App\Http\Controllers\Api\RawMaterialController;
use App\Http\Controllers\Api\RawMaterialStockInController;
use App\Http\Controllers\Api\RawMaterialStockOutController;
use App\Http\Controllers\Api\RawMaterialStockAdjustmentController;

use App\Http\Controllers\Api\PurchaseRequestController;
use App\Http\Controllers\Api\PurchaseRequestItemController;
use App\Http\Controllers\Api\PurchaseOrderController;

/*
|--------------------------------------------------------------------------
| API V1
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);

        /*
        |--------------------------------------------------------------------------
        | MASTER DATA
        |--------------------------------------------------------------------------
        */
        Route::apiResource('units', UnitController::class);
        Route::post('units/{id}/restore', [UnitController::class, 'restore']);

        Route::apiResource('products', ProductController::class);
        Route::post('products/{id}/restore', [ProductController::class, 'restore']);

        Route::apiResource('suppliers', SupplierController::class);
        Route::post('suppliers/{id}/restore', [SupplierController::class, 'restore']);

        Route::apiResource('warehouses', WarehouseController::class);
        Route::post('warehouses/{id}/restore', [WarehouseController::class, 'restore']);

        Route::apiResource('raw-materials', RawMaterialController::class);
        Route::post('raw-materials/{id}/post', [RawMaterialController::class, 'post']);
        Route::post('raw-materials/{id}/restore', [RawMaterialController::class, 'restore']);

        /*
        |--------------------------------------------------------------------------
        | STOCK
        |--------------------------------------------------------------------------
        */
        Route::apiResource('stock-outs', StockOutController::class);
        Route::post('/stock-outs', [StockOutController::class, 'store']);
        Route::delete('/stock-outs/{id}', [StockOutController::class, 'destroy']);

        Route::post('/initial-stocks', [StockInitialController::class, 'store']);

        Route::apiResource('stock-transfers', StockTransferController::class);
        Route::post('stock-transfers/{id}/approve', [StockTransferController::class, 'approve']);
        Route::post('stock-transfers/{id}/reject', [StockTransferController::class, 'reject']);
        Route::post('stock-transfers/{id}/execute', [StockTransferController::class, 'execute']);

        Route::apiResource('stock-adjustments', StockAdjustmentController::class);
        Route::post('stock-adjustments/{id}/approved', [StockAdjustmentController::class, 'approve']);
        Route::post('stock-adjustments/{id}/restore', [StockAdjustmentController::class, 'restore']);

        /*
        |--------------------------------------------------------------------------
        | RAW MATERIAL STOCK
        |--------------------------------------------------------------------------
        */
        Route::apiResource('raw-material-stock-in', RawMaterialStockInController::class);
        Route::post('raw-material-stock-in/{id}/post', [RawMaterialStockInController::class, 'post']);
        Route::post('raw-material-stock-in/{id}/restore', [RawMaterialStockInController::class, 'restore']);

        Route::apiResource('raw-material-stock-out', RawMaterialStockOutController::class);
        Route::post('raw-material-stock-out/{id}/post', [RawMaterialStockOutController::class, 'post']);
        Route::post('raw-material-stock-out/{id}/restore', [RawMaterialStockOutController::class, 'restore']);

        Route::apiResource('raw-material-stock-adjustments', RawMaterialStockAdjustmentController::class);
        Route::post('/raw-material-stock-adjustments/{id}/post', [RawMaterialStockAdjustmentController::class, 'post']);
        Route::post('/raw-material-stock-adjustments/{id}/restore', [RawMaterialStockAdjustmentController::class, 'restore']);
        /*
        |--------------------------------------------------------------------------
        | STOCK REQUEST
        |--------------------------------------------------------------------------
        */
        Route::apiResource('stock-requests', StockRequestController::class);
        Route::post('stock-requests/{id}/restore', [StockRequestController::class, 'restore']);

        Route::prefix('stock-requests-approval/{id}')->group(function () {
            Route::post('approve', [StockRequestApprovalController::class, 'approve']);
            Route::post('reject', [StockRequestApprovalController::class, 'reject']);
        });

        /*
        |--------------------------------------------------------------------------
        | PURCHASE REQUEST (PR)
        |--------------------------------------------------------------------------
        */
        Route::apiResource('purchase-requests', PurchaseRequestController::class);
        Route::post('purchase-requests/{id}/submit', [PurchaseRequestController::class, 'submit']);
        Route::post('purchase-requests/{id}/approve', [PurchaseRequestController::class, 'approve']);
        Route::post('purchase-requests/{id}/reject', [PurchaseRequestController::class, 'reject']);
        Route::post('purchase-requests/{id}/restore', [PurchaseRequestController::class, 'restore']);

        Route::apiResource('purchase-request-items', PurchaseRequestItemController::class);
        
        /*
        |--------------------------------------------------------------------------
        | PURCHASE ORDER (PO)
        |--------------------------------------------------------------------------
        */



        /*
        |--------------------------------------------------------------------------
        | PURCHASE ORDER (PO)
        |--------------------------------------------------------------------------
        */
        Route::apiResource('purchase-orders', PurchaseOrderController::class);

        Route::post('purchase-orders/{id}/restore', [
            PurchaseOrderController::class,
            'restore'
        ]);

        Route::post('purchase-orders/{id}/submit', [
            PurchaseOrderController::class,
            'submit'
        ]);

        Route::post('purchase-orders/{id}/approve', [
            PurchaseOrderController::class,
            'approve'
        ]);

        Route::post('purchase-orders/{id}/reject', [
            PurchaseOrderController::class,
            'reject'
        ]);

        Route::post('purchase-orders/from-pr/{purchaseRequest}', [
            PurchaseOrderController::class,
            'generateFromPR'
        ]);

        Route::post('purchase-orders/item/{item}/price', [
            PurchaseOrderController::class,
            'updateItemPrice'
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| SUPER ADMIN
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:super-admin'])
    ->prefix('v1')
    ->group(function () {

        Route::get('/admin/dashboard', function () {
            return response()->json(['message' => 'Super Admin Access OK']);
        });

        Route::apiResource('users', UserController::class);
        Route::post('users/{id}/restore', [UserController::class, 'restore']);
    });
