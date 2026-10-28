<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Events\StockLevelChanged;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnCompletion;
use App\Models\SalesReturnItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\SalesReturnCompleted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesReturnStatusService
{
    /**
     * @param  array{sales_order_id: int, return_date?: string|null, reason_summary?: string|null, items: list<array{sales_order_item_id: int, quantity_returned: int, reason: string, condition: string, restock_eligible: bool}>}  $data
     */
    public function create(SalesOrder $salesOrder, array $data, User $actor): SalesReturn
    {
        return DB::transaction(function () use ($salesOrder, $data, $actor): SalesReturn {
            $lockedOrder = SalesOrder::query()->lockForUpdate()->findOrFail($salesOrder->getKey());
            $this->ensureConfirmedOrder($lockedOrder);
            $this->assertLinesAreReturnable($lockedOrder, $data['items']);

            $return = SalesReturn::query()->create([
                'return_number' => 'RET-TMP-'.Str::lower(Str::random(28)),
                'sales_order_id' => $lockedOrder->id,
                'warehouse_id' => $lockedOrder->warehouse_id,
                'status' => 'draft',
                'return_date' => $data['return_date'] ?? today()->toDateString(),
                'reason_summary' => $data['reason_summary'] ?? null,
                'created_by' => $actor->id,
            ]);
            $return->update(['return_number' => sprintf('RET-%06d', $return->id)]);
            $this->replaceItems($return, $data['items'], $lockedOrder);

            return $return->refresh()->load($this->returnRelations());
        }, attempts: 3);
    }

    /**
     * @param  array{return_date?: string|null, reason_summary?: string|null, items?: list<array{sales_order_item_id: int, quantity_returned: int, reason: string, condition: string, restock_eligible: bool}>}  $data
     */
    public function update(SalesReturn $salesReturn, array $data): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn, $data): SalesReturn {
            $lockedReturn = SalesReturn::query()->lockForUpdate()->findOrFail($salesReturn->getKey());
            $this->ensureDraft($lockedReturn);
            $lockedOrder = SalesOrder::query()->lockForUpdate()->findOrFail($lockedReturn->sales_order_id);
            $this->ensureConfirmedOrder($lockedOrder);

            $attributes = [];
            if (array_key_exists('return_date', $data)) {
                $attributes['return_date'] = $data['return_date'];
            }
            if (array_key_exists('reason_summary', $data)) {
                $attributes['reason_summary'] = $data['reason_summary'];
            }
            if ($attributes !== []) {
                $lockedReturn->update($attributes);
            }

            if (array_key_exists('items', $data)) {
                $this->assertLinesAreReturnable($lockedOrder, $data['items'], (int) $lockedReturn->id);
                $lockedReturn->items()->delete();
                $this->replaceItems($lockedReturn, $data['items'], $lockedOrder);
            }

            return $lockedReturn->refresh()->load($this->returnRelations());
        }, attempts: 3);
    }

    /**
     * @return array{items: list<array{sales_order_item_id: int, product_id: int, sku: string, name: string, unit: string, quantity_sold: int, returned_quantity: int, reserved_quantity: int, returnable_quantity: int}>}
     */
    public function returnableItems(SalesOrder $salesOrder, ?SalesReturn $excludeReturn = null): array
    {
        $this->ensureConfirmedOrder($salesOrder);
        if ($excludeReturn && ((int) $excludeReturn->sales_order_id !== (int) $salesOrder->id || $excludeReturn->status !== 'draft')) {
            abort(404);
        }

        $orderItems = $salesOrder->items()->with('product')->orderBy('id')->get();
        $itemIds = $orderItems->modelKeys();
        $activeQuantities = $this->returnQuantities($itemIds, ['draft', 'completed'], $excludeReturn?->id);
        $completedQuantities = $this->returnQuantities($itemIds, ['completed'], $excludeReturn?->id);

        return ['items' => $orderItems->map(function (SalesOrderItem $orderItem) use ($activeQuantities, $completedQuantities): array {
            $reservedQuantity = (int) ($activeQuantities[$orderItem->id] ?? 0);
            $returnedQuantity = (int) ($completedQuantities[$orderItem->id] ?? 0);

            return [
                'sales_order_item_id' => (int) $orderItem->id,
                'product_id' => (int) $orderItem->product_id,
                'sku' => $orderItem->product->sku,
                'name' => $orderItem->product->name,
                'unit' => $orderItem->product->unit,
                'quantity_sold' => (int) $orderItem->quantity,
                'returned_quantity' => $returnedQuantity,
                'reserved_quantity' => max(0, $reservedQuantity - $returnedQuantity),
                'returnable_quantity' => max(0, (int) $orderItem->quantity - $reservedQuantity),
            ];
        })->all()];
    }

    public function transition(SalesReturn $salesReturn, string $targetStatus, User $actor): SalesReturn
    {
        $lockedReturn = SalesReturn::query()->lockForUpdate()->findOrFail($salesReturn->getKey());
        $transitions = ['draft' => ['completed', 'cancelled'], 'completed' => [], 'cancelled' => []];

        if (! in_array($targetStatus, $transitions[$lockedReturn->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "A sales return cannot move from {$lockedReturn->status} to {$targetStatus}.",
            ]);
        }

        $attributes = ['status' => $targetStatus];
        if ($targetStatus === 'completed') {
            if (! $lockedReturn->items()->exists()) {
                throw ValidationException::withMessages(['items' => 'Add at least one item before completing the return.']);
            }
            $attributes['completed_by'] = $actor->id;
            $attributes['completed_at'] = now();
        } elseif ($targetStatus === 'cancelled') {
            $attributes['cancelled_at'] = now();
        }

        $lockedReturn->update($attributes);

        return $lockedReturn->refresh();
    }

    /**
     * @return array{sales_return: SalesReturn, created: bool}
     */
    public function complete(SalesReturn $salesReturn, string $idempotencyKey, User $actor): array
    {
        try {
            $result = DB::transaction(function () use ($salesReturn, $idempotencyKey, $actor): array {
                $existing = SalesReturnCompletion::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    abort_unless((int) $existing->sales_return_id === (int) $salesReturn->id, 409, 'This idempotency key belongs to another return.');

                    return [
                        'sales_return' => $existing->salesReturn()->with($this->returnRelations())->firstOrFail(),
                        'created' => false,
                        'stock_events' => [],
                    ];
                }

                $lockedReturn = SalesReturn::query()->lockForUpdate()->findOrFail($salesReturn->getKey());
                $this->ensureDraft($lockedReturn);
                $lockedOrder = SalesOrder::query()->lockForUpdate()->findOrFail($lockedReturn->sales_order_id);
                $this->ensureConfirmedOrder($lockedOrder);

                if ($actor->hasRole('Warehouse Staff') && ! $actor->warehouses()->whereKey($lockedReturn->warehouse_id)->exists()) {
                    abort(404);
                }

                $lines = $lockedReturn->items()->with('product')->orderBy('product_id')->get();
                $this->assertLinesAreReturnable(
                    $lockedOrder,
                    $lines->map(fn (SalesReturnItem $item): array => [
                        'sales_order_item_id' => (int) $item->sales_order_item_id,
                        'quantity_returned' => (int) $item->quantity_returned,
                        'reason' => $item->reason,
                        'condition' => $item->condition,
                        'restock_eligible' => $item->restock_eligible,
                    ])->all(),
                    (int) $lockedReturn->id,
                );

                $warehouse = Warehouse::query()->findOrFail($lockedReturn->warehouse_id);
                $stockEvents = [];
                foreach ($lines->where('restock_eligible', true) as $line) {
                    $movement = app(InventoryService::class)->adjustStock(
                        (int) $line->product_id,
                        (int) $lockedReturn->warehouse_id,
                        (int) $line->quantity_returned,
                        MovementType::Return,
                        "Customer return {$lockedReturn->return_number}: {$line->reason}",
                        (int) $actor->id,
                        ['type' => 'sales_return', 'id' => (int) $lockedReturn->id],
                        false,
                    );
                    $stockEvents[] = [
                        'product' => $line->product,
                        'warehouse' => $warehouse,
                        'quantity' => (int) $movement->balance_after,
                    ];
                }

                $completed = $this->transition($lockedReturn, 'completed', $actor);
                SalesReturnCompletion::query()->create([
                    'sales_return_id' => $completed->id,
                    'idempotency_key' => $idempotencyKey,
                    'completed_by' => $actor->id,
                    'completed_at' => $completed->completed_at,
                ]);

                return [
                    'sales_return' => $completed->load($this->returnRelations()),
                    'created' => true,
                    'stock_events' => $stockEvents,
                ];
            }, attempts: 3);
        } catch (QueryException $exception) {
            $completion = SalesReturnCompletion::query()->where('idempotency_key', $idempotencyKey)->first();
            if (! $completion) {
                throw $exception;
            }
            abort_unless((int) $completion->sales_return_id === (int) $salesReturn->id, 409, 'This idempotency key belongs to another return.');

            return [
                'sales_return' => $completion->salesReturn()->with($this->returnRelations())->firstOrFail(),
                'created' => false,
            ];
        }

        if ($result['created']) {
            foreach ($result['stock_events'] as $stockEvent) {
                StockLevelChanged::dispatch($stockEvent['product'], $stockEvent['warehouse'], $stockEvent['quantity']);
            }

            $result['sales_return']->creator->notify((new SalesReturnCompleted($result['sales_return']))->afterCommit());
        }

        return [
            'sales_return' => $result['sales_return'],
            'created' => $result['created'],
        ];
    }

    /**
     * @param  list<array{sales_order_item_id: int, quantity_returned: int, reason: string, condition: string, restock_eligible: bool}>  $lines
     */
    private function assertLinesAreReturnable(SalesOrder $salesOrder, array $lines, ?int $excludeReturnId = null): void
    {
        $this->ensureConfirmedOrder($salesOrder);
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one return item.']);
        }

        $itemIds = array_map(static fn (array $line): int => (int) $line['sales_order_item_id'], $lines);
        if (count($itemIds) !== count(array_unique($itemIds))) {
            throw ValidationException::withMessages(['items' => 'An order line can only appear once in a return.']);
        }

        $orderItems = $salesOrder->items()->whereIn('id', $itemIds)->get()->keyBy('id');
        if ($orderItems->count() !== count($itemIds)) {
            throw ValidationException::withMessages(['items' => 'Every return line must belong to the selected sales order.']);
        }

        $reservedQuantities = $this->returnQuantities($itemIds, ['draft', 'completed'], $excludeReturnId);
        foreach ($lines as $index => $line) {
            $orderItem = $orderItems->get((int) $line['sales_order_item_id']);
            $quantity = (int) $line['quantity_returned'];
            $reason = $line['reason'] ?? '';
            $condition = $line['condition'] ?? '';
            $restockEligible = filter_var($line['restock_eligible'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (! in_array($reason, ['damaged_in_transit', 'wrong_item', 'customer_changed_mind', 'defective', 'other'], true)) {
                throw ValidationException::withMessages(["items.{$index}.reason" => 'Choose a valid return reason.']);
            }
            if (! in_array($condition, ['new_unopened', 'opened', 'damaged', 'defective'], true)) {
                throw ValidationException::withMessages(["items.{$index}.condition" => 'Choose a valid item condition.']);
            }
            if ($restockEligible && ! in_array($condition, ['new_unopened', 'opened'], true)) {
                throw ValidationException::withMessages(["items.{$index}.restock_eligible" => 'Only unopened or opened items can be returned to sellable stock.']);
            }

            $reserved = (int) ($reservedQuantities[$orderItem->id] ?? 0);
            if ($reserved + $quantity > (int) $orderItem->quantity) {
                $remaining = max(0, (int) $orderItem->quantity - $reserved);
                throw ValidationException::withMessages([
                    "items.{$index}.quantity_returned" => "Only {$remaining} unit(s) remain returnable for this order line.",
                ]);
            }
        }
    }

    /**
     * @param  list<array{sales_order_item_id: int, quantity_returned: int, reason: string, condition: string, restock_eligible: bool}>  $lines
     */
    private function replaceItems(SalesReturn $salesReturn, array $lines, SalesOrder $salesOrder): void
    {
        $orderItems = $salesOrder->items()->whereIn(
            'id',
            array_map(static fn (array $line): int => (int) $line['sales_order_item_id'], $lines),
        )->get()->keyBy('id');

        foreach ($lines as $line) {
            $orderItem = $orderItems->get((int) $line['sales_order_item_id']);
            $salesReturn->items()->create([
                'sales_order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'quantity_returned' => (int) $line['quantity_returned'],
                'reason' => $line['reason'],
                'condition' => $line['condition'],
                'restock_eligible' => filter_var($line['restock_eligible'], FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * @param  list<int|string>  $orderItemIds
     * @param  list<string>  $statuses
     * @return Collection<int, int>
     */
    private function returnQuantities(array $orderItemIds, array $statuses, ?int $excludeReturnId = null): Collection
    {
        if ($orderItemIds === []) {
            return collect();
        }

        return SalesReturnItem::query()
            ->select('sales_order_item_id')
            ->selectRaw('SUM(quantity_returned) AS quantity_total')
            ->whereIn('sales_order_item_id', $orderItemIds)
            ->whereHas('salesReturn', function (Builder $query) use ($statuses, $excludeReturnId): void {
                $query->whereIn('status', $statuses);
                if ($excludeReturnId !== null) {
                    $query->where('id', '!=', $excludeReturnId);
                }
            })
            ->groupBy('sales_order_item_id')
            ->pluck('quantity_total', 'sales_order_item_id');
    }

    private function ensureConfirmedOrder(SalesOrder $salesOrder): void
    {
        if ($salesOrder->status !== 'confirmed') {
            throw ValidationException::withMessages(['sales_order_id' => 'Returns can only be recorded against confirmed sales orders.']);
        }
    }

    private function ensureDraft(SalesReturn $salesReturn): void
    {
        if ($salesReturn->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft returns can be changed or completed.']);
        }
    }

    /**
     * @return list<string>
     */
    private function returnRelations(): array
    {
        return ['salesOrder.customer', 'warehouse', 'creator', 'completer', 'items.product', 'items.salesOrderItem'];
    }
}
