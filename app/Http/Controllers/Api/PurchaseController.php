<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseReceiptRequest;
use App\Http\Requests\PurchaseRejectRequest;
use App\Http\Requests\PurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\PurchaseApproved;
use App\Notifications\PurchaseSubmitted;
use App\Services\PurchaseCalculator;
use App\Services\PurchaseStatusService;
use App\Services\ReceivePurchaseAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'pending_approval', 'approved', 'partially_received', 'received', 'cancelled'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', Rule::in(['order_date', 'grand_total', 'created_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Purchase::query()->with(['supplier:id,name,company_name', 'warehouse:id,name'])->withCount('receipts');
        $this->scopeToAssignedWarehouses($query, $request->user());

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('purchase_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function (Builder $supplierQuery) use ($search): void {
                        $supplierQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%");
                    });
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('order_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('order_date', '<=', $filters['date_to']);
        }

        return PurchaseResource::collection(
            $query->orderBy($filters['sort_by'] ?? 'order_date', $filters['sort_direction'] ?? 'desc')
                ->paginate(15)->withQueryString()
        );
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'company_name']),
            'warehouses' => Warehouse::query()->where('status', 'active')->accessibleBy($user)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(500)
                ->get(['id', 'sku', 'name', 'unit', 'purchase_price']),
            'tax_rate' => (string) config('purchases.tax_rate', '0.00'),
        ]]);
    }

    public function store(PurchaseRequest $request, PurchaseCalculator $calculator): JsonResponse
    {
        $data = $request->validated();
        $calculation = $calculator->calculate($data['items'], $data['discount_amount'] ?? '0');

        $purchase = DB::transaction(function () use ($data, $calculation, $request): Purchase {
            $purchase = Purchase::query()->create([
                'purchase_number' => 'PO-TMP-'.Str::lower(Str::random(12)),
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => 'draft',
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'subtotal' => $calculation['subtotal'],
                'discount_amount' => $calculation['discount_amount'],
                'tax_amount' => $calculation['tax_amount'],
                'grand_total' => $calculation['grand_total'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $purchase->update(['purchase_number' => sprintf('PO-%06d', $purchase->id)]);
            $purchase->items()->createMany($calculation['items']);

            return $purchase;
        });

        $purchase->load(['supplier', 'warehouse', 'creator', 'items.product'])->loadCount('receipts');

        return (new PurchaseResource($purchase))->response()->setStatusCode(201);
    }

    public function show(Purchase $purchase, Request $request): PurchaseResource
    {
        $this->ensureWarehouseAccess($purchase, $request->user());

        return new PurchaseResource($purchase->load([
            'supplier', 'warehouse', 'creator', 'approver', 'items.product',
            'receipts.warehouse', 'receipts.receiver', 'receipts.items.purchaseItem.product',
        ])->loadCount('receipts'));
    }

    public function update(PurchaseRequest $request, Purchase $purchase, PurchaseCalculator $calculator): PurchaseResource
    {
        $data = $request->validated();
        $calculation = $calculator->calculate($data['items'], $data['discount_amount'] ?? '0');

        $purchase = DB::transaction(function () use ($purchase, $data, $calculation): Purchase {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft purchases can be edited.']);
            }
            $locked->update([
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'subtotal' => $calculation['subtotal'],
                'discount_amount' => $calculation['discount_amount'],
                'tax_amount' => $calculation['tax_amount'],
                'grand_total' => $calculation['grand_total'],
                'notes' => $data['notes'] ?? null,
            ]);
            $locked->items()->delete();
            $locked->items()->createMany($calculation['items']);

            return $locked;
        });

        return new PurchaseResource($purchase->refresh()->load(['supplier', 'warehouse', 'creator', 'items.product'])->loadCount('receipts'));
    }

    public function submit(Purchase $purchase, Request $request, PurchaseStatusService $statuses): PurchaseResource
    {
        $actor = $request->user();
        $submitted = $this->transitionModel($purchase, 'pending_approval', $actor, $statuses)
            ->load(['supplier', 'warehouse', 'creator', 'items.product'])
            ->loadCount('receipts');

        $approvers = User::role(['Administrator', 'Manager'])
            ->where('is_active', true)
            ->where('id', '!=', $actor->id)
            ->get();

        Notification::send($approvers, (new PurchaseSubmitted($submitted))->afterCommit());

        return new PurchaseResource($submitted);
    }

    public function approve(Purchase $purchase, Request $request, PurchaseStatusService $statuses): PurchaseResource
    {
        $approved = $this->transitionModel($purchase, 'approved', $request->user(), $statuses)
            ->load(['supplier', 'warehouse', 'creator', 'approver', 'items.product'])
            ->loadCount('receipts');
        $creator = User::query()->where('is_active', true)->whereKey($approved->created_by)->get();
        $assignedWarehouseStaff = User::role('Warehouse Staff')
            ->where('is_active', true)
            ->whereHas('warehouses', fn (Builder $query): Builder => $query->whereKey($approved->warehouse_id))
            ->get();

        Notification::send(
            $creator->merge($assignedWarehouseStaff)->unique('id'),
            (new PurchaseApproved($approved))->afterCommit(),
        );

        return new PurchaseResource($approved);
    }

    public function reject(PurchaseRejectRequest $request, Purchase $purchase, PurchaseStatusService $statuses): PurchaseResource
    {
        return $this->transition($purchase, 'draft', $statuses, $request->user(), $request->validated('reason'));
    }

    public function cancel(Purchase $purchase, Request $request, PurchaseStatusService $statuses): PurchaseResource
    {
        return $this->transition($purchase, 'cancelled', $statuses, $request->user());
    }

    public function receive(
        PurchaseReceiptRequest $request,
        Purchase $purchase,
        ReceivePurchaseAction $receivePurchase
    ): JsonResponse {
        $this->ensureWarehouseAccess($purchase, $request->user());
        $result = $receivePurchase->execute($purchase, $request->validated(), $request->user());
        $purchase->refresh()->load([
            'supplier', 'warehouse', 'creator', 'approver', 'items.product',
            'receipts.warehouse', 'receipts.receiver', 'receipts.items.purchaseItem.product',
        ])->loadCount('receipts');

        return response()->json([
            'message' => $result['created'] ? 'Stock receipt recorded successfully.' : 'This receipt was already recorded.',
            'receipt' => [
                'id' => $result['receipt']->id,
                'receipt_number' => $result['receipt']->receipt_number,
                'received_at' => $result['receipt']->received_at?->toISOString(),
                'created' => $result['created'],
            ],
            'data' => new PurchaseResource($purchase),
        ], $result['created'] ? 201 : 200);
    }

    private function transition(
        Purchase $purchase,
        string $targetStatus,
        PurchaseStatusService $statuses,
        User $actor,
        ?string $reason = null
    ): PurchaseResource {
        $model = $this->transitionModel($purchase, $targetStatus, $actor, $statuses, $reason)
            ->load(['supplier', 'warehouse', 'creator', 'approver', 'items.product'])
            ->loadCount('receipts');

        return new PurchaseResource($model);
    }

    private function transitionModel(
        Purchase $purchase,
        string $targetStatus,
        User $actor,
        PurchaseStatusService $statuses,
        ?string $reason = null
    ): Purchase {
        return DB::transaction(
            fn (): Purchase => $statuses->transition($purchase, $targetStatus, $actor, $reason),
            attempts: 3
        );
    }

    private function scopeToAssignedWarehouses(Builder $query, User $user): void
    {
        if ($user->hasRole('Warehouse Staff')) {
            $query->whereIn('warehouse_id', $user->warehouses()->select('warehouses.id'));
        }
    }

    private function ensureWarehouseAccess(Purchase $purchase, User $user): void
    {
        if ($user->hasRole('Warehouse Staff') && ! $user->warehouses()->whereKey($purchase->warehouse_id)->exists()) {
            abort(404);
        }
    }
}
