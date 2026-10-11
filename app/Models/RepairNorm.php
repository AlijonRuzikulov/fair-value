<?php

namespace App\Models;

use App\Enums\RepairOperation;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Таъмир муддати нормалари" — standard hours for repair operations.
 */
class RepairNorm extends Model
{
    use LogsActivity;

    protected $fillable = ['car_model_id', 'part_name', 'operation', 'hours', 'notes'];

    protected function casts(): array
    {
        return ['hours' => 'decimal:2', 'operation' => RepairOperation::class];
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    public function getTitleAttribute(): string
    {
        return $this->part_name.' — '.$this->operation?->label();
    }
}
