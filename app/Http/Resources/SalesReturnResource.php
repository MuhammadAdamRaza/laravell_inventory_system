<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $can = static fn (string $permission): bool => $user?->can($permission) ?? false;

        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'sales_order_id' => $this->sales_order_id,
            'sales_order' => $this->whenLoaded('salesOrder', fn (): ?array => $this->salesOrder ? [
                'id' => $this->salesOrder->id,
                'order_number' => $this->salesOrder->order_number,
                'customer' => $this->salesOrder->customer ? [
                    'id' => $this->salesOrder->customer->id,
                    'name' => $this->salesOrder->customer->name,
                ] : null,
            ] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn (): ?array => $this->warehouse ? [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ] : null),
            'status' => $this->status,
            'return_date' => $this->return_date?->toDateString(),
            'reason_summary' => $this->reason_summary,
            'quantity_total' => (int) ($this->quantity_total ?? $this->items->sum('quantity_returned')),
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'sales_order_item_id' => $item->sales_order_item_id,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'sku' => $item->product->sku,
                    'name' => $item->product->name,
                    'unit' => $item->product->unit,
                ] : null,
                'quantity_returned' => (int) $item->quantity_returned,
                'reason' => $item->reason,
                'condition' => $item->condition,
                'restock_eligible' => (bool) $item->restock_eligible,
            ])->all()),
            'creator' => $this->whenLoaded('creator', fn (): ?array => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null),
            'completer' => $this->whenLoaded('completer', fn (): ?array => $this->completer ? ['id' => $this->completer->id, 'name' => $this->completer->name] : null),
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'allowed_actions' => [
                'edit' => $this->status === 'draft' && $can('returns.update'),
                'complete' => $this->status === 'draft' && $can('returns.complete'),
                'cancel' => $this->status === 'draft' && $can('returns.cancel'),
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
