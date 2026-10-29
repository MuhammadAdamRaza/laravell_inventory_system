<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FailedJobController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportExportController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\SalesReturnController;
use App\Http\Controllers\Api\StockAdjustmentController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierPurchaseController;
use App\Http\Controllers\Api\SystemSettingsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\WarehouseTransferController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Auth Routes (With Brute-force Throttling)
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register')->name('register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset')->name('password.email');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('password.update');

/*
|--------------------------------------------------------------------------
| Protected Routes (auth:sanctum & throttle:api)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    // Current user & session
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');

    // Read-only reporting and analytics
    Route::middleware('permission:reports.view')->prefix('reports')->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'show'])
            ->name('reports.dashboard');
        Route::get('/options', [ReportController::class, 'options'])->name('reports.options');

        Route::middleware('permission:purchases.view')->group(function (): void {
            Route::get('/purchases', [ReportController::class, 'purchases'])->name('reports.purchases');
            Route::get('/purchases/export', [ReportController::class, 'exportPurchases'])
                ->middleware('permission:reports.export')->name('reports.purchases.export');
        });

        Route::middleware('permission:sales.view')->group(function (): void {
            Route::get('/sales', [ReportController::class, 'sales'])->name('reports.sales');
            Route::get('/sales/export', [ReportController::class, 'exportSales'])
                ->middleware('permission:reports.export')->name('reports.sales.export');
            Route::get('/top-products', [ReportController::class, 'topProducts'])->name('reports.top-products');
            Route::get('/top-products/export', [ReportController::class, 'exportTopProducts'])
                ->middleware('permission:reports.export')->name('reports.top-products.export');
        });

        Route::middleware('permission:inventory.view')->group(function (): void {
            Route::get('/inventory-valuation', [ReportController::class, 'inventoryValuation'])
                ->name('reports.inventory-valuation');
            Route::get('/inventory-valuation/export', [ReportController::class, 'exportInventoryValuation'])
                ->middleware('permission:reports.export')->name('reports.inventory-valuation.export');
        });
    });

    // Users and Roles Management (Administrator only)
    Route::middleware('permission:users.manage')->group(function (): void {
        Route::get('/roles', [UserController::class, 'roles'])->name('roles.index');
        Route::apiResource('users', UserController::class);
    });

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:categories.view');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('permission:categories.view');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:categories.manage');
    Route::match(['put', 'patch'], '/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.manage');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.manage');

    // Warehouses
    Route::get('/warehouses', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view');
    Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:warehouses.view');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->middleware('permission:warehouses.manage');
    Route::match(['put', 'patch'], '/warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:warehouses.manage');
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:warehouses.manage');

    // Suppliers
    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('permission:suppliers.view');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('permission:suppliers.view');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('permission:suppliers.manage');
    Route::match(['put', 'patch'], '/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.manage');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:suppliers.manage');

    // Products
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create');
    Route::match(['put', 'patch', 'post'], '/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.archive');

    // Inventory & Real-world Analytics
    Route::get('/inventory/stats', [InventoryController::class, 'stats'])->middleware('permission:inventory.view');
    Route::get('/inventory/balances', [InventoryController::class, 'balances'])->middleware('permission:inventory.view');
    Route::get('/inventory/movements', [InventoryController::class, 'movements'])->middleware('permission:inventory.view');
    Route::post('/inventory/opening-stock', [InventoryController::class, 'openingStock'])->middleware('permission:inventory.adjust');

    Route::prefix('purchases')->name('purchases.')->group(function (): void {
        Route::get('/', [PurchaseController::class, 'index'])->middleware('permission:purchases.view')->name('index');
        Route::get('/options', [PurchaseController::class, 'options'])->middleware('permission:purchases.view')->name('options');
        Route::post('/', [PurchaseController::class, 'store'])->middleware('permission:purchases.create')->name('store');
        Route::get('/{purchase}', [PurchaseController::class, 'show'])->middleware('permission:purchases.view')->name('show');
        Route::put('/{purchase}', [PurchaseController::class, 'update'])->middleware('permission:purchases.update')->name('update');
        Route::post('/{purchase}/submit', [PurchaseController::class, 'submit'])->middleware('permission:purchases.submit')->name('submit');
        Route::post('/{purchase}/approve', [PurchaseController::class, 'approve'])->middleware('permission:purchases.approve')->name('approve');
        Route::post('/{purchase}/reject', [PurchaseController::class, 'reject'])->middleware('permission:purchases.approve')->name('reject');
        Route::post('/{purchase}/cancel', [PurchaseController::class, 'cancel'])->middleware('permission:purchases.cancel')->name('cancel');
        Route::post('/{purchase}/receive', [PurchaseController::class, 'receive'])->middleware('permission:purchases.receive')->name('receive');
    });

    Route::get('/suppliers/{supplier}/purchases', [SupplierPurchaseController::class, 'index'])->middleware('permission:suppliers.view')->name('suppliers.purchases');
    Route::get('/suppliers/{supplier}/summary', [SupplierPurchaseController::class, 'summary'])->middleware('permission:suppliers.view')->name('suppliers.summary');
    Route::post('/supplier-payments', [SupplierPurchaseController::class, 'storePayment'])->middleware('permission:suppliers.manage')->name('suppliers.payments.store');

    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers.manage')->name('customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view')->name('customers.show');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.manage')->name('customers.update');
    Route::get('/customers/{customer}/orders', [CustomerController::class, 'orders'])->middleware('permission:customers.view')->name('customers.orders');

    Route::prefix('sales-orders')->name('sales-orders.')->group(function (): void {
        Route::get('/', [SalesOrderController::class, 'index'])->middleware('permission:sales.view')->name('index');
        Route::get('/options', [SalesOrderController::class, 'options'])->middleware('permission:sales.view')->name('options');
        Route::get('/availability', [SalesOrderController::class, 'availability'])->middleware('permission:sales.view')->name('availability');
        Route::post('/', [SalesOrderController::class, 'store'])->middleware('permission:sales.create')->name('store');
        Route::get('/{salesOrder}', [SalesOrderController::class, 'show'])->middleware('permission:sales.view')->name('show');
        Route::put('/{salesOrder}', [SalesOrderController::class, 'update'])->middleware('permission:sales.update')->name('update');
        Route::post('/{salesOrder}/confirm', [SalesOrderController::class, 'confirm'])->middleware('permission:sales.confirm')->name('confirm');
        Route::post('/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])->middleware('permission:sales.cancel')->name('cancel');
        Route::get('/{salesOrder}/returnable-items', [SalesReturnController::class, 'returnableItems'])->middleware('permission:returns.view')->name('returnable-items');
    });

    Route::prefix('transfers')->name('transfers.')->group(function (): void {
        Route::get('/', [WarehouseTransferController::class, 'index'])->middleware('permission:transfers.view')->name('index');
        Route::get('/options', [WarehouseTransferController::class, 'options'])->middleware('permission:transfers.view')->name('options');
        Route::post('/', [WarehouseTransferController::class, 'store'])->middleware('permission:transfers.create')->name('store');
        Route::get('/{transfer}', [WarehouseTransferController::class, 'show'])->middleware('permission:transfers.view')->name('show');
        Route::put('/{transfer}', [WarehouseTransferController::class, 'update'])->middleware('permission:transfers.create')->name('update');
        Route::post('/{transfer}/dispatch', [WarehouseTransferController::class, 'dispatch'])->middleware('permission:transfers.dispatch')->name('dispatch');
        Route::post('/{transfer}/receive', [WarehouseTransferController::class, 'receive'])->middleware('permission:transfers.receive')->name('receive');
        Route::post('/{transfer}/cancel', [WarehouseTransferController::class, 'cancel'])->middleware('permission:transfers.cancel')->name('cancel');
    });

    Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->middleware('permission:adjustments.view')->name('stock-adjustments.index');
    Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->middleware('permission:inventory.adjust')->name('stock-adjustments.store');
    Route::get('/stock-adjustments/{adjustment}', [StockAdjustmentController::class, 'show'])->middleware('permission:adjustments.view')->name('stock-adjustments.show');

    Route::prefix('sales-returns')->name('sales-returns.')->group(function (): void {
        Route::get('/', [SalesReturnController::class, 'index'])->middleware('permission:returns.view')->name('index');
        Route::post('/', [SalesReturnController::class, 'store'])->middleware('permission:returns.create')->name('store');
        Route::get('/{salesReturn}', [SalesReturnController::class, 'show'])->middleware('permission:returns.view')->name('show');
        Route::put('/{salesReturn}', [SalesReturnController::class, 'update'])->middleware('permission:returns.update')->name('update');
        Route::post('/{salesReturn}/complete', [SalesReturnController::class, 'complete'])->middleware('permission:returns.complete')->name('complete');
        Route::post('/{salesReturn}/cancel', [SalesReturnController::class, 'cancel'])->middleware('permission:returns.cancel')->name('cancel');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::middleware('permission:reports.export')->group(function (): void {
        Route::get('/report-exports', [ReportExportController::class, 'index'])->name('report-exports.index');
        Route::get('/report-exports/{id}/download', [ReportExportController::class, 'download'])->name('report-exports.download');
    });

    Route::middleware('permission:users.manage')->group(function (): void {
        Route::get('/system-settings', [SystemSettingsController::class, 'show'])->name('system-settings.show');
        Route::put('/system-settings', [SystemSettingsController::class, 'update'])->name('system-settings.update');
        Route::get('/failed-jobs', [FailedJobController::class, 'index'])->name('failed-jobs.index');
        Route::post('/failed-jobs/{uuid}/retry', [FailedJobController::class, 'retry'])->name('failed-jobs.retry');
    });
});
