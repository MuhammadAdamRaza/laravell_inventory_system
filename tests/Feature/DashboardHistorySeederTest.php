<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DashboardHistorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class DashboardHistorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_has_six_months_of_graph_data_and_repeat_seeding_preserves_stock(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00:00', 'Asia/Karachi'));
        $this->seed(RoleAndPermissionSeeder::class);
        $existingProduct = Product::query()->firstOrFail();
        Product::query()->create([
            'sku' => 'PROD-OFFICE-002', 'name' => 'Compact Office Keyboard',
            'category_id' => $existingProduct->category_id, 'unit' => 'pcs',
            'purchase_price' => '1800.00', 'selling_price' => '2400.00',
            'minimum_stock' => 8, 'status' => 'active',
        ]);
        Notification::fake();

        $this->seed(DashboardHistorySeeder::class);

        $this->assertDatabaseCount('customers', 15);
        $this->assertDatabaseCount('purchases', 24);
        $this->assertDatabaseCount('purchase_receipts', 24);
        $this->assertDatabaseCount('sales_orders', 24);
        $this->assertSame(24, Purchase::query()->where('status', 'received')->count());
        $this->assertSame(24, SalesOrder::query()->where('status', 'confirmed')->count());
        $this->assertSame((int) StockMovement::query()->sum('quantity_change'), (int) InventoryBalance::query()->sum('quantity'));
        Notification::assertNothingSent();

        $quantities = InventoryBalance::query()->orderBy('id')->pluck('quantity', 'id')->all();
        $movementCount = StockMovement::query()->count();
        $this->seed(DashboardHistorySeeder::class);
        $this->assertDatabaseCount('customers', 15);
        $this->assertDatabaseCount('purchases', 24);
        $this->assertDatabaseCount('sales_orders', 24);
        $this->assertSame($movementCount, StockMovement::query()->count());
        $this->assertSame($quantities, InventoryBalance::query()->orderBy('id')->pluck('quantity', 'id')->all());

        $manager = User::query()->where('email', 'manager@example.com')->firstOrFail();
        $response = $this->actingAs($manager)->getJson('/api/reports/dashboard?from=2026-05-01&to=2026-10-29')->assertOk();
        $this->assertCount(6, $response->json('trend.months'));
        foreach (['sales', 'purchases'] as $series) {
            foreach ($response->json('trend.'.$series) as $amount) {
                $this->assertGreaterThan(0, (float) $amount);
            }
        }
        $this->assertGreaterThan(1, count(array_unique($response->json('trend.sales'))));
    }

    public function test_history_seeder_rejects_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dashboard history may only be seeded in local or testing environments.');
        $this->app->make(DashboardHistorySeeder::class)->run();
    }
}
