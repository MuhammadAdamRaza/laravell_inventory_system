<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleOneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    /**
     * 1. Login succeeds for an active user and fails for wrong credentials.
     */
    public function test_login_succeeds_for_active_user_and_fails_for_wrong_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'admin@example.com')
            ->assertJsonPath('role', 'Administrator');

        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('user.email', 'admin@example.com');

        // Wrong credentials
        $failResponse = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $failResponse->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /**
     * 2. Inactive user cannot log in.
     */
    public function test_inactive_user_cannot_log_in(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Your account is deactivated. Please contact an administrator.',
            ]);

        $this->assertGuest();
    }

    /**
     * 3. Role-based permission access checks across roles.
     */
    public function test_each_role_access_rules(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $manager = User::where('email', 'manager@example.com')->first();
        $staff = User::where('email', 'staff@example.com')->first();
        $viewer = User::where('email', 'viewer@example.com')->first();

        // Administrator can manage users
        $this->actingAs($admin)
            ->getJson('/api/users')
            ->assertStatus(200);

        // Manager cannot manage users (403)
        $this->actingAs($manager)
            ->getJson('/api/users')
            ->assertStatus(403);

        // Staff cannot access customer records, while viewers may read purchases only.
        $this->actingAs($staff)->getJson('/api/customers')->assertForbidden();
        $this->actingAs($viewer)->getJson('/api/purchases')->assertOk();
        $this->postJson('/api/purchases', [])->assertForbidden();
        // Viewer cannot create products (403)
        $this->actingAs($viewer)
            ->postJson('/api/products', [
                'sku' => 'TEST-VIEW-01',
                'name' => 'Should Fail',
                'category_id' => 1,
                'unit' => 'pcs',
                'purchase_price' => 10,
                'selling_price' => 20,
                'minimum_stock' => 5,
                'status' => 'active',
            ])
            ->assertStatus(403);

        // Staff cannot adjust inventory (403)
        $this->actingAs($staff)
            ->postJson('/api/inventory/opening-stock', [
                'product_id' => 1,
                'warehouse_id' => 1,
                'quantity' => 10,
            ])
            ->assertStatus(403);
    }

    /**
     * 4. Duplicate SKU returns 422.
     */
    public function test_duplicate_sku_returns_422(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $category = Category::where('status', 'active')->first();

        $response = $this->actingAs($admin)
            ->postJson('/api/products', [
                'sku' => 'PROD-ELEC-001', // Already seeded
                'name' => 'Duplicate Item',
                'category_id' => $category->id,
                'unit' => 'pcs',
                'purchase_price' => 10,
                'selling_price' => 15,
                'minimum_stock' => 5,
                'status' => 'active',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('sku');
    }

    /**
     * 5. Archived category cannot be assigned to a new product.
     */
    public function test_archived_category_cannot_be_assigned_to_new_product(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $archivedCat = Category::create([
            'name' => 'Old Discontinued Category',
            'status' => 'archived',
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/api/products', [
                'sku' => 'NEW-SKU-999',
                'name' => 'New Product In Archived Cat',
                'category_id' => $archivedCat->id,
                'unit' => 'pcs',
                'purchase_price' => 10,
                'selling_price' => 15,
                'minimum_stock' => 5,
                'status' => 'active',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    /**
     * 6. Opening stock creates one balance row and one movement row.
     */
    public function test_opening_stock_creates_one_balance_row_and_one_movement_row(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $product = Product::first();
        $warehouse = Warehouse::first();

        $response = $this->actingAs($admin)
            ->postJson('/api/inventory/opening-stock', [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => 50,
                'reason' => 'Opening stock 50 units intake',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 50,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => 'adjustment',
            'quantity_change' => 50,
            'balance_after' => 50,
            'user_id' => $admin->id,
        ]);
    }

    /**
     * 7. Negative stock is rejected and nothing changes in database.
     */
    public function test_negative_stock_is_rejected_and_nothing_changes_in_database(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $product = Product::first();
        $warehouse = Warehouse::first();
        $service = app(InventoryService::class);

        // Initial balance: 20
        $service->adjustStock(
            productId: $product->id,
            warehouseId: $warehouse->id,
            quantityChange: 20,
            movementType: MovementType::Adjustment,
            reason: 'Initial 20',
            userId: $admin->id
        );

        $initialBalanceCount = InventoryBalance::count();
        $initialMovementCount = StockMovement::count();

        // Attempting to decrease by 25 (20 - 25 = -5)
        try {
            $service->adjustStock(
                productId: $product->id,
                warehouseId: $warehouse->id,
                quantityChange: -25,
                movementType: MovementType::Adjustment,
                reason: 'Excessive decrease',
                userId: $admin->id
            );
            $this->fail('Expected InsufficientStockException was not thrown.');
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        // Database balance must remain exactly 20, movement count unchanged
        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 20,
        ]);
        $this->assertEquals($initialMovementCount, StockMovement::count());
        $this->assertEquals($initialBalanceCount, InventoryBalance::count());
    }

    /**
     * 8. Warehouse Staff cannot see balances outside their assigned warehouse.
     */
    public function test_warehouse_staff_cannot_see_balances_outside_assigned_warehouse(): void
    {
        $staff = User::where('email', 'staff@example.com')->first();
        $mainWh = Warehouse::where('name', 'Main Central Warehouse')->first();
        $northWh = Warehouse::where('name', 'North Distribution Center')->first();
        $product = Product::first();

        // Seed stock in both warehouses
        InventoryBalance::updateOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $mainWh->id],
            ['quantity' => 100]
        );
        InventoryBalance::updateOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $northWh->id],
            ['quantity' => 200]
        );

        // Staff is assigned only to mainWh
        $response = $this->actingAs($staff)
            ->getJson('/api/inventory/balances');

        $response->assertStatus(200);

        $data = $response->json('data');
        $warehouseIdsReturned = array_column($data, 'warehouse_id');

        $this->assertContains($mainWh->id, $warehouseIdsReturned);
        $this->assertNotContains($northWh->id, $warehouseIdsReturned);
    }

    /**
     * 9. Two sequential decrease requests cannot push stock below zero.
     */
    public function test_two_sequential_decrease_requests_cannot_push_stock_below_zero(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $product = Product::first();
        $warehouse = Warehouse::first();
        $service = app(InventoryService::class);

        // Set initial stock to 15
        $service->adjustStock(
            productId: $product->id,
            warehouseId: $warehouse->id,
            quantityChange: 15,
            movementType: MovementType::Adjustment,
            reason: 'Opening stock 15',
            userId: $admin->id
        );

        // First decrease: 10 (15 - 10 = 5) -> Succeeds
        $service->adjustStock(
            productId: $product->id,
            warehouseId: $warehouse->id,
            quantityChange: -10,
            movementType: MovementType::Adjustment,
            reason: 'First decrement',
            userId: $admin->id
        );

        $balance = InventoryBalance::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        $this->assertEquals(5, $balance->quantity);

        // Second decrease: 10 (5 - 10 = -5) -> Must fail and rollback
        try {
            $service->adjustStock(
                productId: $product->id,
                warehouseId: $warehouse->id,
                quantityChange: -10,
                movementType: MovementType::Adjustment,
                reason: 'Second decrement exceeding stock',
                userId: $admin->id
            );
            $this->fail('Expected InsufficientStockException on second decrement.');
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        // Final balance must remain 5
        $balance->refresh();
        $this->assertEquals(5, $balance->quantity);
    }

    public function test_zero_balance_history_archives_products_and_deactivates_warehouses_instead_of_deleting_them(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $product = Product::firstOrFail();
        $warehouse = Warehouse::where('name', 'Main Central Warehouse')->firstOrFail();
        InventoryBalance::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
        ]);

        $this->actingAs($admin)->deleteJson('/api/products/'.$product->id)
            ->assertOk()->assertJsonPath('data.status', 'archived');
        $this->deleteJson('/api/warehouses/'.$warehouse->id)
            ->assertOk()->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'archived']);
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
        ]);
    }
}
