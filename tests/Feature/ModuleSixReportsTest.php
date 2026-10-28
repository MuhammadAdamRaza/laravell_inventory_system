<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CsvExporter;
use App\Services\ReportService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModuleSixReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_reports_are_read_only_and_use_the_required_purchase_sales_and_valuation_definitions(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $warehouse = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        $supplierId = (int) DB::table('suppliers')->value('id');
        $customerId = (int) DB::table('customers')->insertGetId([
            'name' => 'Report Customer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $date = now()->toDateString();

        $this->insertPurchase($manager, $warehouse, $supplierId, 'PUR-REPORT-001', 'received', '33.00', $date);
        $this->insertPurchase($manager, $warehouse, $supplierId, 'PUR-REPORT-002', 'cancelled', '999.00', $date);
        $orderId = DB::table('sales_orders')->insertGetId([
            'order_number' => 'SO-REPORT-001', 'customer_id' => $customerId, 'warehouse_id' => $warehouse->id,
            'status' => 'confirmed', 'order_date' => $date, 'subtotal' => '57.00', 'discount_amount' => '0.00',
            'tax_amount' => '0.00', 'grand_total' => '57.00', 'created_by' => $manager->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sales_order_items')->insert([
            'sales_order_id' => $orderId, 'product_id' => $product->id, 'quantity' => 2,
            'unit_price' => '28.50', 'line_total' => '57.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sales_orders')->insert([
            'order_number' => 'SO-REPORT-002', 'customer_id' => $customerId, 'warehouse_id' => $warehouse->id,
            'status' => 'draft', 'order_date' => $date, 'subtotal' => '800.00', 'discount_amount' => '0.00',
            'tax_amount' => '0.00', 'grand_total' => '800.00', 'created_by' => $manager->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 4]);
        DB::table('stock_movements')->insert([
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'movement_type' => 'opening_stock',
            'quantity_change' => 4, 'balance_after' => 4, 'user_id' => $manager->id, 'created_at' => now(),
        ]);

        $before = [
            'balances' => DB::table('inventory_balances')->count(),
            'movements' => DB::table('stock_movements')->count(),
            'purchases' => DB::table('purchases')->count(),
            'sales' => DB::table('sales_orders')->count(),
        ];

        $this->actingAs($manager)->getJson("/api/reports/purchases?from={$date}&to={$date}")
            ->assertOk()->assertJsonPath('summary.purchase_total', '33.00')->assertJsonPath('summary.purchase_count', 1);
        $this->getJson("/api/reports/sales?from={$date}&to={$date}")
            ->assertOk()->assertJsonPath('summary.sales_total', '57.00')->assertJsonPath('summary.sales_count', 1);
        $this->getJson("/api/reports/top-products?from={$date}&to={$date}")
            ->assertOk()->assertJsonPath('rows.data.0.units_sold', 2)->assertJsonPath('rows.data.0.sales_total', '57.00');
        $this->getJson('/api/reports/inventory-valuation')
            ->assertOk()->assertJsonPath('summary.total_value', '60.00');
        $this->getJson('/api/reports/dashboard')
            ->assertOk()->assertJsonPath('totals.purchases_total', '33.00')->assertJsonPath('totals.sales_total', '57.00');

        $this->assertSame($before, [
            'balances' => DB::table('inventory_balances')->count(),
            'movements' => DB::table('stock_movements')->count(),
            'purchases' => DB::table('purchases')->count(),
            'sales' => DB::table('sales_orders')->count(),
        ]);
    }

    public function test_warehouse_staff_are_scoped_and_cannot_export_while_viewers_can_view_dashboard(): void
    {
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $mainWarehouse = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $northWarehouse = Warehouse::where('name', 'North Distribution Center')->firstOrFail();

        $this->actingAs($staff)->getJson('/api/reports/dashboard')->assertOk()
            ->assertJsonPath('filters.warehouses.0.id', $mainWarehouse->id);
        $this->getJson('/api/reports/dashboard?warehouse_id='.$northWarehouse->id)->assertForbidden();
        $this->getJson('/api/reports/purchases/export')->assertForbidden();

        $viewer = User::where('email', 'viewer@example.com')->firstOrFail();
        $this->actingAs($viewer)->getJson('/api/reports/dashboard')->assertOk();
        $this->getJson('/api/reports/sales/export')->assertForbidden();
    }

    public function test_report_dates_are_inclusive_and_capped_at_two_years(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $this->actingAs($manager)->getJson('/api/reports/sales?from=2024-01-01&to=2026-01-02')
            ->assertUnprocessable()->assertJsonValidationErrors(['to']);
        $this->getJson('/api/reports/sales?from=2024-01-01&to=2026-01-01')->assertOk();
        $this->getJson('/api/reports/sales?from=2026-10-29&to=2026-10-28')
            ->assertUnprocessable()->assertJsonValidationErrors(['to']);
    }

    public function test_recent_movement_query_uses_warehouse_created_at_index_with_ten_thousand_rows(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $warehouse = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        $timestamp = now()->toDateTimeString();

        foreach (array_chunk(array_fill(0, 10_000, [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => 'receipt',
            'quantity_change' => 1,
            'balance_after' => 1,
            'user_id' => $manager->id,
            'created_at' => $timestamp,
        ]), 500) as $rows) {
            DB::table('stock_movements')->insert($rows);
        }

        $query = app(ReportService::class)->recentMovementsQuery([$warehouse->id])->limit(10);
        $plan = DB::select('EXPLAIN QUERY PLAN '.$query->toSql(), $query->getBindings());
        $details = implode(' ', array_map(fn (object $row): string => $row->detail, $plan));

        $this->assertStringContainsString('stock_movements_warehouse_created_idx', $details);
    }

    public function test_csv_export_streams_headers_and_neutralizes_spreadsheet_formulas(): void
    {
        $query = DB::table('products')->selectRaw('? as product_name', ['=1+1'])->limit(1);
        $response = app(CsvExporter::class)->download(
            $query,
            ['Product'],
            fn (object $row): array => [$row->product_name],
            'products.csv',
        );

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString("Product\n", $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString('attachment; filename=products.csv', $response->headers->get('content-disposition'));
    }

    private function insertPurchase(User $manager, Warehouse $warehouse, int $supplierId, string $number, string $status, string $total, string $date): void
    {
        DB::table('purchases')->insert([
            'purchase_number' => $number, 'supplier_id' => $supplierId, 'warehouse_id' => $warehouse->id,
            'status' => $status, 'order_date' => $date, 'subtotal' => $total, 'discount_amount' => '0.00',
            'tax_amount' => '0.00', 'grand_total' => $total, 'created_by' => $manager->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
