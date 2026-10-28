<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $admin = $user?->hasRole('Administrator') ?? false;
        $assigned = ! $user?->hasRole('Warehouse Staff') || $user->warehouses()->whereKey($this->warehouse_id)->exists();
        $can = static fn (string $permission): bool => $user?->can($permission) ?? false;

        return [
            'id' => $this->id,
            'purchase_number' => $this->purchase_number,
            'status' => $this->status,
            'order_date' => $this->order_date?->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'grand_total' => $this->grand_total,
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'approved_at' => $this->approved_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'supplier' => $this->whenLoaded('supplier', fn (): array => [
                'id' => $this->supplier->id, 'name' => $this->supplier->name, 'company_name' => $this->supplier->company_name,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn (): array => ['id' => $this->warehouse->id, 'name' => $this->warehouse->name]),
            'creator' => $this->whenLoaded('creator', fn (): array => ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'approver' => $this->whenLoaded('approver', fn (): ?array => $this->approver ? [
                'id' => $this->approver->id, 'name' => $this->approver->name,
            ] : null),
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product' => $item->product ? [
                    'id' => $item->product->id, 'sku' => $item->product->sku,
                    'name' => $item->product->name, 'unit' => $item->product->unit,
                ] : null,
                'ordered_quantity' => $item->ordered_quantity,
                'received_quantity' => $item->received_quantity,
                'remaining_quantity' => $item->ordered_quantity - $item->received_quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->all()),
            'receipts' => $this->whenLoaded('receipts', fn (): array => $this->receipts->map(fn ($receipt): array => [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'received_at' => $receipt->received_at?->toISOString(),
                'warehouse' => $receipt->warehouse ? ['id' => $receipt->warehouse->id, 'name' => $receipt->warehouse->name] : null,
                'receiver' => $receipt->receiver ? ['id' => $receipt->receiver->id, 'name' => $receipt->receiver->name] : null,
                'items' => $receipt->items->map(fn ($line): array => [
                    'purchase_item_id' => $line->purchase_item_id,
                    'product_name' => $line->purchaseItem?->product?->name,
                    'quantity_received' => $line->quantity_received,
                ])->all(),
            ])->all()),
            'allowed_actions' => [
                'edit' => $this->status === 'draft' && $can('purchases.update'),
                'submit' => $this->status === 'draft' && $can('purchases.submit'),
                'approve' => $this->status === 'pending_approval' && $can('purchases.approve')
                    && ($admin || (int) $this->created_by !== (int) ($user?->id ?? 0)),
                'reject' => $this->status === 'pending_approval' && $can('purchases.approve'),
                'cancel' => in_array($this->status, ['draft', 'pending_approval', 'approved'], true)
                    && $can('purchases.cancel') && (int) ($this->receipts_count ?? 0) === 0,
                'receive' => in_array($this->status, ['approved', 'partially_received'], true) && $assigned && $can('purchases.receive'),
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
