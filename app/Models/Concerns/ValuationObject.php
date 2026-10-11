<?php

namespace App\Models\Concerns;

use App\Enums\PersonRole;
use App\Enums\ValuationApproach;
use App\Enums\ValuationPurpose;
use App\Enums\ValuationStatus;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\District;
use App\Models\Region;
use App\Models\User;
use App\Support\ReportNumber;
use App\Support\VerifyCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Behaviour shared by everything that gets valued: real estate, movable
 * property, vehicles, other assets and damage cases.
 *
 * @property ValuationStatus $status
 */
trait ValuationObject
{
    use LogsActivity, SoftDeletes;

    /** Fillable attributes common to all valuation objects. */
    public const VALUATION_FIELDS = [
        'region_id', 'district_id', 'address', 'purpose', 'approaches',
        'value_cost', 'value_comparative', 'value_income', 'market_value',
        'valuation_date', 'inspection_date', 'contract_number', 'contract_date',
        'service_fee', 'report_number', 'status', 'appraiser_id', 'notes',
    ];

    public static function bootValuationObject(): void
    {
        static::creating(function (self $model) {
            $model->verify_code ??= VerifyCode::generate();
            $model->created_by ??= auth()->id();
            $model->status ??= ValuationStatus::New;
        });

        static::saving(function (self $model) {
            if ($model->status === ValuationStatus::Completed) {
                $model->completed_at ??= now();
                if (blank($model->report_number)) {
                    $model->report_number = ReportNumber::next();
                }
            } elseif ($model->isDirty('status')) {
                $model->completed_at = null;
            }
        });
    }

    public function initializeValuationObject(): void
    {
        $this->mergeCasts([
            'approaches' => 'array',
            'value_cost' => 'decimal:2',
            'value_comparative' => 'decimal:2',
            'value_income' => 'decimal:2',
            'market_value' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'valuation_date' => 'date',
            'inspection_date' => 'date',
            'contract_date' => 'date',
            'completed_at' => 'datetime',
            'status' => ValuationStatus::class,
        ]);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function appraiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appraiser_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** "Шахслар" — owners, customers and other people linked to the object. */
    public function clients(): MorphToMany
    {
        return $this->morphToMany(Client::class, 'object', 'client_object')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }

    /**
     * @param  array<int, array{client_id:int|string, role:string}>  $persons
     */
    public function syncPersons(array $persons): void
    {
        $this->clients()->detach();
        foreach ($persons as $person) {
            if (! empty($person['client_id'])) {
                $this->clients()->attach($person['client_id'], [
                    'role' => PersonRole::tryFrom($person['role'] ?? '')?->value ?? PersonRole::Owner->value,
                ]);
            }
        }
    }

    /**
     * Applies the filters of the list page.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                foreach (static::searchable() as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
                $q->orWhere('report_number', 'like', "%{$search}%")
                    ->orWhere('contract_number', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhereHas('clients', fn (Builder $c) => $c->search($search));
            });
        }

        return $query
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['region_id'] ?? null, fn (Builder $q, $v) => $q->where('region_id', $v))
            ->when($filters['district_id'] ?? null, fn (Builder $q, $v) => $q->where('district_id', $v))
            ->when($filters['appraiser_id'] ?? null, fn (Builder $q, $v) => $q->where('appraiser_id', $v))
            ->when($filters['purpose'] ?? null, fn (Builder $q, $v) => $q->where('purpose', $v))
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v));
    }

    /** Columns searched by the free text filter. */
    abstract public static function searchable(): array;

    /** Name of the object type, e.g. "Vehicle". */
    abstract public function typeLabel(): string;

    /** Route name prefix in the admin panel, e.g. "admin.vehicles". */
    abstract public function routePrefix(): string;

    /**
     * Facts shown on the public report verification page. Personal data of
     * clients is never included.
     *
     * @return array<string, string|null>
     */
    abstract public function publicFacts(): array;

    /** @return list<ValuationApproach> */
    public function approachList(): array
    {
        return array_values(array_filter(array_map(
            fn ($v) => ValuationApproach::tryFrom((string) $v),
            (array) $this->approaches
        )));
    }

    public function purposeLabel(): ?string
    {
        return $this->purpose ? ValuationPurpose::labelFor($this->purpose) : null;
    }

    public function owner(): ?Client
    {
        return $this->clients->firstWhere('pivot.role', PersonRole::Owner->value) ?? $this->clients->first();
    }

    public function locationLine(): string
    {
        return collect([$this->region?->name, $this->district?->name, $this->address])->filter()->implode(', ');
    }

    public function verifyUrl(): string
    {
        return route('verify', ['code' => $this->verify_code]);
    }
}
