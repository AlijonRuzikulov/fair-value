<?php

namespace App\Support;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * The workspace of the signed-in person: the organisation they work for, or
 * none for the company that runs the system. Set for the admin panel (see
 * SetWorkspace); clients, valuation objects and the audit log are then read
 * and written only within it. Outside the panel (the website, the console)
 * no workspace is set and nothing is limited.
 */
final class Tenant
{
    private function __construct(public readonly ?Organization $organization) {}

    /** Work in the workspace of this organisation; null is the company that runs the system. */
    public static function set(?Organization $organization): void
    {
        app()->instance(self::class, new self($organization));
    }

    public static function clear(): void
    {
        app()->forgetInstance(self::class);
    }

    public static function active(): bool
    {
        return app()->bound(self::class);
    }

    public static function organization(): ?Organization
    {
        return self::active() ? app(self::class)->organization : null;
    }

    public static function id(): ?int
    {
        return self::organization()?->id;
    }

    /**
     * Limits a query to the workspace.
     *
     * @template T of Builder
     *
     * @param  T  $query
     * @return T
     */
    public static function constrain(Builder $query, string $column = 'organization_id'): Builder
    {
        if (! self::active()) {
            return $query;
        }
        $id = self::id();

        return $id === null ? $query->whereNull($column) : $query->where($column, $id);
    }

    /** A validation rule: a record of this table in the workspace. */
    public static function exists(string $table): Exists
    {
        return Rule::exists($table, 'id')->where(fn ($query) => self::constrain($query));
    }

    /** Whether a record of this organisation is in the workspace. */
    public static function sees(?int $organizationId): bool
    {
        return ! self::active() || self::id() === ($organizationId === null ? null : (int) $organizationId);
    }

    /** Whether the record a file or an offer belongs to (by its morph type and key) is in the workspace. */
    public static function owns(?string $type, mixed $id): bool
    {
        $class = $type ? (Relation::getMorphedModel($type) ?? $type) : null;
        if (! self::active() || ! $class || ! class_exists($class) || ! in_array(BelongsToOrganization::class, class_uses_recursive($class), true)) {
            return true;
        }

        return self::sees($class::withoutGlobalScopes()->whereKey($id)->value('organization_id'));
    }

    /** The name an organisation is known by; null is the company that runs the system. */
    public static function label(?Organization $organization): string
    {
        return $organization?->name ?? (string) Setting::main('name');
    }
}
