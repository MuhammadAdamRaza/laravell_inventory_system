<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Jobs\RefreshInventoryCache;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReceivePurchaseAction
{
    /**
     * @param  array{idempotency_key: string, warehouse_id: int, items: list<array{purchase_item_id: int, quantity_received: int}>}  $data
     * @return array{receipt: PurchaseReceipt, created: bool}
     */
    public function execute(Purchase $purchase, array $data, User $actor): array
    {
        try {
            return DB::transaction(function () use ($purchase, $data, $actor): array {
                $lockedPurchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->getKey());
                $existingReceipt = PurchaseReceipt::query()->where('idempotency_key', $data['idempotency_key'])->first();

                if ($existingReceipt) {
                    abort_unless($existingReceipt->purchase_id === $lockedPurchase->id, 409, 'This idempotency key belongs to another purchase.');

                    return ['receipt' => $existingReceipt->load(['items.purchaseItem.product', 'receiver']), 'created' => false];
                }
                if (! in_array($lockedPurchase->status, ['approved', 'partially_received'], true)) {
                    throw ValidationException::withMessages(['status' => 'Only approved purchases can be received.']);
                }
                if ((int) $data['warehouse_id'] !== (int) $lockedPurchase->warehouse_id) {
                    throw ValidationException::withMessages(['warehouse_id' => 'Stock must be received into the purchase warehouse.']);
                }
                if ($actor->hasRole('Warehouse Staff') && ! $actor->warehouses()->whereKey($lockedPurchase->warehouse_id)->exists()) {
                    abort(403, 'You are not assigned to this warehouse.');
                }

                $requestedItems = collect($data['items']);
                $purchaseItems = $lockedPurchase->items()->with('product')
                    ->whereIn('id', $requestedItems->pluck('purchase_item_id'))
                    ->lockForUpdate()->get()->keyBy('id');
                if ($purchaseItems->count() !== $requestedItems->count()) {
                    throw ValidationException::withMessages(['items' => 'Every received item must belong to this purchase.']);
                }
                foreach ($requestedItems as $requestedItem) {
                    $purchaseItem = $purchaseItems->get((int) $requestedItem['purchase_item_id']);
                    if ((int) $requestedItem['quantity_received'] > $purchaseItem->ordered_quantity - $purchaseItem->received_quantity) {
                        throw ValidationException::withMessages(['items' => "Received quantity exceeds the remaining quantity for {$purchaseItem->product->name}."]);
                    }
                }

                $receipt = $lockedPurchase->receipts()->create([
                    'receipt_number' => 'GRN-TMP-'.Str::lower(Str::random(12)),
                    'warehouse_id' => $lockedPurchase->warehouse_id,
                    'received_by' => $actor->id,
                    'received_at' => now(),
                    'idempotency_key' => $data['idempotency_key'],
                ]);
                $receipt->update(['receipt_number' => sprintf('GRN-%06d', $receipt->id)]);
                $productIds = [];

                foreach ($requestedItems as $requestedItem) {
                    $purchaseItem = $purchaseItems->get((int) $requestedItem['purchase_item_id']);
                    $quantity = (int) $requestedItem['quantity_received'];
                    app(InventoryService::class)->adjustStock(
                        (int) $purchaseItem->product_id,
                        (int) $lockedPurchase->warehouse_id,
                        $quantity,
                        MovementType::Receipt,
                        null,
                        (int) $actor->id,
                        ['type' => 'purchase_receipt', 'id' => $receipt->id],
                    );
                    $purchaseItem->increment('received_quantity', $quantity);
                    $receipt->items()->create(['purchase_item_id' => $purchaseItem->id, 'quantity_received' => $quantity]);
                    $productIds[] = (int) $purchaseItem->product_id;
                }

                $complete = ! $lockedPurchase->items()->whereColumn('received_quantity', '<', 'ordered_quantity')->exists();
                $targetStatus = $complete ? 'received' : 'partially_received';
                if ($lockedPurchase->status !== $targetStatus) {
                    app(PurchaseStatusService::class)->transition($lockedPurchase, $targetStatus, $actor);
                }
                DB::afterCommit(static function () use ($productIds, $lockedPurchase): void {
                    RefreshInventoryCache::dispatch(array_values(array_unique($productIds)), (int) $lockedPurchase->warehouse_id);
                });

                return ['receipt' => $receipt->load(['items.purchaseItem.product', 'receiver']), 'created' => true];
            }, attempts: 3);
        } catch (QueryException $exception) {
            $receipt = PurchaseReceipt::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if (! $receipt) {
                throw $exception;
            }
            abort_unless($receipt->purchase_id === $purchase->id, 409, 'This idempotency key belongs to another purchase.');

            return ['receipt' => $receipt->load(['items.purchaseItem.product', 'receiver']), 'created' => false];
        }
    }
}
