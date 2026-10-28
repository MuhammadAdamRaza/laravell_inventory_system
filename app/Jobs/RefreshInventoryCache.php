<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class RefreshInventoryCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  list<int>  $productIds
     */
    public function __construct(public array $productIds, public int $warehouseId) {}

    public function handle(): void
    {
        foreach ($this->productIds as $productId) {
            Cache::forget("inventory_balance_{$productId}_{$this->warehouseId}");
            Cache::forget("inventory_product_{$productId}");
        }

        Cache::forget("inventory_warehouse_{$this->warehouseId}");
    }
}
