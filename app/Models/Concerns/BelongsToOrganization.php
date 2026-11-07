<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records of a workspace: read only within the workspace of the signed-in
 * person and created in it (see App\Support\Tenant).
 */
trait BelongsToOrganization
{
    public const WORKSPACE_SCOPE = 'organization';

    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(self::WORKSPACE_SCOPE, fn (Builder $query) => Tenant::constrain($query, $query->qualifyColumn('organization_id')));

        static::creating(function (Model $model) {
            if (Tenant::active() && ! $model->isDirty('organization_id')) {
                $model->organization_id = Tenant::id();
            }
        });
    }

    /** The organisation whose workspace the record is in (none: the company that runs the system). */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
