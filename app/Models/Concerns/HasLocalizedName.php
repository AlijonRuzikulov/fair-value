<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Directory entries stored in three languages: name_uz, name_oz, name_ru.
 */
trait HasLocalizedName
{
    public function getNameAttribute(): string
    {
        $column = 'name_'.app()->getLocale();

        return (string) ($this->attributes[$column] ?? $this->attributes['name_uz'] ?? '');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('name_uz');
    }
}
