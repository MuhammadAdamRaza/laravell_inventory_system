<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTransferItem extends Model
{
    protected $fillable = ['warehouse_transfer_id', 'product_id', 'dispatched_quantity', 'accepted_quantity', 'returned_quantity'];

    protected function casts(): array
    {
        return ['dispatched_quantity' => 'integer', 'accepted_quantity' => 'integer', 'returned_quantity' => 'integer'];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransfer::class, 'warehouse_transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
