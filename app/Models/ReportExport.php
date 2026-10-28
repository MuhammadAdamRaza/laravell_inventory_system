<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    protected $fillable = ['id', 'created_by', 'report', 'filters', 'status', 'file_path', 'file_name'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }

    /**
     * User who requested this export.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
