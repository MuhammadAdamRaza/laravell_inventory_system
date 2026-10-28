<?php

namespace App\Http\Controllers\Api;

use App\Actions\ConfirmSalesOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalesOrderRequest;
use App\Http\Resources\SalesOrderResource;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\SalesOrderConfirmed;
use App\Services\SalesOrderCalculator;
use App\Services\SalesStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesOrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'confirmed', 'cancelled'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', Rule::in(['order_date', 'grand_total'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = SalesOrder::query()->with(['customer:id,name,phone,email', 'warehouse:id,name', 'creator:id,name', 'confirmer:id,name'])
            ->withCount('items');
        $this->scopeToAssignedWarehouses($query, $request->user());

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn (Builder $builder) => $builder->where('order_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->where('name', 'like', "%{$search}%")));
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

        return SalesOrderResource::collection(
            $query->orderBy($filters['sort_by'] ?? 'order_date', $filters['sort_direction'] ?? 'desc')
                ->orderByDesc('id')->paginate(15)->withQueryString()
        );
    }

    public function options(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => [
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'phone', 'email']),
            'warehouses' => Warehouse::query()->where('status', 'active')->accessibleBy($user)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(500)->get(['id', 'sku', 'name', 'unit', 'selling_price']),
            'tax_rate' => (string) config('sales.tax_rate', '0.00'),
        ]]);
    }

    public function store(SalesOrderRequest $request, SalesOrderCalculator $calculator): JsonResponse
    {
        $data = $request->validated();
        $totals = $calculator->calculate($data['items'], $data['discount_amount'] ?? '0');
        $order = DB::transaction(function () use ($request, $data, $totals): SalesOrder {
            $order = SalesOrder::query()->create([
                'order_number' => 'SO-TMP-'.Str::lower(Str::random(12)),
                'customer_id' => $data['customer_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => 'draft',
                'order_date' => $data['order_date'],
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'grand_total' => $totals['grand_total'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $order->update(['order_number' => sprintf('SO-%06d', $order->id)]);
            $order->items()->createMany($totals['items']);

            return $order;
        });

        return (new SalesOrderResource($order->load(['customer', 'warehouse', 'creator', 'items.product'])))
            ->response()->setStatusCode(201);
    }

    public function show(SalesOrder $salesOrder, Request $request): SalesOrderResource
    {
        $this->ensureWarehouseAccess($salesOrder, $request->user());

        return new SalesOrderResource($salesOrder->load(['customer', 'warehouse', 'creator', 'confirmer', 'items.product', 'items.returnItems.salesReturn', 'returns.items', 'returns.items.product', 'returns.completion', 'returns.completion']));
    }

    public function update(SalesOrderRequest $request, SalesOrder $salesOrder, SalesOrderCalculator $calculator): SalesOrderResource
    {
        $data = $request->validated();
        $totals = $calculator->calculate($data['items'], $data['discount_amount'] ?? '0');
        $updated = DB::transaction(function () use ($salesOrder, $data, $totals): SalesOrder {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($salesOrder->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft sales orders can be edited.']);
            }
            $locked->update([
                'customer_id' => $data['customer_id'], 'warehouse_id' => $data['warehouse_id'],
                'order_date' => $data['order_date'], 'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'], 'tax_amount' => $totals['tax_amount'],
                'grand_total' => $totals['grand_total'], 'notes' => $data['notes'] ?? null,
            ]);
            $locked->items()->delete();
            $locked->items()->createMany($totals['items']);

            return $locked;
        });

        return new SalesOrderResource($updated->refresh()->load(['customer', 'warehouse', 'creator', 'items.product']));
    }

    public function confirm(SalesOrder $salesOrder, Request $request, ConfirmSalesOrderAction $confirmation): SalesOrderResource
    {
        $this->ensureWarehouseAccess($salesOrder, $request->user());
        $confirmed = $confirmation->execute($salesOrder, $request->user());
        $confirmed->creator->notify((new SalesOrderConfirmed($confirmed))->afterCommit());

        return new SalesOrderResource($confirmed);
    }

    public function cancel(SalesOrder $salesOrder, Request $request, SalesStatusService $statuses): SalesOrderResource
    {
        $this->ensureWarehouseAccess($salesOrder, $request->user());
        $cancelled = DB::transaction(
            fn (): SalesOrder => $statuses->transition($salesOrder, 'cancelled'),
            attempts: 3,
        );

        return new SalesOrderResource($cancelled->load(['customer', 'warehouse', 'creator', 'items.product']));
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('status', 'active')],
            'product_ids' => ['required', 'array', 'min:1', 'max:500'],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('status', 'active')],
        ]);
        /** @var User $user */
        $user = $request->user();
        $warehouse = Warehouse::query()->whereKey($data['warehouse_id'])->where('status', 'active')->firstOrFail();
        if ($user->hasRole('Warehouse Staff') && ! $user->warehouses()->whereKey($warehouse->id)->exists()) {
            abort(403, 'You are not assigned to this warehouse.');
        }

        $balances = InventoryBalance::query()->where('warehouse_id', $warehouse->id)
            ->whereIn('product_id', $data['product_ids'])->pluck('quantity', 'product_id');
        $products = Product::query()->whereIn('id', $data['product_ids'])->get(['id', 'minimum_stock'])->keyBy('id');

        return response()->json(['data' => collect($data['product_ids'])->map(fn (int $productId): array => [
            'product_id' => $productId,
            'warehouse_id' => (int) $warehouse->id,
            'available_quantity' => (int) ($balances[$productId] ?? 0),
            'minimum_stock' => (int) $products[$productId]->minimum_stock,
        ])->values()]);
    }

    private function scopeToAssignedWarehouses(Builder $query, User $user): void
    {
        if ($user->hasRole('Warehouse Staff')) {
            $query->whereIn('warehouse_id', $user->warehouses()->select('warehouses.id'));
        }
    }

    private function ensureWarehouseAccess(SalesOrder $salesOrder, User $user): void
    {
        if ($user->hasRole('Warehouse Staff') && ! $user->warehouses()->whereKey($salesOrder->warehouse_id)->exists()) {
            abort(404);
        }
    }
}
