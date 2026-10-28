<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of products with search, filters, and sorting.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::with('category');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sortField = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        if (in_array($sortField, ['name', 'selling_price', 'created_at'])) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        return ProductResource::collection($query->paginate(15));
    }

    /**
     * Store a newly created product with optional image upload.
     */
    public function store(ProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
            $data['image_path'] = $imagePath;
        }

        unset($data['image']);

        try {
            $product = Product::create($data);
        } catch (\Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }
        $product->load('category');

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): ProductResource
    {
        $product->load(['category', 'balances.warehouse']);

        return new ProductResource($product);
    }

    /**
     * Update the specified product with optional new image.
     */
    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $data = $request->validated();
        $previousImagePath = $product->image_path;
        $newImagePath = null;

        if ($request->hasFile('image')) {
            $newImagePath = $request->file('image')->store('products', 'public');
            $data['image_path'] = $newImagePath;
        }

        unset($data['image']);

        try {
            $product->update($data);
        } catch (\Throwable $exception) {
            if ($newImagePath !== null) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($newImagePath !== null && $previousImagePath !== null) {
            Storage::disk('public')->delete($previousImagePath);
        }
        $product->load('category');

        return new ProductResource($product);
    }

    /**
     * Archive or delete product safely.
     */
    public function destroy(Product $product): JsonResponse
    {
        $hasOperationalHistory = $product->balances()->exists()
            || $product->stockMovements()->exists()
            || DB::table('purchase_items')->where('product_id', $product->id)->exists()
            || DB::table('sales_order_items')->where('product_id', $product->id)->exists()
            || DB::table('warehouse_transfer_items')->where('product_id', $product->id)->exists()
            || DB::table('stock_adjustments')->where('product_id', $product->id)->exists()
            || DB::table('sales_return_items')->where('product_id', $product->id)->exists();

        if ($hasOperationalHistory) {
            $product->update(['status' => 'archived']);

            return response()->json([
                'message' => 'Product has operational history and was archived instead of deleted.',
                'data' => new ProductResource($product),
            ]);
        }

        if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
