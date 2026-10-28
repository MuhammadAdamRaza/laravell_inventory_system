<?php

namespace App\Listeners;

use App\Events\StockLevelChanged;
use App\Services\InventoryVersionStore;

class BumpInventoryVersion
{
    public function __construct(private InventoryVersionStore $versions) {}

    public function handle(StockLevelChanged $event): void
    {
        $this->versions->incrementForWarehouse((int) $event->warehouse->getKey());
    }
}
