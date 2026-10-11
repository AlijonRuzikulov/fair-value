<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Авто эҳтиёт қисмлар" — spare part prices.
 */
class SparePart extends Model
{
    use LogsActivity;

    protected $fillable = ['car_model_id', 'name', 'part_number', 'unit', 'price', 'price_date', 'notes'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'price_date' => 'date'];
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }
}
