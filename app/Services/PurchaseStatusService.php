<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class PurchaseStatusService
{
    /**
     * Validate and apply one purchase status transition.
     */
    public function transition(Purchase $purchase, string $targetStatus, User $actor, ?string $reason = null): Purchase
    {
        $lockedPurchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->getKey());
        $transitions = [
            'draft' => ['pending_approval', 'cancelled'],
            'pending_approval' => ['approved', 'draft', 'cancelled'],
            'approved' => ['partially_received', 'received', 'cancelled'],
            'partially_received' => ['partially_received', 'received'],
            'received' => [],
            'cancelled' => [],
        ];

        if (! in_array($targetStatus, $transitions[$lockedPurchase->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "A purchase cannot move from {$lockedPurchase->status} to {$targetStatus}.",
            ]);
        }

        if ($targetStatus === 'pending_approval' && ! $lockedPurchase->items()->exists()) {
            throw ValidationException::withMessages(['items' => 'Add at least one item before submitting the purchase.']);
        }

        if (
            $targetStatus === 'approved'
            && (int) $lockedPurchase->created_by === (int) $actor->id
            && ! $actor->hasRole('Administrator')
        ) {
            throw new AuthorizationException('You cannot approve a purchase you created.');
        }

        if ($targetStatus === 'cancelled' && $lockedPurchase->receipts()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'A purchase with received stock cannot be cancelled.',
            ]);
        }

        if ($targetStatus === 'draft' && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'A rejection reason is required.']);
        }

        $attributes = ['status' => $targetStatus];

        if ($targetStatus === 'approved') {
            $attributes['approved_by'] = $actor->id;
            $attributes['approved_at'] = now();
            $attributes['rejection_reason'] = null;
        }

        if ($targetStatus === 'draft') {
            $attributes['rejection_reason'] = trim((string) $reason);
            $attributes['approved_by'] = null;
            $attributes['approved_at'] = null;
        }

        if ($targetStatus === 'cancelled') {
            $attributes['cancelled_at'] = now();
        }

        $lockedPurchase->update($attributes);

        return $lockedPurchase->refresh();
    }
}
