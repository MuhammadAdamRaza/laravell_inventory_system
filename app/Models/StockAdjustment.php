<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['adjustment_number', 'warehouse_id', 'product_id', 'quantity_change', 'reason_code', 'reason', 'created_by', 'created_at'];

    protected function casts(): array
    {
        return ['quantity_change' => 'integer', 'created_at' => 'datetime'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
