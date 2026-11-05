<?php

namespace App\Support;

use App\Enums\ValuationStatus;
use App\Models\RealEstate;
use Illuminate\Database\Eloquent\Model;

/**
 * The four steps of a valuation on the valuation portal: the main data
 * saved, the approaches calculated, reconciled and approved, the end.
 * Shown over the object page and in the "Jarayon" column of the lists.
 */
final class ValuationProgress
{
    /**
     * Step label => done.
     *
     * @return array<string, bool>
     */
    public static function steps(Model $item, bool $short = false): array
    {
        $applied = $item->exists ? $item->approachList() : [];
        $main = $item->exists && $item->region_id !== null && (! $item instanceof RealEstate || $item->total_area > 0);
        // Without approaches (damage) the amount is what is worked out.
        $calculated = $applied !== []
            ? collect($applied)->every(fn ($a) => $item->{$a->column()} !== null)
            : $item->market_value !== null;

        return [
            ($short ? __('Main data') : __('Saving the main data of the real estate')) => $main,
            ($short ? __('Calculation') : __('Calculating the approaches')) => $calculated,
            ($short ? __('Approval') : __('Reconciliation and approval')) => $item->market_value !== null
                && in_array($item->status, [ValuationStatus::Review, ValuationStatus::Completed], true),
            __('The end') => $item->status === ValuationStatus::Completed,
        ];
    }

    /** Index of the first step not done; false when all are. */
    public static function current(array $steps): int|false
    {
        return array_search(false, array_values($steps), true);
    }
}
