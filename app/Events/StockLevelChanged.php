<?php

namespace App\Events;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockLevelChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Product $product, public Warehouse $warehouse, public int $quantity) {}
}
