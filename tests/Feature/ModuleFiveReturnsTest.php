<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\SalesReturnCompleted;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ModuleFiveReturnsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Notification::fake();
    }

    public function test_only_confirmed_orders_can_have_return_drafts_and_drafts_reserve_sold_quantity(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        [$order, $line] = $this->confirmedOrder($manager, 4);
        $draftPayload = $this->returnPayload($order, $line, 3);

        $this->actingAs($manager)->postJson('/api/sales-returns', $draftPayload)
            ->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->postJson('/api/sales-returns', $draftPayload)->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity_returned']);
        $this->assertDatabaseCount('sales_returns', 1);
    }

    public function test_completion_restocks_only_eligible_items_and_is_idempotent(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        [$order, $line, $product, $warehouse] = $this->confirmedOrder($manager, 3, 10);
        $payload = $this->returnPayload($order, $line, 2, true, 'opened');
        $returnId = (int) $this->actingAs($manager)->postJson('/api/sales-returns', $payload)->assertCreated()->json('data.id');

        $key = 'd4156b7a-28ed-4e3d-9c00-667c34412d96';
        $this->postJson("/api/sales-returns/{$returnId}/complete", ['idempotency_key' => $key])
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->postJson("/api/sales-returns/{$returnId}/complete", ['idempotency_key' => $key])
            ->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertSame(9, (int) InventoryBalance::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertDatabaseCount('sales_return_completions', 1);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => MovementType::Return->value,
            'quantity_change' => 2,
            'reference_type' => 'sales_return',
            'reference_id' => $returnId,
        ]);
        $this->assertSame(1, (int) StockMovement::where('reference_type', 'sales_return')->where('reference_id', $returnId)->count());
        Notification::assertSentTo($manager, SalesReturnCompleted::class);
    }

    public function test_damaged_return_can_complete_without_adding_stock_and_cancelled_drafts_release_reservations(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        [$order, $line, $product, $warehouse] = $this->confirmedOrder($manager, 3, 10);
        $payload = $this->returnPayload($order, $line, 2, false, 'damaged');
        $returnId = (int) $this->actingAs($manager)->postJson('/api/sales-returns', $payload)->assertCreated()->json('data.id');

        $this->postJson("/api/sales-returns/{$returnId}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $available = $this->getJson("/api/sales-orders/{$order->id}/returnable-items")->assertOk();
        $available->assertJsonPath('data.items.0.returnable_quantity', 3);

        $secondReturn = (int) $this->postJson('/api/sales-returns', $payload)->assertCreated()->json('data.id');
        $this->postJson("/api/sales-returns/{$secondReturn}/complete", ['idempotency_key' => '84bb6397-f99e-4738-a7d1-ade48a9023ae'])
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame(7, (int) InventoryBalance::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertDatabaseMissing('stock_movements', ['reference_type' => 'sales_return', 'reference_id' => $secondReturn]);
    }

    public function test_warehouse_staff_can_complete_only_returns_for_their_assigned_warehouse(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        [$order, $line] = $this->confirmedOrder($manager, 2);
        $returnId = (int) $this->actingAs($manager)->postJson('/api/sales-returns', $this->returnPayload($order, $line, 1))->assertCreated()->json('data.id');

        $this->actingAs($staff)->getJson("/api/sales-returns/{$returnId}")->assertOk();
        $this->postJson("/api/sales-returns/{$returnId}/complete", ['idempotency_key' => '1812e77c-28f5-48a9-b011-c4de792ac1df'])
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $northWarehouse = Warehouse::where('name', 'North Distribution Center')->firstOrFail();
        [$northOrder, $northLine] = $this->confirmedOrder($manager, 2, 10, $northWarehouse);
        $northReturnId = (int) $this->actingAs($manager)->postJson('/api/sales-returns', $this->returnPayload($northOrder, $northLine, 1))
            ->assertCreated()->json('data.id');
        $this->actingAs($staff)->getJson("/api/sales-returns/{$northReturnId}")->assertNotFound();
    }

    /**
     * @return array{SalesOrder, SalesOrderItem, Product, Warehouse}
     */
    private function confirmedOrder(User $manager, int $quantity, int $startingStock = 10, ?Warehouse $selectedWarehouse = null): array
    {
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        $warehouse = $selectedWarehouse ?? Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => $startingStock]);
        $customer = Customer::create(['name' => 'Return Customer', 'status' => 'active']);
        $response = $this->actingAs($manager)->postJson('/api/sales-orders', [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
            'discount_amount' => '0.00',
            'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => '28.50']],
        ])->assertCreated();
        $order = SalesOrder::query()->with('items')->findOrFail($response->json('data.id'));
        $this->postJson("/api/sales-orders/{$order->id}/confirm")->assertOk();
        $order->refresh();

        return [$order, $order->items->first(), $product, $warehouse];
    }

    private function returnPayload(SalesOrder $order, SalesOrderItem $line, int $quantity, bool $restock = true, string $condition = 'new_unopened'): array
    {
        return [
            'sales_order_id' => $order->id,
            'return_date' => now()->toDateString(),
            'reason_summary' => 'Customer return',
            'items' => [[
                'sales_order_item_id' => $line->id,
                'quantity_returned' => $quantity,
                'reason' => 'customer_changed_mind',
                'condition' => $condition,
                'restock_eligible' => $restock,
            ]],
        ];
    }
}
