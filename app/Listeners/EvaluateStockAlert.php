<?php

namespace App\Listeners;

use App\Events\StockLevelChanged;
use App\Models\InventoryBalance;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class EvaluateStockAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function handle(StockLevelChanged $event): void
    {
        $product = $event->product->fresh();
        $warehouse = $event->warehouse->fresh();

        if ($product === null || $warehouse === null) {
            return;
        }

        $quantity = (int) (InventoryBalance::query()
            ->where('product_id', $product->getKey())
            ->where('warehouse_id', $warehouse->getKey())
            ->value('quantity') ?? 0);
        $state = $quantity <= 0
            ? 'out_of_stock'
            : ($quantity <= (int) $product->minimum_stock ? 'low_stock' : 'ok');
        $now = now();

        DB::table('stock_alert_states')->insertOrIgnore([
            'product_id' => $product->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'state' => 'ok',
            'last_notified_state' => 'ok',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $shouldNotify = DB::transaction(function () use ($product, $warehouse, $state): bool {
            $alertState = DB::table('stock_alert_states')
                ->where('product_id', $product->getKey())
                ->where('warehouse_id', $warehouse->getKey())
                ->lockForUpdate()
                ->first();

            if ($alertState === null || $alertState->state === $state) {
                return false;
            }

            $shouldNotify = in_array($state, ['low_stock', 'out_of_stock'], true)
                && $alertState->last_notified_state !== $state;

            DB::table('stock_alert_states')
                ->where('id', $alertState->id)
                ->update([
                    'state' => $state,
                    'last_notified_state' => $state === 'ok'
                        ? 'ok'
                        : ($shouldNotify ? $state : $alertState->last_notified_state),
                    'updated_at' => now(),
                ]);

            return $shouldNotify;
        });

        if (! $shouldNotify) {
            return;
        }

        $recipients = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($warehouse): void {
                $query->role(['Administrator', 'Manager'])
                    ->orWhereHas('warehouses', fn ($warehouses) => $warehouses->whereKey($warehouse->getKey()));
            })
            ->with(['roles', 'permissions'])
            ->get()
            ->filter(static fn (User $user): bool => $user->can('inventory.view'));

        foreach ($recipients as $recipient) {
            Notification::send($recipient, new LowStockAlert(
                productName: $product->name,
                sku: $product->sku,
                warehouseName: $warehouse->name,
                warehouseId: (int) $warehouse->getKey(),
                productId: (int) $product->getKey(),
                quantity: $quantity,
                minimumStock: (int) $product->minimum_stock,
                alertState: $state,
            ));
        }
    }
}
