<?php

namespace App\Models;

use App\Enums\DamageItemType;
use App\Enums\RepairOperation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageCaseItem extends Model
{
    protected $fillable = ['type', 'name', 'operation', 'quantity', 'hours', 'unit_price', 'amount', 'sort'];

    protected function casts(): array
    {
        return [
            'type' => DamageItemType::class,
            'quantity' => 'decimal:2',
            'hours' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function damageCase(): BelongsTo
    {
        return $this->belongsTo(DamageCase::class);
    }

    public function operationLabel(): ?string
    {
        return $this->operation ? RepairOperation::labelFor($this->operation) : null;
    }
}
