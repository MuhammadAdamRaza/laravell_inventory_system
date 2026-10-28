<?php

namespace App\Http\Controllers\Api;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransferReceiptRequest;
use App\Http\Requests\WarehouseTransferRequest;
use App\Http\Resources\WarehouseTransferResource;
use App\Jobs\RefreshInventoryCache;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferReceipt;
use App\Services\InventoryService;
use App\Services\TransferStatusService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WarehouseTransferController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'in_transit', 'completed', 'cancelled'])],
            'source_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', Rule::in(['created_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = WarehouseTransfer::query()->with(['sourceWarehouse:id,name', 'destinationWarehouse:id,name', 'creator:id,name', 'dispatcher:id,name', 'receiver:id,name']);
        $this->scopeToAssignedWarehouses($query, $request->user());
        if (! empty($filters['search'])) {
            $query->where('transfer_number', 'like', '%'.$filters['search'].'%');
        }
        foreach (['status', 'source_warehouse_id', 'destination_warehouse_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return WarehouseTransferResource::collection($query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_direction'] ?? 'desc')->orderByDesc('id')->paginate(15)->withQueryString());
    }

    public function options(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => [
            'warehouses' => Warehouse::query()->where('status', 'active')->accessibleBy($user)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(500)->get(['id', 'sku', 'name', 'unit']),
        ]]);
    }

    public function store(WarehouseTransferRequest $request): JsonResponse
    {
        $data = $request->validated();
        $transfer = DB::transaction(function () use ($request, $data): WarehouseTransfer {
            $transfer = WarehouseTransfer::query()->create([
                'transfer_number' => 'TR-TMP-'.Str::lower(Str::random(12)),
                'source_warehouse_id' => $data['source_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $transfer->update(['transfer_number' => sprintf('TR-%06d', $transfer->id)]);
            $transfer->items()->createMany($data['items']);

            return $transfer;
        });

        return (new WarehouseTransferResource($transfer->load($this->relations())))->response()->setStatusCode(201);
    }

    public function show(WarehouseTransfer $transfer, Request $request): WarehouseTransferResource
    {
        $this->ensureWarehouseAccess($transfer, $request->user());

        return new WarehouseTransferResource($transfer->load($this->relations()));
    }

    public function update(WarehouseTransferRequest $request, WarehouseTransfer $transfer): WarehouseTransferResource
    {
        $data = $request->validated();
        $updated = DB::transaction(function () use ($transfer, $data): WarehouseTransfer {
            $locked = WarehouseTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft transfers can be edited.']);
            }
            $locked->update([
                'source_warehouse_id' => $data['source_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'notes' => $data['notes'] ?? null,
            ]);
            $locked->items()->delete();
            $locked->items()->createMany($data['items']);

            return $locked;
        });

        return new WarehouseTransferResource($updated->refresh()->load($this->relations()));
    }

    public function dispatch(
        WarehouseTransfer $transfer,
        Request $request,
        InventoryService $inventory,
        TransferStatusService $statuses,
    ): WarehouseTransferResource {
        $this->ensureDispatchAccess($transfer, $request->user());
        $dispatched = DB::transaction(function () use ($transfer, $request, $inventory, $statuses): WarehouseTransfer {
            $locked = WarehouseTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft transfers can be dispatched.']);
            }
            $items = $locked->items()->orderBy('product_id')->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'A transfer must contain at least one item.']);
            }
            foreach ($items as $item) {
                $inventory->adjustStock((int) $item->product_id, (int) $locked->source_warehouse_id, -((int) $item->dispatched_quantity), MovementType::TransferOut, null, (int) $request->user()->id, $locked);
            }
            $updated = $statuses->transition($locked, 'in_transit', $request->user());
            DB::afterCommit(static function () use ($items, $locked): void {
                RefreshInventoryCache::dispatch($items->pluck('product_id')->map(static fn ($id): int => (int) $id)->all(), (int) $locked->source_warehouse_id);
            });

            return $updated;
        }, attempts: 3);

        return new WarehouseTransferResource($dispatched->load($this->relations()));
    }

    public function receive(
        TransferReceiptRequest $request,
        WarehouseTransfer $transfer,
        InventoryService $inventory,
        TransferStatusService $statuses,
    ): JsonResponse {
        $data = $request->validated();
        $this->ensureReceiveAccess($transfer, $request->user());
        $existing = WarehouseTransferReceipt::query()->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            abort_unless($existing->warehouse_transfer_id === $transfer->id, 409, 'This idempotency key belongs to another transfer.');

            return response()->json(['message' => 'This receipt was already recorded.', 'data' => new WarehouseTransferResource($transfer->load($this->relations())), 'receipt' => ['id' => $existing->id]], 200);
        }

        try {
            $result = DB::transaction(function () use ($data, $transfer, $request, $inventory, $statuses): array {
                $locked = WarehouseTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
                $existingReceipt = WarehouseTransferReceipt::query()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existingReceipt) {
                    abort_unless($existingReceipt->warehouse_transfer_id === $locked->id, 409, 'This idempotency key belongs to another transfer.');

                    return ['receipt' => $existingReceipt, 'transfer' => $locked, 'created' => false];
                }
                if ($locked->status !== 'in_transit') {
                    throw ValidationException::withMessages(['status' => 'Only in-transit transfers can be received.']);
                }
                if (! $request->user()->hasRole('Administrator') && (int) $locked->dispatched_by === (int) $request->user()->id) {
                    throw new AuthorizationException('The dispatcher cannot receive this transfer.');
                }

                $requested = collect($data['items'])->keyBy('product_id');
                $items = $locked->items()->with('product')->orderBy('product_id')->lockForUpdate()->get();
                if ($items->count() !== $requested->count()) {
                    throw ValidationException::withMessages(['items' => 'Provide a receipt line for every transfer item.']);
                }
                foreach ($items as $item) {
                    $line = $requested->get((string) $item->product_id) ?? $requested->get($item->product_id);
                    if (! $line) {
                        throw ValidationException::withMessages(['items' => 'Every received product must belong to this transfer.']);
                    }
                    if ((int) $line['accepted_quantity'] + (int) $line['returned_quantity'] !== (int) $item->dispatched_quantity) {
                        throw ValidationException::withMessages(['items' => "Accepted plus returned quantity must equal dispatched quantity for {$item->product->name}."]);
                    }
                    $accepted = (int) $line['accepted_quantity'];
                    $returned = (int) $line['returned_quantity'];
                    if ($accepted > 0) {
                        $inventory->adjustStock((int) $item->product_id, (int) $locked->destination_warehouse_id, $accepted, MovementType::TransferIn, null, (int) $request->user()->id, $locked);
                    }
                    if ($returned > 0) {
                        $inventory->adjustStock((int) $item->product_id, (int) $locked->source_warehouse_id, $returned, MovementType::TransferReturn, null, (int) $request->user()->id, $locked);
                    }
                    $item->update(['accepted_quantity' => $accepted, 'returned_quantity' => $returned]);
                }

                $receipt = $locked->receipts()->create([
                    'idempotency_key' => $data['idempotency_key'],
                    'received_by' => $request->user()->id,
                    'received_at' => now(),
                ]);
                $completed = $statuses->transition($locked, 'completed', $request->user());
                $productIds = $items->pluck('product_id')->map(static fn ($id): int => (int) $id)->all();
                DB::afterCommit(static function () use ($productIds, $locked): void {
                    RefreshInventoryCache::dispatch($productIds, (int) $locked->source_warehouse_id);
                    RefreshInventoryCache::dispatch($productIds, (int) $locked->destination_warehouse_id);
                });

                return ['receipt' => $receipt, 'transfer' => $completed, 'created' => true];
            }, attempts: 3);
        } catch (QueryException $exception) {
            $receipt = WarehouseTransferReceipt::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if (! $receipt) {
                throw $exception;
            }
            abort_unless($receipt->warehouse_transfer_id === $transfer->id, 409, 'This idempotency key belongs to another transfer.');
            $result = ['receipt' => $receipt, 'transfer' => $transfer, 'created' => false];
        }

        $result['transfer']->refresh()->load($this->relations());

        return response()->json([
            'message' => $result['created'] ? 'Transfer receipt recorded.' : 'This receipt was already recorded.',
            'receipt' => ['id' => $result['receipt']->id, 'idempotency_key' => $result['receipt']->idempotency_key],
            'data' => new WarehouseTransferResource($result['transfer']),
        ], $result['created'] ? 201 : 200);
    }

    public function cancel(WarehouseTransfer $transfer, Request $request, TransferStatusService $statuses): WarehouseTransferResource
    {
        $cancelled = DB::transaction(fn (): WarehouseTransfer => $statuses->transition($transfer, 'cancelled', $request->user()), attempts: 3);

        return new WarehouseTransferResource($cancelled->load($this->relations()));
    }

    private function relations(): array
    {
        return ['sourceWarehouse', 'destinationWarehouse', 'creator', 'dispatcher', 'receiver', 'items.product'];
    }

    private function scopeToAssignedWarehouses(Builder $query, User $user): void
    {
        if ($user->hasRole('Warehouse Staff')) {
            $ids = $user->warehouses()->select('warehouses.id');
            $query->where(fn (Builder $builder) => $builder->whereIn('source_warehouse_id', $ids)->orWhereIn('destination_warehouse_id', $ids));
        }
    }

    private function ensureWarehouseAccess(WarehouseTransfer $transfer, User $user): void
    {
        if ($user->hasRole('Warehouse Staff') && ! $user->warehouses()->whereIn('warehouses.id', [$transfer->source_warehouse_id, $transfer->destination_warehouse_id])->exists()) {
            abort(404);
        }
    }

    private function ensureDispatchAccess(WarehouseTransfer $transfer, User $user): void
    {
        if ($user->hasRole('Warehouse Staff') && ! $user->warehouses()->whereKey($transfer->source_warehouse_id)->exists()) {
            throw new AuthorizationException('You are not assigned to the source warehouse.');
        }
    }

    private function ensureReceiveAccess(WarehouseTransfer $transfer, User $user): void
    {
        if ($user->hasRole('Warehouse Staff') && ! $user->warehouses()->whereKey($transfer->destination_warehouse_id)->exists()) {
            throw new AuthorizationException('You are not assigned to the destination warehouse.');
        }
    }
}
