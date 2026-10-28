<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_return_id')->unique()->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('completed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_completions');
    }
};
