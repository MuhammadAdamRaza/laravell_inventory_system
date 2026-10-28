<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->index(['order_date', 'status'], 'purchases_reporting_date_status_idx');
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->index(['order_date', 'status'], 'sales_orders_reporting_date_status_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index('created_at', 'stock_movements_created_at_idx');
            $table->index(
                ['product_id', 'warehouse_id', 'created_at'],
                'stock_movements_product_warehouse_created_idx',
            );
            $table->index(
                ['warehouse_id', 'created_at'],
                'stock_movements_warehouse_created_idx',
            );
        });

        Schema::table('inventory_balances', function (Blueprint $table): void {
            $table->index(['warehouse_id', 'quantity'], 'inventory_balances_warehouse_quantity_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropIndex('purchases_reporting_date_status_idx');
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropIndex('sales_orders_reporting_date_status_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('stock_movements_created_at_idx');
            $table->dropIndex('stock_movements_product_warehouse_created_idx');
            $table->dropIndex('stock_movements_warehouse_created_idx');
        });

        Schema::table('inventory_balances', function (Blueprint $table): void {
            $table->dropIndex('inventory_balances_warehouse_quantity_idx');
        });
    }
};
