<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteSalesReturnRequest;
use App\Http\Requests\StoreSalesReturnRequest;
use App\Http\Requests\UpdateSalesReturnRequest;
use App\Http\Resources\SalesReturnResource;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\User;
use App\Services\SalesReturnStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SalesReturnController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'completed', 'cancelled'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        /** @var User $user */
        $user = $request->user();

        $query = SalesReturn::query()->with([
            'salesOrder:id,order_number,customer_id',
            'salesOrder.customer:id,name',
            'warehouse:id,name',
            'creator:id,name',
            'completer:id,name',
            'items.product:id,sku,name,unit',
        ])->withSum('items as quantity_total', 'quantity_returned');
        $this->scopeToAssignedWarehouses($query, $user);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn (Builder $builder) => $builder->where('return_number', 'like', "%{$search}%")
                ->orWhereHas('salesOrder', fn (Builder $order) => $order->where('order_number', 'like', "%{$search}%"))
                ->orWhereHas('salesOrder.customer', fn (Builder $customer) => $customer->where('name', 'like', "%{$search}%")));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('return_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('return_date', '<=', $filters['date_to']);
        }

        return SalesReturnResource::collection($query->orderByDesc('return_date')->orderByDesc('id')->paginate(15)->withQueryString());
    }

    public function store(StoreSalesReturnRequest $request, SalesReturnStatusService $returns): JsonResponse
    {
        $data = $request->validated();
        $salesOrder = SalesOrder::query()->findOrFail($data['sales_order_id']);
        $this->ensureWarehouseAccess($salesOrder, $request->user());
        $salesReturn = $returns->create($salesOrder, $data, $request->user());

        return (new SalesReturnResource($salesReturn))->response()->setStatusCode(201);
    }

    public function returnableItems(Request $request, SalesOrder $salesOrder, SalesReturnStatusService $returns): JsonResponse
    {
        $this->ensureWarehouseAccess($salesOrder, $request->user());
        $data = $request->validate([
            'exclude_return_id' => ['nullable', 'integer', 'exists:sales_returns,id'],
        ]);
        $excludeReturn = isset($data['exclude_return_id'])
            ? SalesReturn::query()->whereKey($data['exclude_return_id'])->firstOrFail()
            : null;

        return response()->json(['data' => $returns->returnableItems($salesOrder, $excludeReturn)]);
    }

    public function show(SalesReturn $salesReturn, Request $request): SalesReturnResource
    {
        $this->ensureWarehouseAccess($salesReturn, $request->user());

        return new SalesReturnResource($salesReturn->load([
            'salesOrder.customer', 'warehouse', 'creator', 'completer',
            'items.product', 'items.salesOrderItem', 'completion',
        ]));
    }

    public function update(UpdateSalesReturnRequest $request, SalesReturn $salesReturn, SalesReturnStatusService $returns): SalesReturnResource
    {
        $this->ensureWarehouseAccess($salesReturn, $request->user());

        return new SalesReturnResource($returns->update($salesReturn, $request->validated()));
    }

    public function complete(CompleteSalesReturnRequest $request, SalesReturn $salesReturn, SalesReturnStatusService $returns): SalesReturnResource
    {
        $this->ensureWarehouseAccess($salesReturn, $request->user());
        $result = $returns->complete($salesReturn, $request->validated('idempotency_key'), $request->user());

        return new SalesReturnResource($result['sales_return']);
    }

    public function cancel(SalesReturn $salesReturn, Request $request, SalesReturnStatusService $returns): SalesReturnResource
    {
        $this->ensureWarehouseAccess($salesReturn, $request->user());
        $cancelled = DB::transaction(fn (): SalesReturn => $returns->transition($salesReturn, 'cancelled', $request->user()), attempts: 3);

        return new SalesReturnResource($cancelled->load(['salesOrder.customer', 'warehouse', 'creator', 'items.product']));
    }

    private function scopeToAssignedWarehouses(Builder $query, User $user): void
    {
        if ($user->hasRole('Warehouse Staff')) {
            $query->whereIn('warehouse_id', $user->warehouses()->select('warehouses.id'));
        }
    }

    private function ensureWarehouseAccess(SalesReturn|SalesOrder $record, User $user): void
    {
        $warehouseId = $record instanceof SalesReturn ? $record->warehouse_id : $record->warehouse_id;
        if ($user->hasRole('Warehouse Staff') && ! $user->warehouses()->whereKey($warehouseId)->exists()) {
            abort(404);
        }
    }
}
