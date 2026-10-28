<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class SampleInventorySeeder extends Seeder
{
    public function run(): void
    {
        if (! App::environment(['local', 'testing'])) {
            throw new \LogicException('Sample inventory data may only be seeded in local or testing environments.');
        }

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $manager = User::query()->where('email', 'manager@example.com')->firstOrFail();

        $descriptions = [
            'Electronics' => 'Computing devices and everyday electronic equipment.',
            'Raw Materials' => 'Materials used for assembly, maintenance, and packaging.',
            'Office Supplies' => 'Storage, filing, and desk organisation products.',
            'Furniture' => 'Furniture for offices and shared workspaces.',
            'Networking' => 'Network connectivity and wireless equipment.',
            'Storage' => 'Portable devices for transferring and storing digital files.',
            'Lighting' => 'Task lighting for desks and work areas.',
            'Audio' => 'Headphones and speakers for calls and media.',
            'Computer Accessories' => 'Keyboards and accessories for desktop computers.',
            'Printers' => 'Printing equipment for office documents.',
            'Displays' => 'Computer monitors and display equipment.',
            'Cables' => 'Wired connections for computer and network equipment.',
            'Tools' => 'Tools for installation and equipment maintenance.',
            'Security' => 'Cameras and equipment for workplace security.',
            'Components' => 'Computer and point-of-sale equipment components.',
        ];
        $categories = collect($descriptions)->mapWithKeys(fn (string $description, string $name): array => [
            $name => Category::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description, 'status' => 'active'],
            ),
        ]);

        $warehouseData = [
            ['Main Central Warehouse', 'Sundar Industrial Estate, Lahore', 'Zain Qureshi', '+92 300 555 0201', 'warehouse01@example.test'],
            ['North Distribution Center', 'Shahdara, Lahore', 'Saad Mahmood', '+92 300 555 0202', 'warehouse02@example.test'],
            ['Lahore East Warehouse', 'Multan Road, Lahore', 'Haris Javed', '+92 300 555 0203', 'warehouse03@example.test'],
            ['Gulberg Fulfilment Centre', 'Gulberg III, Lahore', 'Usman Khalid', '+92 300 555 0204', 'warehouse04@example.test'],
            ['Johar Town Storage Centre', 'Johar Town, Lahore', 'Ali Raza', '+92 300 555 0205', 'warehouse05@example.test'],
            ['Ferozepur Road Warehouse', 'Ferozepur Road, Lahore', 'Fahad Iqbal', '+92 300 555 0206', 'warehouse06@example.test'],
            ['DHA Logistics Centre', 'DHA Phase 5, Lahore', 'Hamza Tariq', '+92 300 555 0207', 'warehouse07@example.test'],
            ['Model Town Warehouse', 'Model Town, Lahore', 'Bilal Nadeem', '+92 300 555 0208', 'warehouse08@example.test'],
            ['Raiwind Road Distribution Centre', 'Raiwind Road, Lahore', 'Ahsan Ali', '+92 300 555 0209', 'warehouse09@example.test'],
            ['Township Stockroom', 'Township, Lahore', 'Omar Siddiqui', '+92 300 555 0210', 'warehouse10@example.test'],
            ['Cavalry Ground Depot', 'Cavalry Ground, Lahore', 'Danish Akram', '+92 300 555 0211', 'warehouse11@example.test'],
            ['Wapda Town Fulfilment Centre', 'Wapda Town, Lahore', 'Rizwan Ahmed', '+92 300 555 0212', 'warehouse12@example.test'],
            ['Canal View Warehouse', 'Canal View, Lahore', 'Imran Bashir', '+92 300 555 0213', 'warehouse13@example.test'],
            ['Garden Town Distribution Point', 'Garden Town, Lahore', 'Noman Aslam', '+92 300 555 0214', 'warehouse14@example.test'],
            ['Cantonment Supply Depot', 'Lahore Cantonment', 'Kashif Rauf', '+92 300 555 0215', 'warehouse15@example.test'],
        ];
        $warehouses = collect($warehouseData)->values()->map(function (array $data, int $index): Warehouse {
            [$name, $location, $contactPerson, $phone, $email] = $data;
            $legacyName = $index < 2 ? $name : 'Demo Warehouse '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $warehouse = Warehouse::query()->whereIn('name', [$legacyName, $name])->first() ?? new Warehouse;
            $warehouse->fill([
                'name' => $name, 'location' => $location, 'contact_person' => $contactPerson,
                'phone' => $phone, 'email' => $email, 'status' => 'active',
            ])->save();

            return $warehouse;
        });

        $allWarehouseIds = Warehouse::query()->pluck('id')->all();

        foreach (['admin@example.com', 'manager@example.com', 'manager2@example.com', 'viewer@example.com', 'inactive@example.com'] as $email) {
            User::query()->where('email', $email)->firstOrFail()->warehouses()->sync($allWarehouseIds);
        }

        $warehouseAId = (int) Warehouse::query()->where('name', 'Warehouse A')->value('id');
        $warehouseBId = (int) Warehouse::query()->where('name', 'Warehouse B')->value('id');

        User::query()->where('email', 'staffa@example.com')->firstOrFail()->warehouses()->sync([$warehouseAId]);
        User::query()->where('email', 'staffb@example.com')->firstOrFail()->warehouses()->sync([$warehouseBId]);
        $supplierData = [
            ['Apex Tech Distributors', 'Apex Technology Distributors (Pvt.) Ltd.', 'Faraz Ali', '+92 300 555 0101', 'supplier01@example.test', 'Main Boulevard, Gulberg I, Lahore'],
            ['Crescent Office Systems', 'Crescent Office Systems (Pvt.) Ltd.', 'Ayesha Raza', '+92 300 555 0102', 'supplier02@example.test', 'Gulberg II, Lahore'],
            ['Pakline Electronics', 'Pakline Electronics', 'Hassan Malik', '+92 300 555 0103', 'supplier03@example.test', 'MM Alam Road, Gulberg III, Lahore'],
            ['Meridian Computer Supplies', 'Meridian Computer Supplies', 'Mariam Shah', '+92 300 555 0104', 'supplier04@example.test', 'Gulberg III, Lahore'],
            ['Gulberg Workspace Furnishings', 'Gulberg Workspace Furnishings', 'Bilal Hussain', '+92 300 555 0105', 'supplier05@example.test', 'Liberty Market, Gulberg III, Lahore'],
            ['Nova Lighting House', 'Nova Lighting House', 'Sana Khalid', '+92 300 555 0106', 'supplier06@example.test', 'Gulberg I, Lahore'],
            ['Vertex Network Solutions', 'Vertex Network Solutions', 'Usman Tariq', '+92 300 555 0107', 'supplier07@example.test', 'MM Alam Road, Gulberg III, Lahore'],
            ['Soundcraft Electronics', 'Soundcraft Electronics', 'Hira Ahmed', '+92 300 555 0108', 'supplier08@example.test', 'Gulberg II, Lahore'],
            ['DataLink Storage Supply', 'DataLink Storage Supply', 'Omer Farooq', '+92 300 555 0109', 'supplier09@example.test', 'Gulberg III, Lahore'],
            ['Printwell Office Equipment', 'Printwell Office Equipment', 'Laiba Nadeem', '+92 300 555 0110', 'supplier10@example.test', 'Main Boulevard, Gulberg II, Lahore'],
            ['Urban Shelf and Stationery', 'Urban Shelf and Stationery', 'Danish Rauf', '+92 300 555 0111', 'supplier11@example.test', 'Gulberg I, Lahore'],
            ['Scanpoint Retail Systems', 'Scanpoint Retail Systems', 'Zoya Javed', '+92 300 555 0112', 'supplier12@example.test', 'MM Alam Road, Gulberg III, Lahore'],
            ['Clearview Digital Supply', 'Clearview Digital Supply', 'Hamza Aslam', '+92 300 555 0113', 'supplier13@example.test', 'Gulberg II, Lahore'],
            ['Soundwave Traders', 'Soundwave Traders', 'Areeba Siddiqui', '+92 300 555 0114', 'supplier14@example.test', 'Gulberg III, Lahore'],
            ['Connective Cable House', 'Connective Cable House', 'Rizwan Akram', '+92 300 555 0115', 'supplier15@example.test', 'Main Boulevard, Gulberg I, Lahore'],
        ];
        $suppliers = collect($supplierData)->values()->map(function (array $data, int $index): Supplier {
            [$name, $companyName, $contactPerson, $phone, $email, $address] = $data;
            $legacyName = $index === 0 ? $name : 'Demo Supplier '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $supplier = Supplier::query()->whereIn('name', [$legacyName, $name])->first() ?? new Supplier;
            $supplier->fill([
                'name' => $name, 'company_name' => $companyName, 'contact_person' => $contactPerson,
                'phone' => $phone, 'email' => $email, 'address' => $address, 'status' => 'active',
            ])->save();

            return $supplier;
        });

        $productData = [
            ['PROD-ELEC-001', 'PROD-ELEC-001', 'Wireless Ergonomic Mouse', 'Electronics', 'A 2.4 GHz wireless optical mouse with a contoured grip and quiet-click switches.', 'wireless-ergonomic-mouse.jpg', 'Logitech', 15, 28.50],
            ['DEMO-PROD-002', 'PROD-COMP-002', 'Compact USB Keyboard', 'Computer Accessories', 'A compact wired USB keyboard with responsive keys for everyday office work.', 'compact-usb-keyboard.jpg', 'Logitech', 18, 32],
            ['DEMO-PROD-003', 'PROD-DISP-003', 'HD Monitor 24 Inch', 'Displays', 'A 24-inch full HD monitor for office work, spreadsheets, and video calls.', 'hd-monitor-24-inch.jpg', 'Dell', 105, 159],
            ['DEMO-PROD-004', 'PROD-FURN-004', 'Adjustable Office Desk', 'Furniture', 'A height-adjustable desk with a spacious work surface for office use.', 'adjustable-office-desk.jpg', 'Urban Workspaces', 145, 219],
            ['DEMO-PROD-005', 'PROD-FURN-005', 'Mesh Office Chair', 'Furniture', 'A breathable mesh office chair with adjustable height and lumbar support.', 'mesh-office-chair.jpg', 'ErgoSeat', 78, 129],
            ['DEMO-PROD-006', 'PROD-LITE-006', 'LED Desk Lamp', 'Lighting', 'An adjustable LED desk lamp with focused light for reading and detailed tasks.', 'led-desk-lamp.jpg', 'Philips', 12, 24],
            ['DEMO-PROD-007', 'PROD-NET-007', 'Dual Band WiFi Router', 'Networking', 'A dual-band Wi-Fi router with Ethernet ports for home and office networks.', 'dual-band-wifi-router.jpg', 'TP-Link', 42, 69],
            ['DEMO-PROD-008', 'PROD-AUD-008', 'Studio Headphones', 'Audio', 'Over-ear headphones with cushioned ear cups for calls and focused listening.', 'studio-headphones.jpg', 'CMF', 26, 45],
            ['DEMO-PROD-009', 'PROD-STOR-009', 'USB Flash Drive 64GB', 'Storage', 'A compact 64 GB USB drive for carrying documents and everyday files.', 'usb-flash-drive-64gb.jpg', 'SanDisk', 6, 12],
            ['DEMO-PROD-010', 'PROD-PRNT-010', 'Laser Printer', 'Printers', 'A monochrome laser printer for clear, fast printing of office documents.', 'laser-printer.jpg', 'HP', 115, 169],
            ['DEMO-PROD-011', 'PROD-OFF-011', 'Desktop Bookshelf', 'Office Supplies', 'A wooden desktop bookshelf with open shelves for books and office supplies.', 'desktop-bookshelf.jpg', 'Urban Workspaces', 22, 39],
            ['DEMO-PROD-012', 'PROD-COMP-012', 'Barcode Scanner', 'Components', 'A handheld barcode scanner with a charging dock for stock control.', 'barcode-scanner.jpg', 'Zebra', 31, 52],
            ['DEMO-PROD-013', 'PROD-CAM-013', 'Web Camera 1080p', 'Security', 'A 1080p USB webcam for clear video meetings and remote collaboration.', 'web-camera-1080p.jpg', 'Logitech', 19, 34],
            ['DEMO-PROD-014', 'PROD-AUD-014', 'Portable Speaker', 'Audio', 'A compact Bluetooth speaker with a durable enclosure for portable listening.', 'portable-speaker.jpg', 'Sony', 24, 42],
            ['DEMO-PROD-015', 'PROD-CABL-015', 'Network Cable 5m', 'Cables', 'A five-metre Ethernet cable with RJ45 connectors for wired network connections.', 'network-cable-5m.jpg', 'D-Link', 3, 8],
        ];
        $products = collect($productData)->values()->map(function (array $data, int $index) use ($categories): Product {
            [$legacySku, $sku, $name, $categoryName, $description, $imageName, $brand, $purchasePrice, $sellingPrice] = $data;
            $product = Product::query()->whereIn('sku', array_unique([$legacySku, $sku]))->first() ?? new Product;
            $product->fill([
                'sku' => $sku, 'name' => $name, 'description' => $description,
                'category_id' => $categories->get($categoryName)->id, 'brand' => $brand, 'unit' => 'pcs',
                'barcode' => sprintf('89012345%05d', $index + 1),
                'image_path' => 'products/catalog/'.$imageName,
                'purchase_price' => $purchasePrice, 'selling_price' => $sellingPrice,
                'minimum_stock' => 5 + ($index % 6), 'status' => 'active',
            ])->save();

            return $product;
        });

        foreach ($products as $index => $product) {
            $warehouse = $warehouses->get($index);
            $movementExists = $product->stockMovements()
                ->where('reference_type', 'sample_inventory_seed')
                ->where('reference_id', $product->id)
                ->exists();

            if (! $movementExists) {
                app(InventoryService::class)->adjustStock(
                    (int) $product->id, (int) $warehouse->id, 12 + ($index * 3),
                    MovementType::Adjustment, 'Opening stock recorded for warehouse replenishment',
                    (int) $admin->id, ['type' => 'sample_inventory_seed', 'id' => $product->id],
                );
            }
        }
        StockMovement::query()->where('reference_type', 'sample_inventory_seed')
            ->whereIn('reference_id', $products->pluck('id'))
            ->update(['reason' => 'Opening stock recorded for warehouse replenishment']);

        $purchaseStatuses = ['draft', 'pending_approval', 'approved'];
        foreach ($products as $index => $product) {
            $number = str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $quantity = 4 + ($index % 8);
            $lineTotal = round($quantity * (float) $product->purchase_price, 2);
            $status = $purchaseStatuses[$index % count($purchaseStatuses)];
            $purchaseNumber = 'PO-LHR-2026-'.$number;
            $purchase = Purchase::query()->whereIn('purchase_number', ['DEMO-PO-'.$number, $purchaseNumber])->first() ?? new Purchase;
            $purchase->fill([
                'purchase_number' => $purchaseNumber, 'supplier_id' => $suppliers->get($index)->id,
                'warehouse_id' => $warehouses->get($index)->id, 'status' => $status,
                'order_date' => now()->subDays($index)->toDateString(),
                'expected_date' => now()->addDays(3 + $index)->toDateString(),
                'subtotal' => $lineTotal, 'discount_amount' => 0, 'tax_amount' => 0,
                'grand_total' => $lineTotal, 'notes' => 'Scheduled replenishment of warehouse stock.',
                'rejection_reason' => null, 'created_by' => $manager->id,
                'approved_by' => $status === 'approved' ? $admin->id : null,
                'approved_at' => $status === 'approved' ? now()->subDays($index)->toDateTimeString() : null,
                'cancelled_at' => null,
            ])->save();
            PurchaseItem::query()->updateOrCreate(
                ['purchase_id' => $purchase->id, 'product_id' => $product->id],
                ['ordered_quantity' => $quantity, 'received_quantity' => 0, 'unit_price' => $product->purchase_price, 'line_total' => $lineTotal],
            );

            $paymentReference = 'PAY-LHR-2026-'.$number;
            $payment = SupplierPayment::query()->whereIn('reference', ['DEMO-PAY-'.$number, $paymentReference])->first() ?? new SupplierPayment;
            $payment->fill([
                'reference' => $paymentReference, 'supplier_id' => $suppliers->get($index)->id,
                'amount' => round($lineTotal / 2, 2), 'payment_date' => now()->subDays($index)->toDateString(),
                'created_by' => $admin->id,
            ])->save();
        }
    }
}
