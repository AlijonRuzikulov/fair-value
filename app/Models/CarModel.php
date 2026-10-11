<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Автолар рўйхати" — the catalogue of vehicle models.
 */
class CarModel extends Model
{
    use LogsActivity;

    protected $fillable = ['brand', 'model', 'category', 'body_type', 'years', 'labor_rate', 'notes'];

    protected function casts(): array
    {
        return ['labor_rate' => 'decimal:2'];
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class);
    }

    public function repairNorms(): HasMany
    {
        return $this->hasMany(RepairNorm::class);
    }

    public function getTitleAttribute(): string
    {
        return trim($this->brand.' '.$this->model);
    }
}
