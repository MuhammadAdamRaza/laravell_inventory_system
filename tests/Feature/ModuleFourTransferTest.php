<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleFourTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_transfer_requires_different_source_and_destination(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $warehouse = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $destination = Warehouse::where('name', 'North Distribution Center')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        $payload = $this->transferPayload($warehouse, $destination, $product);
        $payload['destination_warehouse_id'] = $warehouse->id;

        $this->actingAs($manager)->postJson('/api/transfers', $payload)->assertUnprocessable()->assertJsonValidationErrors('source_warehouse_id');
    }

    public function test_dispatch_and_receive_reconcile_stock_and_retries_are_idempotent(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $source = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $destination = Warehouse::where('name', 'North Distribution Center')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $source->id, 'quantity' => 10]);
        $id = $this->createTransfer($manager, $source, $destination, $product, 10);

        $this->actingAs($manager)->postJson("/api/transfers/{$id}/dispatch")->assertOk()->assertJsonPath('data.status', 'in_transit');
        $this->assertSame(0, (int) InventoryBalance::where('product_id', $product->id)->where('warehouse_id', $source->id)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'warehouse_id' => $source->id, 'movement_type' => 'transfer_out', 'quantity_change' => -10]);

        $payload = [
            'idempotency_key' => 'f3e55ba7-cc26-4bd3-88bf-bb75c7e4f009',
            'items' => [['product_id' => $product->id, 'accepted_quantity' => 8, 'returned_quantity' => 2]],
        ];
        $this->actingAs($admin)->postJson("/api/transfers/{$id}/receive", $payload)->assertCreated()->assertJsonPath('data.status', 'completed');
        $this->postJson("/api/transfers/{$id}/receive", $payload)->assertOk();
        $this->assertSame(2, (int) InventoryBalance::where('product_id', $product->id)->where('warehouse_id', $source->id)->value('quantity'));
        $this->assertSame(8, (int) InventoryBalance::where('product_id', $product->id)->where('warehouse_id', $destination->id)->value('quantity'));
        $this->assertSame(10, (int) InventoryBalance::where('product_id', $product->id)->whereIn('warehouse_id', [$source->id, $destination->id])->sum('quantity'));
        $this->assertDatabaseHas('stock_movements', ['warehouse_id' => $destination->id, 'movement_type' => 'transfer_in', 'quantity_change' => 8]);
        $this->assertDatabaseHas('stock_movements', ['warehouse_id' => $source->id, 'movement_type' => 'transfer_return', 'quantity_change' => 2]);
        $this->assertDatabaseCount('warehouse_transfer_receipts', 1);
    }

    public function test_mismatched_receipt_and_insufficient_dispatch_leave_stock_unchanged(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $source = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $destination = Warehouse::where('name', 'North Distribution Center')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $source->id, 'quantity' => 2]);
        $id = $this->createTransfer($manager, $source, $destination, $product, 4);
        $this->actingAs($manager)->postJson("/api/transfers/{$id}/dispatch")->assertUnprocessable();
        $this->assertSame(2, (int) InventoryBalance::where('product_id', $product->id)->where('warehouse_id', $source->id)->value('quantity'));
        $this->assertDatabaseMissing('stock_movements', ['reference_type' => App\Models\WarehouseTransfer::class, 'reference_id' => $id]);

        InventoryBalance::where('product_id', $product->id)->where('warehouse_id', $source->id)->update(['quantity' => 4]);
        $this->postJson("/api/transfers/{$id}/dispatch")->assertOk();
        $payload = ['idempotency_key' => 'c11d42b9-202d-4b99-a432-6aa56d611b97', 'items' => [['product_id' => $product->id, 'accepted_quantity' => 2, 'returned_quantity' => 1]]];
        $this->actingAs($admin)->postJson("/api/transfers/{$id}/receive", $payload)->assertUnprocessable();
        $this->assertDatabaseCount('warehouse_transfer_receipts', 0);
        $this->assertSame('in_transit', $this->getJson("/api/transfers/{$id}")->json('data.status'));
    }

    public function test_dispatcher_cannot_receive_and_in_transit_transfer_cannot_be_cancelled(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $source = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $destination = Warehouse::where('name', 'North Distribution Center')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $source->id, 'quantity' => 3]);
        $id = $this->createTransfer($manager, $source, $destination, $product, 3);
        $this->actingAs($manager)->postJson("/api/transfers/{$id}/dispatch")->assertOk();
        $this->postJson("/api/transfers/{$id}/dispatch")->assertUnprocessable();
        $this->postJson("/api/transfers/{$id}/cancel")->assertUnprocessable();
        $payload = ['idempotency_key' => 'f0d9b1cb-5169-4c06-b4fc-0600cbbcc125', 'items' => [['product_id' => $product->id, 'accepted_quantity' => 3, 'returned_quantity' => 0]]];
        $this->postJson("/api/transfers/{$id}/receive", $payload)->assertForbidden();
    }

    public function test_warehouse_staff_can_dispatch_only_from_assigned_source_warehouses(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $source = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $other = Warehouse::where('name', 'North Distribution Center')->firstOrFail();
        $destination = Warehouse::where('name', 'North Distribution Center')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $source->id, 'quantity' => 5]);
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $other->id, 'quantity' => 5]);
        $allowed = $this->createTransfer($manager, $source, $destination, $product, 1);
        $blocked = $this->createTransfer($manager, $other, $source, $product, 1);

        $this->actingAs($staff)->postJson("/api/transfers/{$allowed}/dispatch")->assertOk();
        $this->postJson("/api/transfers/{$blocked}/dispatch")->assertForbidden();
    }

    public function test_warehouse_staff_can_receive_only_at_assigned_destination_warehouses(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $north = Warehouse::where('name', 'North Distribution Center')->firstOrFail();
        $main = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $north->id, 'quantity' => 4]);
        InventoryBalance::create(['product_id' => $product->id, 'warehouse_id' => $main->id, 'quantity' => 4]);

        $allowed = $this->createTransfer($manager, $north, $main, $product, 2);
        $blocked = $this->createTransfer($manager, $main, $north, $product, 2);
        $this->actingAs($manager)->postJson("/api/transfers/{$allowed}/dispatch")->assertOk();
        $this->postJson("/api/transfers/{$blocked}/dispatch")->assertOk();
        $allowedReceipt = ['idempotency_key' => 'c1c1b7e5-590d-4c04-bd07-98bb5f149e83', 'items' => [['product_id' => $product->id, 'accepted_quantity' => 2, 'returned_quantity' => 0]]];
        $blockedReceipt = ['idempotency_key' => '775834dd-7601-499d-9ea5-8da023771719', 'items' => [['product_id' => $product->id, 'accepted_quantity' => 2, 'returned_quantity' => 0]]];

        $this->actingAs($staff)->postJson("/api/transfers/{$allowed}/receive", $allowedReceipt)->assertCreated();
        $this->postJson("/api/transfers/{$blocked}/receive", $blockedReceipt)->assertForbidden();
    }

    public function test_adjustments_are_immutable_reasoned_and_use_inventory_service(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $viewer = User::where('email', 'viewer@example.com')->firstOrFail();
        $warehouse = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();

        $this->actingAs($admin)->getJson('/api/stock-adjustments')->assertOk();

        $created = $this->actingAs($manager)->postJson('/api/stock-adjustments', [
            'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity_change' => 5,
            'reason_code' => 'found', 'reason' => 'Found during stock count',
        ])->assertCreated()->assertJsonPath('data.quantity_change', 5);
        $adjustmentId = $created->json('data.id');
        $this->assertSame(5, (int) InventoryBalance::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'movement_type' => 'adjustment', 'quantity_change' => 5, 'reason' => 'Found during stock count']);
        $this->putJson("/api/stock-adjustments/{$adjustmentId}", [])->assertMethodNotAllowed();
        $this->deleteJson("/api/stock-adjustments/{$adjustmentId}")->assertMethodNotAllowed();

        $this->postJson('/api/stock-adjustments', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity_change' => -6, 'reason_code' => 'loss', 'reason' => 'Damaged stock'])->assertUnprocessable();
        $this->postJson('/api/stock-adjustments', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity_change' => 0, 'reason_code' => 'other', 'reason' => 'No change requested'])->assertUnprocessable();
        $this->postJson('/api/stock-adjustments', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity_change' => 1, 'reason_code' => 'other', 'reason' => 'Short'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->actingAs($staff)->postJson('/api/stock-adjustments', [])->assertForbidden();
        $this->actingAs($viewer)->postJson('/api/stock-adjustments', [])->assertForbidden();
    }

    private function createTransfer(User $manager, Warehouse $source, Warehouse $destination, Product $product, int $quantity): int
    {
        $response = $this->actingAs($manager)->postJson('/api/transfers', $this->transferPayload($source, $destination, $product, $quantity))->assertCreated();

        return (int) $response->json('data.id');
    }

    private function transferPayload(Warehouse $source, Warehouse $destination, Product $product, int $quantity = 1): array
    {
        return [
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'items' => [['product_id' => $product->id, 'dispatched_quantity' => $quantity]],
        ];
    }
}
