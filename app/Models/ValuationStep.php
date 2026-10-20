<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A step of the approval of a valuation: submitted by the appraiser,
 * approved or returned by the director, reopened, cancelled, or the act
 * signed by the customer.
 */
class ValuationStep extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = ['submitted', 'approved', 'returned', 'reopened', 'cancelled', 'accepted'];

    protected $fillable = ['action', 'user_id', 'comment'];

    public function stepable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function label(string $action): string
    {
        return match ($action) {
            'submitted' => __('Submitted for approval'),
            'approved' => __('Approved'),
            'returned' => __('Returned for correction'),
            'reopened' => __('Reopened for revaluation'),
            'cancelled' => __('Cancelled'),
            'accepted' => __('Act signed by the customer'),
            default => $action,
        };
    }
}
