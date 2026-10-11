<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * "Кичик эҳтиёт қисмлар" — clips, bolts, seals and other consumables.
 */
class SmallPart extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'unit', 'price', 'price_date', 'notes'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'price_date' => 'date'];
    }
}
