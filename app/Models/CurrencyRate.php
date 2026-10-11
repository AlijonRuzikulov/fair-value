<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    protected $fillable = ['code', 'nominal', 'rate', 'diff', 'rate_date'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'diff' => 'decimal:4', 'rate_date' => 'date'];
    }
}
