<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * "Мижозлар" — individuals and legal entities.
 */
class Client extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'type', 'last_name', 'first_name', 'middle_name', 'birth_date', 'pinfl',
        'passport_series', 'passport_number', 'passport_issued_by', 'passport_issued_at',
        'company_name', 'inn', 'director', 'oked', 'bank_name', 'bank_mfo', 'bank_account',
        'phone', 'phone2', 'email', 'region_id', 'district_id', 'address', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
            'birth_date' => 'date',
            'passport_issued_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Client $client) => $client->created_by ??= auth()->id());
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }

    public function realEstates(): MorphToMany
    {
        return $this->morphedByMany(RealEstate::class, 'object', 'client_object')->withPivot('role');
    }

    public function assets(): MorphToMany
    {
        return $this->morphedByMany(Asset::class, 'object', 'client_object')->withPivot('role');
    }

    public function vehicles(): MorphToMany
    {
        return $this->morphedByMany(Vehicle::class, 'object', 'client_object')->withPivot('role');
    }

    public function otherAssets(): MorphToMany
    {
        return $this->morphedByMany(OtherAsset::class, 'object', 'client_object')->withPivot('role');
    }

    public function damageCases(): MorphToMany
    {
        return $this->morphedByMany(DamageCase::class, 'object', 'client_object')->withPivot('role');
    }

    public function isLegal(): bool
    {
        return $this->type === ClientType::Legal;
    }

    /** Full name of a person or the name of a company. */
    public function getTitleAttribute(): string
    {
        if ($this->isLegal()) {
            return (string) $this->company_name;
        }

        return trim(implode(' ', array_filter([$this->last_name, $this->first_name, $this->middle_name])));
    }

    /** "Aliyev V. B." */
    public function getShortNameAttribute(): string
    {
        if ($this->isLegal()) {
            return (string) $this->company_name;
        }
        $initials = collect([$this->first_name, $this->middle_name])
            ->filter()
            ->map(fn ($p) => mb_substr($p, 0, 1).'.')
            ->implode(' ');

        return trim($this->last_name.' '.$initials);
    }

    /** Passport for individuals, taxpayer number for companies. */
    public function getDocumentAttribute(): ?string
    {
        if ($this->isLegal()) {
            return $this->inn ? __('TIN').' '.$this->inn : null;
        }
        $passport = trim($this->passport_series.' '.$this->passport_number);

        return $passport !== '' ? $passport : null;
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('last_name', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('middle_name', 'like', "%{$term}%")
                ->orWhere('company_name', 'like', "%{$term}%")
                ->orWhere('pinfl', 'like', "%{$term}%")
                ->orWhere('inn', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('passport_number', 'like', "%{$term}%");

            // "AA 1234567" or "AA1234567"
            if (preg_match('/^([A-Za-z]{2})\s*(\d{3,})$/', $term, $m)) {
                $q->orWhere(fn (Builder $p) => $p
                    ->where('passport_series', strtoupper($m[1]))
                    ->where('passport_number', 'like', $m[2].'%'));
            }
        });
    }

    /** Number of valuation objects the client is linked to. */
    public function objectsCount(): int
    {
        return DB::table('client_object')->where('client_id', $this->id)->count();
    }
}
