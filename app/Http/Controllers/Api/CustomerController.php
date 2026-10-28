<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\SalesOrderResource;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Customer::query()->withCount('salesOrders');
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn (Builder $builder) => $builder->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return CustomerResource::collection($query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString());
    }

    public function store(CustomerRequest $request): CustomerResource
    {
        return new CustomerResource(Customer::query()->create($request->validated()));
    }

    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer->loadCount('salesOrders'));
    }

    public function update(CustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->validated());

        return new CustomerResource($customer->refresh()->loadCount('salesOrders'));
    }

    public function orders(Customer $customer, Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $query = SalesOrder::query()->whereBelongsTo($customer)
            ->with(['customer:id,name,phone,email', 'warehouse:id,name', 'creator:id,name', 'confirmer:id,name', 'items.product:id,sku,name,unit', 'items.returnItems.salesReturn', 'returns.items', 'returns.items.product']);

        $this->scopeToAssignedWarehouses($query, $user);

        return SalesOrderResource::collection($query->orderByDesc('order_date')->orderByDesc('id')->paginate(15)->withQueryString());
    }

    private function scopeToAssignedWarehouses(Builder $query, User $user): void
    {
        if ($user->hasRole('Warehouse Staff')) {
            $query->whereIn('warehouse_id', $user->warehouses()->select('warehouses.id'));
        }
    }
}
