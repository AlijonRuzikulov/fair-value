<?php

namespace App\Models;

use App\Support\ComparativeCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A comparable sale offer ("analogue") of the comparative approach: taken
 * from a listing site or typed in by the appraiser.
 */
class Analog extends Model
{
    public const PENDING = 'pending';

    public const READY = 'ready';

    public const FAILED = 'failed';

    protected $fillable = [
        'status', 'source', 'url', 'title', 'price', 'currency', 'usd_rate', 'price_uzs',
        'area', 'rooms', 'floor', 'floors_total', 'location', 'condition', 'listed_at',
        'adjustments', 'included', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'usd_rate' => 'decimal:2',
            'price_uzs' => 'decimal:2',
            'area' => 'decimal:2',
            'listed_at' => 'date',
            'adjustments' => 'array',
            'included' => 'boolean',
        ];
    }

    public function object(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isUsable(): bool
    {
        return $this->status === self::READY && (float) $this->price_uzs > 0 && (float) $this->area > 0;
    }

    public function pricePerSquareMeter(): ?float
    {
        return $this->isUsable() ? round((float) $this->price_uzs / (float) $this->area, 2) : null;
    }

    /** Adjustment in percent, by key (see ComparativeCalculator::ADJUSTMENTS). */
    public function adjustment(string $key): float
    {
        return (float) ($this->adjustments[$key] ?? 0);
    }

    public function totalAdjustment(): float
    {
        return array_sum(array_map(fn ($key) => $this->adjustment($key), ComparativeCalculator::ADJUSTMENTS));
    }

    public function host(): ?string
    {
        $host = $this->url ? parse_url($this->url, PHP_URL_HOST) : null;

        return $host ? preg_replace('/^(www|m)\./', '', $host) : null;
    }
}
