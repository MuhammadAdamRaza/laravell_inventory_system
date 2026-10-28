<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'movement_type',
        'quantity_change',
        'balance_after',
        'reference_type',
        'reference_id',
        'reason',
        'user_id',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
            'quantity_change' => 'integer',
            'balance_after' => 'integer',
            'reference_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Product moved.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Warehouse where movement happened.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * User who initiated movement.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope query to movements accessible by a specific user.
     *
     * @param  Builder<StockMovement>  $query
     * @return Builder<StockMovement>
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Warehouse Staff')) {
            $assignedWarehouseIds = $user->warehouses()->pluck('warehouses.id');

            return $query->whereIn('warehouse_id', $assignedWarehouseIds);
        }

        return $query;
    }
}
