<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'status' => $this->status,
            'order_date' => $this->order_date?->toDateString(),
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'grand_total' => $this->grand_total,
            'notes' => $this->notes,
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'customer' => $this->whenLoaded('customer', fn (): array => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn (): array => ['id' => $this->warehouse->id, 'name' => $this->warehouse->name]),
            'creator' => $this->whenLoaded('creator', fn (): array => ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'confirmer' => $this->whenLoaded('confirmer', fn (): ?array => $this->confirmer ? ['id' => $this->confirmer->id, 'name' => $this->confirmer->name] : null),
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(function ($item): array {
                $returnItems = $item->relationLoaded('returnItems') ? $item->returnItems : collect();
                $returned = (int) $returnItems->filter(fn ($returnItem): bool => $returnItem->salesReturn?->status === 'completed')->sum('quantity_returned');
                $reserved = (int) $returnItems->filter(fn ($returnItem): bool => in_array($returnItem->salesReturn?->status, ['draft', 'completed'], true))->sum('quantity_returned');

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => $item->product ? ['id' => $item->product->id, 'sku' => $item->product->sku, 'name' => $item->product->name, 'unit' => $item->product->unit] : null,
                    'quantity' => $item->quantity,
                    'returned_quantity' => $returned,
                    'reserved_return_quantity' => max(0, $reserved - $returned),
                    'returnable_quantity' => max(0, (int) $item->quantity - $reserved),
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ];
            })->all()),
            'returns' => $this->whenLoaded('returns', fn (): array => $this->returns->map(fn ($return): array => [
                'id' => $return->id,
                'return_number' => $return->return_number,
                'status' => $return->status,
                'return_date' => $return->return_date?->toDateString(),
                'quantity_total' => (int) ($return->quantity_total ?? $return->items->sum('quantity_returned')),
            ])->all()),
            'allowed_actions' => [
                'edit' => $this->status === 'draft' && $can('sales.update'),
                'confirm' => $this->status === 'draft' && $can('sales.confirm'),
                'cancel' => $this->status === 'draft' && $can('sales.cancel'),
                'create_return' => $this->status === 'confirmed' && $can('returns.create'),
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
