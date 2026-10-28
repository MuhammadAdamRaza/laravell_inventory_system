<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Events\StockLevelChanged;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Adjust stock for a product in a warehouse within an isolated transaction.
     *
     * @param  Model|array{type: string, id: int}|null  $reference
     *
     * @throws InsufficientStockException
     * @throws \Throwable
     */
    public function adjustStock(
        int $productId,
        int $warehouseId,
        int $quantityChange,
        MovementType $movementType,
        ?string $reason,
        int $userId,
        mixed $reference = null,
        bool $emitStockLevelEvent = true
    ): StockMovement {
        return DB::transaction(function () use (
            $productId,
            $warehouseId,
            $quantityChange,
            $movementType,
            $reason,
            $userId,
            $reference,
            $emitStockLevelEvent
        ): StockMovement {
            // Lock or create row
            DB::table('inventory_balances')->insertOrIgnore([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $balance = InventoryBalance::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->firstOrFail();

            $currentQuantity = $balance->quantity;
            $newQuantity = $currentQuantity + $quantityChange;

            if ($newQuantity < 0) {
                throw new InsufficientStockException(
                    "Insufficient stock. Current: {$currentQuantity}, Attempted change: {$quantityChange}"
                );
            }

            $balance->quantity = $newQuantity;
            $balance->save();

            $product = Product::query()->findOrFail($productId);
            if ($emitStockLevelEvent && $newQuantity <= $product->minimum_stock) {
                $warehouse = Warehouse::query()->findOrFail($warehouseId);
                DB::afterCommit(static function () use ($product, $warehouse, $newQuantity): void {
                    StockLevelChanged::dispatch($product, $warehouse, $newQuantity);
                });
            }

            $referenceType = null;
            $referenceId = null;

            if ($reference instanceof Model) {
                $referenceType = get_class($reference);
                $referenceId = $reference->getKey();
            } elseif (is_array($reference)) {
                $referenceType = $reference['type'] ?? null;
                $referenceId = $reference['id'] ?? null;
            }

            $movement = StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'movement_type' => $movementType->value,
                'quantity_change' => $quantityChange,
                'balance_after' => $newQuantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reason' => $reason,
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            $this->clearCache($productId, $warehouseId);

            return $movement;
        });
    }

    /**
     * Cache invalidation hook for inventory balances.
     */
    protected function clearCache(int $productId, int $warehouseId): void
    {
        // Placeholder hook for Module 1 caching
        Cache::forget("inventory_balance_{$productId}_{$warehouseId}");
        Cache::forget("inventory_product_{$productId}");
        Cache::forget("inventory_warehouse_{$warehouseId}");
    }
}
