<?php

namespace Database\Seeders;

use App\Actions\ConfirmSalesOrderAction;
use App\Jobs\RefreshInventoryCache;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryVersionStore;
use App\Services\PurchaseCalculator;
use App\Services\PurchaseStatusService;
use App\Services\ReceivePurchaseAction;
use App\Services\SalesOrderCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DashboardHistorySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Dashboard history may only be seeded in local or testing environments.');
        }

        $products = Product::query()->where('status', 'active')->orderBy('id')->limit(8)->get();
        $suppliers = Supplier::query()->where('status', 'active')->orderBy('id')->get();
        $warehouses = Warehouse::query()->where('status', 'active')
            ->whereIn('name', ['Main Central Warehouse', 'North Distribution Center', 'Warehouse A', 'Warehouse B'])
            ->orderBy('id')->get();

        if ($products->count() < 2 || $suppliers->isEmpty() || $warehouses->isEmpty()) {
            throw new RuntimeException('Seed active products, suppliers and warehouses before dashboard history.');
        }

        $administrator = $this->userForRole('Administrator');
        $manager = $this->userForRole('Manager');
        $today = CarbonImmutable::today('Asia/Karachi');
        $dispatcher = Bus::getFacadeRoot();
        $createdPairs = 0;

        Bus::fake([RefreshInventoryCache::class]);

        try {
            $createdPairs = DB::transaction(function () use ($products, $suppliers, $warehouses, $administrator, $manager, $today): int {
                $created = 0;
                $customers = $this->customers()->where('status', 'active')->values();

                if ($customers->isEmpty()) {
                    throw new RuntimeException('At least one active sample customer is required for dashboard history.');
                }

                foreach (range(5, 0) as $monthsAgo) {
                    $month = $today->startOfMonth()->subMonths($monthsAgo);

                    foreach ($warehouses as $warehouseIndex => $warehouse) {
                        $key = $month->format('Ym').'-'.str_pad((string) $warehouse->id, 4, '0', STR_PAD_LEFT);

                        if (Purchase::query()->where('purchase_number', 'PO-LHR-'.$key)->exists()
                            || SalesOrder::query()->where('order_number', 'SO-LHR-'.$key)->exists()) {
                            continue;
                        }

                        $maximumDay = $month->isSameMonth($today) ? $today->day : $month->daysInMonth;
                        $purchaseDate = $month->day(min(5 + $warehouseIndex * 3, max(1, $maximumDay - 2)));
                        $saleDate = $month->day(min($purchaseDate->day + 2, $maximumDay));
                        $purchaseItems = [];
                        $salesItems = [];

                        foreach (range(0, 1) as $lineIndex) {
                            $product = $products->get(((5 - $monthsAgo) + $warehouseIndex * 2 + $lineIndex) % $products->count());
                            $salesQuantity = 4 + (5 - $monthsAgo) * 2 + $warehouseIndex + $lineIndex;
                            $purchaseItems[] = [
                                'product_id' => $product->id,
                                'ordered_quantity' => $salesQuantity + (int) $product->minimum_stock + 25,
                                'unit_price' => $product->purchase_price,
                            ];
                            $salesItems[] = [
                                'product_id' => $product->id,
                                'quantity' => $salesQuantity,
                                'unit_price' => $product->selling_price,
                            ];
                        }

                        $this->receivePurchase(
                            $key, $warehouse, $suppliers->get($warehouseIndex % $suppliers->count()),
                            $purchaseDate, $purchaseItems, $manager, $administrator,
                        );
                        $this->confirmSale(
                            $key, $warehouse, $customers->get(((5 - $monthsAgo) * $warehouses->count() + $warehouseIndex) % $customers->count()),
                            $saleDate, $salesItems, $manager, $administrator,
                        );
                        $created++;
                    }
                }

                return $created;
            }, attempts: 3);
        } finally {
            Bus::swap($dispatcher);
        }

        if ($createdPairs > 0) {
            foreach ($warehouses as $warehouse) {
                app(InventoryVersionStore::class)->incrementForWarehouse((int) $warehouse->id);
            }
        }

        $this->command?->info("Added {$createdPairs} received purchases and {$createdPairs} confirmed sales across six months.");
    }

    private function userForRole(string $role): User
    {
        return User::query()->where('is_active', true)
            ->whereHas('roles', static fn (Builder $query): Builder => $query->where('name', $role))
            ->orderBy('id')->firstOrFail();
    }

    /**
     * @return Collection<int, Customer>
     */
    private function customers(): Collection
    {
        $names = [
            'Ali Ahmed', 'Sara Malik', 'Hassan Rafiq', 'Ayesha Khan', 'Omar Farooq',
            'Hira Saleem', 'Fahad Tariq', 'Mariam Saeed', 'Hamza Nadeem', 'Sana Aslam',
            'Usman Qureshi', 'Laiba Rauf', 'Zain Mahmood', 'Nida Khalid', 'Danish Javed',
        ];
        $addresses = ['Main Boulevard, Gulberg I, Lahore', 'Gulberg II, Lahore', 'MM Alam Road, Gulberg III, Lahore'];
        $customers = new Collection;

        foreach ($names as $index => $name) {
            $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $customers->push(Customer::query()->firstOrCreate(
                ['email' => 'customer'.$number.'@example.test'],
                [
                    'name' => $name,
                    'phone' => '+92 300 555 04'.$number,
                    'address' => $addresses[$index % count($addresses)],
                    'status' => 'active',
                ],
            ));
        }

        return $customers;
    }

    /**
     * @param  list<array{product_id: int, ordered_quantity: int, unit_price: string}>  $items
     */
    private function receivePurchase(string $key, Warehouse $warehouse, Supplier $supplier, CarbonImmutable $date, array $items, User $manager, User $administrator): void
    {
        $totals = app(PurchaseCalculator::class)->calculate($items, '0.00');
        $purchase = Purchase::query()->create([
            'purchase_number' => 'PO-LHR-'.$key,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'order_date' => $date->toDateString(),
            'expected_date' => $date->addDays(2)->toDateString(),
            ...collect($totals)->except('items')->all(),
            'notes' => 'Monthly replenishment of office and warehouse equipment.',
            'created_by' => $manager->id,
        ]);
        $purchase->items()->createMany($totals['items']);
        $statuses = app(PurchaseStatusService::class);
        $statuses->transition($purchase, 'pending_approval', $manager);
        $statuses->transition($purchase, 'approved', $administrator);
        app(ReceivePurchaseAction::class)->execute($purchase, [
            'idempotency_key' => (string) Str::uuid(),
            'warehouse_id' => (int) $warehouse->id,
            'items' => $purchase->items()->get()->map(static fn ($item): array => [
                'purchase_item_id' => (int) $item->id,
                'quantity_received' => (int) $item->ordered_quantity,
            ])->all(),
        ], $administrator);
    }

    /**
     * @param  list<array{product_id: int, quantity: int, unit_price: string}>  $items
     */
    private function confirmSale(string $key, Warehouse $warehouse, Customer $customer, CarbonImmutable $date, array $items, User $manager, User $administrator): void
    {
        $totals = app(SalesOrderCalculator::class)->calculate($items, '0.00');
        $order = SalesOrder::query()->create([
            'order_number' => 'SO-LHR-'.$key,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'order_date' => $date->toDateString(),
            ...collect($totals)->except('items')->all(),
            'notes' => 'Office equipment supplied for scheduled customer orders.',
            'created_by' => $manager->id,
        ]);
        $order->items()->createMany($totals['items']);
        app(ConfirmSalesOrderAction::class)->execute($order, $administrator);
    }
}
