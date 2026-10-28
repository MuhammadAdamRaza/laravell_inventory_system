<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\PurchaseApproved;
use App\Notifications\PurchaseSubmitted;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ModuleTwoPurchasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Notification::fake();
    }

    public function test_warehouse_staff_sees_only_assigned_stock_and_receiving_pages(): void
    {
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $product = Product::where('sku', 'PROD-ELEC-001')->firstOrFail();
        $assignedWarehouse = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        $otherWarehouse = Warehouse::query()->create(['name' => 'Other Staff Test Warehouse', 'status' => 'active']);
        InventoryBalance::query()->create(['product_id' => $product->id, 'warehouse_id' => $assignedWarehouse->id, 'quantity' => 4]);
        InventoryBalance::query()->create(['product_id' => $product->id, 'warehouse_id' => $otherWarehouse->id, 'quantity' => 9]);

        $this->actingAs($staff)->getJson('/api/products')->assertOk();
        $this->getJson('/api/categories')->assertOk();
        $this->getJson('/api/warehouses')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/inventory/balances')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quantity', 4)
            ->assertJsonPath('data.0.warehouse_name', 'Main Central Warehouse');
        $this->getJson('/api/inventory/stats')
            ->assertOk()->assertJsonPath('total_units', 4)
            ->assertJsonPath('total_warehouses', 1)
            ->assertJsonPath('total_products', 1);
    }

    public function test_manager_creates_and_submits_purchase_but_admin_must_approve_it(): void
    {
        config(['purchases.tax_rate' => '10.00']);
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $payload = $this->purchasePayload();
        $payload['discount_amount'] = '2.50';

        $purchaseResponse = $this->actingAs($manager)->postJson('/api/purchases', $payload);
        $purchaseResponse->assertCreated()
            ->assertJsonPath('data.subtotal', '37.50')
            ->assertJsonPath('data.discount_amount', '2.50')
            ->assertJsonPath('data.tax_amount', '3.50')
            ->assertJsonPath('data.grand_total', '38.50');

        $purchaseId = $purchaseResponse->json('data.id');
        $this->postJson('/api/purchases/'.$purchaseId.'/submit')->assertOk()
            ->assertJsonPath('data.status', 'pending_approval');
        $manager2 = User::where('email', 'manager2@example.com')->firstOrFail();
        Notification::assertSentTo($admin, PurchaseSubmitted::class);
        Notification::assertSentTo($manager2, PurchaseSubmitted::class);
        Notification::assertNotSentTo($manager, PurchaseSubmitted::class);
        $this->putJson('/api/purchases/'.$purchaseId, $payload)->assertUnprocessable();
        $this->postJson('/api/purchases/'.$purchaseId.'/approve')->assertForbidden();

        $this->actingAs($admin)->postJson('/api/purchases/'.$purchaseId.'/approve')
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $purchase = Purchase::findOrFail($purchaseId);
        $this->assertSame($admin->id, $purchase->approved_by);
        $this->assertNotNull($purchase->approved_at);
        Notification::assertSentTo($manager, PurchaseApproved::class);
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        Notification::assertSentTo($staff, PurchaseApproved::class);
    }

    public function test_assigned_staff_can_receive_partially_then_fully_and_retries_are_idempotent(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $purchaseResponse = $this->actingAs($manager)->postJson('/api/purchases', $this->purchasePayload());
        $purchaseId = $purchaseResponse->json('data.id');
        $purchaseItemId = $purchaseResponse->json('data.items.0.id');
        $this->postJson('/api/purchases/'.$purchaseId.'/submit')->assertOk();
        $this->actingAs($admin)->postJson('/api/purchases/'.$purchaseId.'/approve')->assertOk();

        $warehouseId = Warehouse::where('name', 'Main Central Warehouse')->value('id');
        $payload = [
            'idempotency_key' => 'a7a4f272-7c08-4487-9d67-bdf5c4aa75dd',
            'warehouse_id' => $warehouseId,
            'items' => [['purchase_item_id' => $purchaseItemId, 'quantity_received' => 2]],
        ];
        $this->actingAs($staff)->postJson('/api/purchases/'.$purchaseId.'/receive', $payload)
            ->assertCreated()->assertJsonPath('data.status', 'partially_received');
        $this->postJson('/api/purchases/'.$purchaseId.'/receive', $payload)->assertOk();

        $productId = Product::where('sku', 'PROD-ELEC-001')->value('id');
        $this->assertSame(2, (int) InventoryBalance::where('product_id', $productId)->where('warehouse_id', $warehouseId)->value('quantity'));
        $this->assertSame(1, Purchase::findOrFail($purchaseId)->receipts()->count());

        $payload['idempotency_key'] = 'e9ffb099-2fb2-41ef-bce6-58d9e34dc6dd';
        $payload['items'][0]['quantity_received'] = 1;
        $this->postJson('/api/purchases/'.$purchaseId.'/receive', $payload)
            ->assertCreated()->assertJsonPath('data.status', 'received');
        $this->assertSame(3, (int) InventoryBalance::where('product_id', $productId)->where('warehouse_id', $warehouseId)->value('quantity'));
        $this->assertSame(2, Purchase::findOrFail($purchaseId)->receipts()->count());

        $this->actingAs($admin)->postJson('/api/purchases/'.$purchaseId.'/cancel')->assertUnprocessable();
    }

    public function test_over_receiving_is_rejected_without_changing_stock_or_purchase(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $purchaseResponse = $this->actingAs($manager)->postJson('/api/purchases', $this->purchasePayload());
        $purchaseId = $purchaseResponse->json('data.id');
        $purchaseItemId = $purchaseResponse->json('data.items.0.id');
        $this->postJson('/api/purchases/'.$purchaseId.'/submit')->assertOk();
        $this->actingAs($admin)->postJson('/api/purchases/'.$purchaseId.'/approve')->assertOk();

        $warehouseId = Warehouse::where('name', 'Main Central Warehouse')->value('id');
        $this->actingAs($staff)->postJson('/api/purchases/'.$purchaseId.'/receive', [
            'idempotency_key' => 'a4b1c5a9-a0e7-4a34-bf8a-564e3ac43ba0',
            'warehouse_id' => $warehouseId,
            'items' => [['purchase_item_id' => $purchaseItemId, 'quantity_received' => 4]],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('inventory_balances', ['product_id' => Product::where('sku', 'PROD-ELEC-001')->value('id')]);
        $this->assertSame('approved', Purchase::findOrFail($purchaseId)->status);
        $this->assertSame(0, Purchase::findOrFail($purchaseId)->receipts()->count());
    }

    public function test_warehouse_staff_cannot_access_a_purchase_for_an_unassigned_warehouse(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $warehouse = Warehouse::query()->create(['name' => 'Unassigned Test Warehouse', 'status' => 'active']);
        $payload = $this->purchasePayload();
        $payload['warehouse_id'] = $warehouse->id;
        $purchase = $this->actingAs($manager)->postJson('/api/purchases', $payload);
        $purchaseId = $purchase->json('data.id');
        $purchaseItemId = $purchase->json('data.items.0.id');
        $this->postJson('/api/purchases/'.$purchaseId.'/submit')->assertOk();
        $this->actingAs($admin)->postJson('/api/purchases/'.$purchaseId.'/approve')->assertOk();

        $this->actingAs($staff)->postJson('/api/purchases/'.$purchaseId.'/receive', [
            'idempotency_key' => 'bb63ee8d-c279-4706-9ed6-f7dbd638fd11',
            'warehouse_id' => $warehouse->id,
            'items' => [['purchase_item_id' => $purchaseItemId, 'quantity_received' => 1]],
        ])->assertNotFound();
    }

    public function test_manager_approval_rejection_reason_and_administrator_exception(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $manager = User::where('email', 'manager@example.com')->firstOrFail();

        $adminCreated = $this->actingAs($admin)->postJson('/api/purchases', $this->purchasePayload());
        $adminPurchaseId = $adminCreated->json('data.id');
        $this->postJson('/api/purchases/'.$adminPurchaseId.'/submit')->assertOk();
        $this->actingAs($manager)->postJson('/api/purchases/'.$adminPurchaseId.'/approve')
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $managerCreated = $this->actingAs($manager)->postJson('/api/purchases', $this->purchasePayload());
        $managerPurchaseId = $managerCreated->json('data.id');
        $this->postJson('/api/purchases/'.$managerPurchaseId.'/submit')->assertOk();
        $this->postJson('/api/purchases/'.$managerPurchaseId.'/reject', ['reason' => 'Correct the requested quantity.'])
            ->assertOk()->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.rejection_reason', 'Correct the requested quantity.');

        $adminOwn = $this->actingAs($admin)->postJson('/api/purchases', $this->purchasePayload());
        $adminOwnId = $adminOwn->json('data.id');
        $this->postJson('/api/purchases/'.$adminOwnId.'/submit')->assertOk();
        $this->postJson('/api/purchases/'.$adminOwnId.'/approve')
            ->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_purchase_creation_rejects_duplicate_products(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $payload = $this->purchasePayload();
        $payload['items'][] = $payload['items'][0];

        $this->actingAs($manager)->postJson('/api/purchases', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('items.1.product_id');
    }

    public function test_supplier_payment_updates_exact_summary_balance(): void
    {
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $supplierId = Supplier::where('name', 'Apex Tech Distributors')->value('id');
        $this->actingAs($manager)->postJson('/api/purchases', $this->purchasePayload())->assertCreated();

        $this->postJson('/api/supplier-payments', [
            'supplier_id' => $supplierId,
            'amount' => '20.00',
            'payment_date' => now()->toDateString(),
            'reference' => 'BANK-001',
        ])->assertCreated()->assertJsonPath('data.amount', '20.00');

        $this->getJson('/api/suppliers/'.$supplierId.'/summary')
            ->assertOk()
            ->assertJsonPath('data.total_purchases', 1)
            ->assertJsonPath('data.total_amount', '37.50')
            ->assertJsonPath('data.total_paid', '20.00')
            ->assertJsonPath('data.outstanding_balance', '17.50');
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasePayload(): array
    {
        return [
            'supplier_id' => Supplier::where('name', 'Apex Tech Distributors')->value('id'),
            'warehouse_id' => Warehouse::where('name', 'Main Central Warehouse')->value('id'),
            'order_date' => now()->toDateString(),
            'discount_amount' => '0.00',
            'items' => [[
                'product_id' => Product::where('sku', 'PROD-ELEC-001')->value('id'),
                'ordered_quantity' => 3,
                'unit_price' => '12.50',
            ]],
        ];
    }
}
