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
use App\Http\Controllers\Api\StockMovementController;

use App\Http\Controllers\Api\RawMaterialController;
use App\Http\Controllers\Api\RawMaterialStockInController;
use App\Http\Controllers\Api\RawMaterialStockOutController;
use App\Http\Controllers\Api\RawMaterialStockAdjustmentController;

use App\Http\Controllers\Api\PurchaseRequestController;
use App\Http\Controllers\Api\PurchaseRequestItemController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\PurchaseReturnController;
use App\Http\Controllers\Api\SupplierPurchaseReportController;
use App\Http\Controllers\Api\InvoiceReceiptController;
use App\Http\Controllers\Api\InvoiceReceiptReportController;
use App\Http\Controllers\Api\PurchaseReturnReportController;

use App\Http\Controllers\Api\InventoryReportController;

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

        Route::apiResource('initial-stocks', StockInitialController::class);

        Route::apiResource('stock-transfers', StockTransferController::class);
        Route::post('stock-transfers/{id}/approve', [StockTransferController::class, 'approve']);
        Route::post('stock-transfers/{id}/reject', [StockTransferController::class, 'reject']);
        Route::post('stock-transfers/{id}/execute', [StockTransferController::class, 'execute']);

        Route::apiResource('stock-adjustments', StockAdjustmentController::class);
        Route::post('stock-adjustments/{id}/approve', [StockAdjustmentController::class, 'approve']);
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
        Route::post('raw-material-stock-adjustments/{id}/post', [RawMaterialStockAdjustmentController::class, 'post']);
        Route::post('raw-material-stock-adjustments/{id}/restore', [RawMaterialStockAdjustmentController::class, 'restore']);

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
        | Stock Movement Product & Raw Material
        |--------------------------------------------------------------------------
        */
        Route::get('/stock-tracking', [StockMovementController::class, 'index']);
        Route::get('/stock-summary', [StockMovementController::class, 'getStockSummary']);
        Route::post('/stock-movements', [StockMovementController::class, 'store']);

         /*
        |--------------------------------------------------------------------------
        | Kartu Persediaan
        |--------------------------------------------------------------------------
        */

        Route::get('/inventory/products', [InventoryReportController::class, 'product']);
        Route::get('/inventory/raw-materials', [InventoryReportController::class, 'rawMaterial']);
        Route::get('/inventory/incoming-report', [InventoryReportController::class, 'incomingGoodslog']);
        Route::get('/inventory/outgoing-report', [InventoryReportController::class, 'outgoingGoodslog']);


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

        Route::post('purchase-request-items', [PurchaseRequestItemController::class, 'store']);
        Route::delete('purchase-request-items/{id}', [PurchaseRequestItemController::class, 'destroy']);

        /*
        |--------------------------------------------------------------------------
        | PURCHASE ORDER (PO)
        |--------------------------------------------------------------------------
        */
        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        
        Route::post('purchase-orders/from-pr/{purchaseRequest}', [PurchaseOrderController::class,'generateFromPR']);
        Route::post('purchase-orders/item/{item}/price', [PurchaseOrderController::class,'updateItemPrice']);
        Route::post('purchase-orders/{id}/submit', [PurchaseOrderController::class,'submit']);
        Route::post('purchase-orders/{id}/receive', [PurchaseOrderController::class,'receive']);
        Route::post('purchase-orders/{id}/restore', [PurchaseOrderController::class,'restore']);
        /*
        |--------------------------------------------------------------------------
        | PURCHASE ORDER (PO)
        |--------------------------------------------------------------------------
        */
        Route::apiResource('goods-receipts', GoodsReceiptController::class);
        Route::post('goods-receipts/{id}/post', [GoodsReceiptController::class, 'post']);
        Route::post('goods-receipts/{id}/restore', [GoodsReceiptController::class, 'restore']);

        /*
        |--------------------------------------------------------------------------
        | RETUR PEMBELIAN 
        |--------------------------------------------------------------------------
        */
        // List & Detail
        Route::get('purchase-returns', [PurchaseReturnController::class, 'index']);
        Route::get('purchase-returns/{id}', [PurchaseReturnController::class, 'show']);
        // CRUD
        Route::post('purchase-returns', [PurchaseReturnController::class, 'store']);
        Route::put('purchase-returns/{id}', [PurchaseReturnController::class, 'update']);
        Route::delete('purchase-returns/{id}', [PurchaseReturnController::class, 'destroy']);
        Route::post('purchase-returns/{id}/restore', [PurchaseReturnController::class, 'restore']);

        // Additional Actions
        Route::post('purchase-returns/{id}/submit', [PurchaseReturnController::class, 'submit']);
        Route::post('purchase-returns/{id}/approve', [PurchaseReturnController::class, 'approve']);
        Route::post('purchase-returns/{id}/reject', [PurchaseReturnController::class, 'reject']);
        Route::post('purchase-returns/{id}/realize', [PurchaseReturnController::class, 'realize']);
        Route::post('purchase-returns/{id}/complete', [PurchaseReturnController::class, 'complete']);

        // Helper Endpoints
        Route::get('/purchase-returns-helpers/returnable-pos', [PurchaseReturnController::class, 'getReturnablePurchaseOrders']);
        Route::get('/purchase-returns-helpers/returnable-items/{goodsReceiptId}', [PurchaseReturnController::class, 'getReturnableItems']);

        /*
        |--------------------------------------------------------------------------
        | Invoicr RECEIPTS 
        |--------------------------------------------------------------------------
        */
        
        // List & Detail
        Route::get('/invoice-receipts', [InvoiceReceiptController::class, 'index']);
        Route::get('/invoice-receipts/{id}', [InvoiceReceiptController::class, 'show']);
        
        // CRUD Operations
        Route::post('/invoice-receipts', [InvoiceReceiptController::class, 'store']);
        Route::put('/invoice-receipts/{id}', [InvoiceReceiptController::class, 'update']);
        Route::delete('/invoice-receipts/{id}', [InvoiceReceiptController::class, 'destroy']);
        Route::post('/invoice-receipts/{id}/restore', [InvoiceReceiptController::class, 'restore']);
        
        // Invoice Management
        Route::post('/invoice-receipts/{id}/invoices', [InvoiceReceiptController::class, 'addInvoice']);
        Route::put('/invoice-receipts/{receiptId}/invoices/{invoiceId}', [InvoiceReceiptController::class, 'updateInvoice']);
        Route::delete('/invoice-receipts/{receiptId}/invoices/{invoiceId}', [InvoiceReceiptController::class, 'removeInvoice']);
        
        // Status Actions
        Route::post('/invoice-receipts/{id}/submit', [InvoiceReceiptController::class, 'submit']);
        Route::post('/invoice-receipts/{id}/approve', [InvoiceReceiptController::class, 'approve']);
        Route::post('/invoice-receipts/{id}/reject', [InvoiceReceiptController::class, 'reject']);
        
        // Helper Endpoints
        Route::get('/invoice-receipts-helpers/eligible-pos', [InvoiceReceiptController::class, 'getEligiblePurchaseOrders']);
        Route::get('/invoice-receipts/{id}/summary', [InvoiceReceiptController::class, 'getSummary']);
    });

    Route::prefix('reports/supplier-purchase')->group(function () {
    Route::get('/supplier', [SupplierPurchaseReportController::class, 'index']);
    Route::get('/{supplierId}/detail', [SupplierPurchaseReportController::class, 'detail']);
    Route::get('/{supplierId}/top-items', [SupplierPurchaseReportController::class, 'topItems']);
    Route::get('/performance', [SupplierPurchaseReportController::class, 'performance']);
    Route::get('/{supplierId}/monthly-trend', [SupplierPurchaseReportController::class, 'monthlyTrend']);
});
// routes/api.php
Route::prefix('reports/invoice-receipt')->group(function () {
    Route::get('/invoice', [InvoiceReceiptReportController::class, 'index']);
    Route::get('/{receiptId}/detail', [InvoiceReceiptReportController::class, 'detail']);
    Route::get('/by-supplier', [InvoiceReceiptReportController::class, 'bySupplier']);
    Route::get('/due-invoices', [InvoiceReceiptReportController::class, 'dueInvoices']);
    Route::get('/aging', [InvoiceReceiptReportController::class, 'agingReport']);
    Route::get('/monthly-trend', action: [InvoiceReceiptReportController::class, 'monthlyTrend']);
    Route::get('/by-requester', [InvoiceReceiptReportController::class, 'byRequester']);
});
Route::prefix('reports/purchase-return')->group(function () {
    Route::get('/return', action: [PurchaseReturnReportController::class, 'index']);
    Route::get('/{returnId}/detail', [PurchaseReturnReportController::class, 'detail']);
    Route::get('/by-supplier', [PurchaseReturnReportController::class, 'bySupplier']);
    Route::get('/top-returned-items', [PurchaseReturnReportController::class, 'topReturnedItems']);
    Route::get('/by-reason', [PurchaseReturnReportController::class, 'byReason']);
    Route::get('/monthly-trend', [PurchaseReturnReportController::class, 'monthlyTrend']);
    Route::get('/approval-rate', [PurchaseReturnReportController::class, 'approvalRate']);
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