<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_returned');
            $table->enum('reason', [
                'damaged_in_transit',
                'wrong_item',
                'customer_changed_mind',
                'defective',
                'other',
            ]);
            $table->enum('condition', ['new_unopened', 'opened', 'damaged', 'defective']);
            $table->boolean('restock_eligible')->default(false);
            $table->timestamps();
            $table->unique(['sales_return_id', 'sales_order_item_id'], 'sales_return_item_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
    }
};
