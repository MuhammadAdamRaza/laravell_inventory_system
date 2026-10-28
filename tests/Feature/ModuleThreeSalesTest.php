<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\SalesOrderConfirmed;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ModuleThreeSalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Notification::fake();
    }

    public function test_sales_order_totals_are_calculated_for_multiple_lines(): void
    {
        config(['sales.tax_rate' => '10.00']);
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $products = $this->products(3);
        $payload = $this->payload($products);
        $payload['items'] = [
            ['product_id' => $products[0]->id, 'quantity' => 2, 'unit_price' => '10.00'],
            ['product_id' => $products[1]->id, 'quantity' => 3, 'unit_price' => '20.00'],
            ['product_id' => $products[2]->id, 'quantity' => 1, 'unit_price' => '30.00'],
        ];
        $payload['discount_amount'] = '10.00';

        $this->actingAs($manager)->postJson('/api/sales-orders', $payload)->assertCreated()
            ->assertJsonPath('data.subtotal', '110.00')->assertJsonPath('data.discount_amount', '10.00')
            ->assertJsonPath('data.tax_amount', '10.00')->assertJsonPath('data.grand_total', '110.00')
            ->assertJsonPath('data.items.1.line_total', '60.00');
    }

    public function test_duplicate_product_lines_are_rejected(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        $payload = $this->payload([$product]);
        $payload['items'][] = $payload['items'][0];

        $this->actingAs($manager)->postJson('/api/sales-orders', $payload)->assertUnprocessable()->assertJsonValidationErrors(['items.0.product_id', 'items.1.product_id']);
    }

    public function test_confirmation_deducts_selected_stock_once_and_queues_notification(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        $warehouse = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $other = Warehouse::where('name', 'North Distribution Center')->firstOrFail();
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 9]);
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $other->id, 'quantity' => 14]);
        $id = $this->createOrder($manager, [$product], (int) $warehouse->id, 4);

        $this->actingAs($manager)->postJson("/api/sales-orders/{$id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->assertSame(5, (int) InventoryBalance::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertSame(14, (int) InventoryBalance::where('product_id', $product->id)->where('warehouse_id', $other->id)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'movement_type' => 'sale', 'quantity_change' => -4, 'reference_type' => 'sales_order']);
        $this->postJson("/api/sales-orders/{$id}/confirm")->assertUnprocessable();
        $this->postJson("/api/sales-orders/{$id}/cancel")->assertUnprocessable();
        Notification::assertSentTo($manager, SalesOrderConfirmed::class);
    }

    public function test_exact_last_units_cannot_be_sold_by_two_orders(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        $warehouse = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 2]);
        $firstOrder = $this->createOrder($manager, [$product], (int) $warehouse->id, 2);
        $secondOrder = $this->createOrder($manager, [$product], (int) $warehouse->id, 2);

        $this->actingAs($manager)->postJson("/api/sales-orders/{$firstOrder}/confirm")->assertOk();
        $this->actingAs($admin)->postJson("/api/sales-orders/{$secondOrder}/confirm")->assertUnprocessable();

        $this->assertSame(0, (int) InventoryBalance::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame('draft', SalesOrder::findOrFail($secondOrder)->status);
    }

    public function test_draft_can_be_cancelled_without_stock_movement_and_confirmed_order_cannot_be_edited(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $warehouse = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 3]);
        $draft = $this->createOrder($manager, [$product], (int) $warehouse->id, 1);

        $this->actingAs($manager)->postJson("/api/sales-orders/{$draft}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseMissing('stock_movements', ['reference_type' => 'sales_order', 'reference_id' => $draft]);

        $confirmed = $this->createOrder($manager, [$product], (int) $warehouse->id, 1);
        $this->postJson("/api/sales-orders/{$confirmed}/confirm")->assertOk();
        $payload = $this->payload([$product], (int) $warehouse->id);
        $this->putJson("/api/sales-orders/{$confirmed}", $payload)->assertUnprocessable();
    }

    public function test_insufficient_stock_rolls_back_all_lines_and_order_status(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $warehouse = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $products = $this->products(2);
        InventoryBalance::create(['product_id' => $products[0]->id, 'warehouse_id' => $warehouse->id, 'quantity' => 20]);
        InventoryBalance::create(['product_id' => $products[1]->id, 'warehouse_id' => $warehouse->id, 'quantity' => 1]);
        $payload = $this->payload($products, (int) $warehouse->id);
        $payload['items'][0]['quantity'] = 3;
        $payload['items'][1]['quantity'] = 2;
        $id = (int) $this->actingAs($manager)->postJson('/api/sales-orders', $payload)->assertCreated()->json('data.id');

        $this->postJson("/api/sales-orders/{$id}/confirm")->assertUnprocessable();
        $this->assertSame(20, (int) InventoryBalance::where('product_id', $products[0]->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertSame(1, (int) InventoryBalance::where('product_id', $products[1]->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertSame('draft', SalesOrder::findOrFail($id)->status);
        $this->assertDatabaseMissing('stock_movements', ['reference_type' => 'sales_order', 'reference_id' => $id]);
    }

    public function test_staff_can_confirm_only_orders_for_assigned_warehouses(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        $assigned = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $unassigned = Warehouse::where('name', 'North Distribution Center')->firstOrFail();
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $assigned->id, 'quantity' => 5]);
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $unassigned->id, 'quantity' => 5]);
        $allowed = $this->createOrder($manager, [$product], (int) $assigned->id, 1);
        $blocked = $this->createOrder($manager, [$product], (int) $unassigned->id, 1);

        $this->actingAs($staff)->postJson("/api/sales-orders/{$allowed}/confirm")->assertOk();
        $this->postJson("/api/sales-orders/{$blocked}/confirm")->assertNotFound();
    }

    public function test_customer_order_history_is_customer_specific(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $customer = Customer::create(['name' => 'Test Customer A', 'status' => 'active']);
        $other = Customer::create(['name' => 'Test Customer B', 'status' => 'active']);
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        $warehouse = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $this->createOrder($manager, [$product], (int) $warehouse->id, 1, (int) $customer->id);
        $this->createOrder($manager, [$product], (int) $warehouse->id, 1, (int) $other->id);

        $this->actingAs($manager)->getJson("/api/customers/{$customer->id}/orders")->assertOk()->assertJsonCount(1, 'data');
    }

    /** @return list<Product> */
    private function products(int $count): array
    {
        $products = [Product::where('sku', 'PROD-ELEC-001')->firstOrFail()];
        $category = Category::where('name', 'Electronics')->firstOrFail();
        for ($index = 2; $index <= $count; $index++) {
            $products[] = Product::create([
                'sku' => 'SALES-TEST-'.uniqid(), 'name' => 'Sales test product '.$index,
                'category_id' => $category->id, 'unit' => 'pcs', 'selling_price' => '10.00',
                'purchase_price' => '5.00', 'minimum_stock' => 0, 'status' => 'active',
            ]);
        }

        return $products;
    }

    /** @param list<Product> $products */
    private function payload(array $products, ?int $warehouseId = null, ?int $customerId = null): array
    {
        $customer = $customerId ? Customer::findOrFail($customerId) : Customer::create(['name' => 'Test Order Customer', 'status' => 'active']);
        $warehouse = $warehouseId ? Warehouse::findOrFail($warehouseId) : Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();

        return [
            'customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'order_date' => now()->toDateString(),
            'discount_amount' => '0.00',
            'items' => array_map(static fn (Product $product): array => ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '10.00'], $products),
        ];
    }

    /** @param list<Product> $products */
    private function createOrder(User $manager, array $products, int $warehouseId, int $quantity, ?int $customerId = null): int
    {
        $payload = $this->payload($products, $warehouseId, $customerId);
        foreach ($payload['items'] as &$item) {
            $item['quantity'] = $quantity;
        }
        unset($item);

        return (int) $this->actingAs($manager)->postJson('/api/sales-orders', $payload)->assertCreated()->json('data.id');
    }
}
