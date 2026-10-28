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

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::middleware('permission:users.manage')->group(function (): void {
        Route::get('/failed-jobs', [FailedJobController::class, 'index'])->name('failed-jobs.index');
        Route::post('/failed-jobs/{uuid}/retry', [FailedJobController::class, 'retry'])->name('failed-jobs.retry');
    });

    Route::middleware('permission:reports.export')->group(function (): void {
        Route::get('/report-exports', [ReportExportController::class, 'index'])->name('report-exports.index');
        Route::get('/report-exports/{id}/download', [ReportExportController::class, 'download'])->name('report-exports.download');
    });

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

    Route::middleware('permission:users.manage')->group(function (): void {
        Route::get('/system-settings', [SystemSettingsController::class, 'show'])->name('system-settings.show');
        Route::put('/system-settings', [SystemSettingsController::class, 'update'])->name('system-settings.update');
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

    Route::get('/inventory/availability', [SalesOrderController::class, 'availability'])->middleware('permission:inventory.view');

    // Purchases and supplier account workflows
    Route::get('/purchases/options', [PurchaseController::class, 'options'])->middleware(['permission:purchases.view', 'permission:purchases.create']);
    Route::middleware('permission:purchases.view')->group(function (): void {
        Route::get('/purchases', [PurchaseController::class, 'index']);
        Route::get('/purchases/{purchase}', [PurchaseController::class, 'show']);
        Route::get('/suppliers/{supplier}/purchases', [SupplierPurchaseController::class, 'index'])->middleware('permission:suppliers.view');
        Route::get('/suppliers/{supplier}/summary', [SupplierPurchaseController::class, 'summary'])->middleware('permission:suppliers.view');
    });
    Route::post('/purchases', [PurchaseController::class, 'store'])->middleware('permission:purchases.create');
    Route::put('/purchases/{purchase}', [PurchaseController::class, 'update'])->middleware('permission:purchases.update');
    Route::patch('/purchases/{purchase}', [PurchaseController::class, 'update'])->middleware('permission:purchases.update');
    Route::post('/purchases/{purchase}/submit', [PurchaseController::class, 'submit'])->middleware('permission:purchases.submit');
    Route::post('/purchases/{purchase}/approve', [PurchaseController::class, 'approve'])->middleware('permission:purchases.approve');
    Route::post('/purchases/{purchase}/reject', [PurchaseController::class, 'reject'])->middleware('permission:purchases.approve');
    Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->middleware('permission:purchases.cancel');
    Route::post('/purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->middleware('permission:purchases.receive');
    Route::post('/supplier-payments', [SupplierPurchaseController::class, 'storePayment'])->middleware('permission:suppliers.manage');

    // Sales orders and customer account workflows
    Route::get('/sales-orders/options', [SalesOrderController::class, 'options'])->middleware(['permission:sales.view', 'permission:sales.create']);
    Route::middleware('permission:sales.view')->group(function (): void {
        Route::get('/sales-orders', [SalesOrderController::class, 'index']);
        Route::get('/sales-orders/{salesOrder}', [SalesOrderController::class, 'show']);
        Route::get('/customers/{customer}/orders', [CustomerController::class, 'orders']);
    });
    Route::get('/sales-orders/{salesOrder}/returnable-items', [SalesReturnController::class, 'returnableItems'])->middleware('permission:returns.create');
    Route::post('/sales-orders', [SalesOrderController::class, 'store'])->middleware('permission:sales.create');
    Route::put('/sales-orders/{salesOrder}', [SalesOrderController::class, 'update'])->middleware('permission:sales.update');
    Route::patch('/sales-orders/{salesOrder}', [SalesOrderController::class, 'update'])->middleware('permission:sales.update');
    Route::post('/sales-orders/{salesOrder}/confirm', [SalesOrderController::class, 'confirm'])->middleware('permission:sales.confirm');
    Route::post('/sales-orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])->middleware('permission:sales.cancel');

    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers.manage');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.manage');
    Route::patch('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.manage');

    // Inter-warehouse transfers
    Route::get('/transfers/options', [WarehouseTransferController::class, 'options'])->middleware(['permission:transfers.view', 'permission:transfers.create']);
    Route::middleware('permission:transfers.view')->group(function (): void {
        Route::get('/transfers', [WarehouseTransferController::class, 'index']);
        Route::get('/transfers/{transfer}', [WarehouseTransferController::class, 'show']);
    });
    Route::post('/transfers', [WarehouseTransferController::class, 'store'])->middleware('permission:transfers.create');
    Route::put('/transfers/{transfer}', [WarehouseTransferController::class, 'update'])->middleware('permission:transfers.create');
    Route::patch('/transfers/{transfer}', [WarehouseTransferController::class, 'update'])->middleware('permission:transfers.create');
    Route::post('/transfers/{transfer}/dispatch', [WarehouseTransferController::class, 'dispatch'])->middleware('permission:transfers.dispatch');
    Route::post('/transfers/{transfer}/receive', [WarehouseTransferController::class, 'receive'])->middleware('permission:transfers.receive');
    Route::post('/transfers/{transfer}/cancel', [WarehouseTransferController::class, 'cancel'])->middleware('permission:transfers.cancel');

    // Immutable stock corrections and customer returns
    Route::middleware('permission:adjustments.view')->group(function (): void {
        Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index']);
        Route::get('/stock-adjustments/{adjustment}', [StockAdjustmentController::class, 'show']);
    });
    Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->middleware('permission:inventory.adjust');

    Route::middleware('permission:returns.view')->group(function (): void {
        Route::get('/sales-returns', [SalesReturnController::class, 'index']);
        Route::get('/sales-returns/{salesReturn}', [SalesReturnController::class, 'show']);
    });
    Route::post('/sales-returns', [SalesReturnController::class, 'store'])->middleware('permission:returns.create');
    Route::put('/sales-returns/{salesReturn}', [SalesReturnController::class, 'update'])->middleware('permission:returns.update');
    Route::patch('/sales-returns/{salesReturn}', [SalesReturnController::class, 'update'])->middleware('permission:returns.update');
    Route::post('/sales-returns/{salesReturn}/complete', [SalesReturnController::class, 'complete'])->middleware('permission:returns.complete');
    Route::post('/sales-returns/{salesReturn}/cancel', [SalesReturnController::class, 'cancel'])->middleware('permission:returns.cancel');
});
