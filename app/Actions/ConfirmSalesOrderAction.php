<?php

namespace App\Actions;

use App\Enums\MovementType;
use App\Jobs\RefreshInventoryCache;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SalesStatusService;
use Illuminate\Support\Facades\DB;

class ConfirmSalesOrderAction
{
    public function __construct(
        private InventoryService $inventory,
        private SalesStatusService $statuses,
    ) {}

    public function execute(SalesOrder $salesOrder, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder, $actor): SalesOrder {
            $lockedOrder = SalesOrder::query()->lockForUpdate()->findOrFail($salesOrder->id);
            if ($lockedOrder->status !== 'draft') {
                $this->statuses->transition($lockedOrder, 'confirmed');
            }

            $items = $lockedOrder->items()->orderBy('product_id')->get();
            if ($items->isEmpty()) {
                abort(422, 'A sales order must contain at least one item before confirmation.');
            }

            foreach ($items as $item) {
                $this->inventory->adjustStock(
                    (int) $item->product_id,
                    (int) $lockedOrder->warehouse_id,
                    -((int) $item->quantity),
                    MovementType::Sale,
                    null,
                    (int) $actor->id,
                    ['type' => 'sales_order', 'id' => $lockedOrder->id],
                );
            }

            $confirmed = $this->statuses->transition($lockedOrder, 'confirmed');
            $confirmed->update(['confirmed_by' => $actor->id, 'confirmed_at' => now()]);

            DB::afterCommit(static function () use ($items, $lockedOrder): void {
                RefreshInventoryCache::dispatch(
                    $items->pluck('product_id')->map(static fn ($id): int => (int) $id)->all(),
                    (int) $lockedOrder->warehouse_id,
                );
            });

            return $confirmed->refresh()->load(['customer', 'warehouse', 'creator', 'confirmer', 'items.product']);
        }, attempts: 3);
    }
}
