<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A landmark (mo'ljal) of a district: one of the portal's list (kind
 * "portal", see LandmarkSeeder) or a mahalla, a massif, a village, a market,
 * a metro station… taken from OpenStreetMap where the portal has no list
 * (see LandmarkFinder).
 */
class Landmark extends Model
{
    protected $fillable = ['district_id', 'name', 'kind', 'latitude', 'longitude'];

    /** Kinds in the order they are listed. */
    public const KINDS = ['portal', 'mahalla', 'area', 'village', 'metro', 'market', 'mall', 'station', 'park', 'hospital', 'education'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float'];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'portal' => '',
            'mahalla' => __('Mahalla'),
            'area' => __('Massif, quarter'),
            'village' => __('Village, settlement'),
            'metro' => __('Metro station'),
            'market' => __('Market'),
            'mall' => __('Shopping centre'),
            'station' => __('Station'),
            'park' => __('Park'),
            'hospital' => __('Hospital'),
            'education' => __('Higher education institution'),
            default => __('Used before'),
        };
    }
}
