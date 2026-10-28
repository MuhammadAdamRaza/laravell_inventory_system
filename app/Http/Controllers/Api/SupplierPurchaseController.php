<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierPaymentRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\MoneyAmount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

class SupplierPurchaseController extends Controller
{
    public function index(Supplier $supplier, Request $request): AnonymousResourceCollection
    {
        $query = $supplier->purchases()->with(['supplier', 'warehouse'])->withCount('receipts');
        $this->scopeToAssignedWarehouses($query, $request->user());

        return PurchaseResource::collection($query->latest('order_date')->paginate(15)->withQueryString());
    }

    public function summary(Supplier $supplier): JsonResponse
    {
        $purchases = $supplier->purchases()->where('status', '!=', 'cancelled');
        $totalPurchases = $purchases->count();
        $totalAmountCents = 0;

        $purchases->select(['id', 'grand_total'])->chunkById(500, function (Collection $items) use (&$totalAmountCents): void {
            foreach ($items as $purchase) {
                $totalAmountCents = MoneyAmount::add($totalAmountCents, MoneyAmount::toCents($purchase->grand_total));
            }
        });

        $totalPaidCents = 0;
        $supplier->payments()->select(['id', 'amount'])->chunkById(500, function (Collection $payments) use (&$totalPaidCents): void {
            foreach ($payments as $payment) {
                $totalPaidCents = MoneyAmount::add($totalPaidCents, MoneyAmount::toCents($payment->amount));
            }
        });

        $outstandingCents = $totalAmountCents - $totalPaidCents;

        return response()->json(['data' => [
            'total_purchases' => $totalPurchases,
            'total_amount' => MoneyAmount::asDecimal($totalAmountCents),
            'total_paid' => MoneyAmount::asDecimal($totalPaidCents),
            'outstanding_balance' => MoneyAmount::asDecimal($outstandingCents),
        ]]);
    }

    public function storePayment(SupplierPaymentRequest $request): JsonResponse
    {
        $payment = SupplierPayment::query()->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return response()->json([
            'message' => 'Supplier payment recorded successfully.',
            'data' => [
                'id' => $payment->id,
                'supplier_id' => $payment->supplier_id,
                'amount' => $payment->amount,
                'payment_date' => $payment->payment_date?->toDateString(),
                'reference' => $payment->reference,
            ],
        ], 201);
    }

    private function scopeToAssignedWarehouses(Builder $query, User $user): void
    {
        if ($user->hasRole('Warehouse Staff')) {
            $query->whereIn('warehouse_id', $user->warehouses()->select('warehouses.id'));
        }
    }
}
