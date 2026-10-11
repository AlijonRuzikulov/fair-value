<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use HasLocalizedName;

    protected $fillable = ['name_uz', 'name_oz', 'name_ru', 'sort'];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class)->orderBy('sort')->orderBy('name_uz');
    }
}
