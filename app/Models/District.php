<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class District extends Model
{
    use HasLocalizedName;

    protected $fillable = ['region_id', 'name_uz', 'name_oz', 'name_ru', 'sort'];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
