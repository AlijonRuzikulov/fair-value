<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    use HasLocalizedName;

    protected $fillable = ['region_id', 'name_uz', 'name_oz', 'name_ru', 'sort'];

    protected function casts(): array
    {
        return ['landmarks_fetched_at' => 'datetime'];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function landmarks(): HasMany
    {
        return $this->hasMany(Landmark::class);
    }
}
