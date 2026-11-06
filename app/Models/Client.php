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
        'company_name', 'organization', 'management', 'inn', 'director', 'director_position', 'oked',
        'bank_name', 'bank_mfo', 'bank_account', 'single_tax',
        'phone', 'phone2', 'email', 'region_id', 'district_id', 'address', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
            'birth_date' => 'date',
            'passport_issued_at' => 'date',
            'single_tax' => 'boolean',
        ];
    }

    /** Kinds of organisations on the form of a legal entity (Uzbek Latin, as saved). */
    public const ORGANIZATIONS = ['Xususiy tashkilot', 'Davlat tashkiloti', 'Bank', 'Xorijiy tashkilot', 'Boshqa'];

    /** Forms of management (organisational and legal forms). */
    public const MANAGEMENT = ['MChJ', 'AJ', 'XK', 'QK', 'OK', 'DUK', 'Davlat muassasasi', "Fermer xo'jaligi", "Dehqon xo'jaligi", 'YaTT', 'Boshqa'];

    /**
     * The head of a company as last name, first name and patronymic
     * (kept together in "director").
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    public function directorParts(): array
    {
        $parts = preg_split('/\s+/u', trim((string) $this->director), 3, PREG_SPLIT_NO_EMPTY) ?: [];

        return [$parts[0] ?? null, $parts[1] ?? null, $parts[2] ?? null];
    }

    /** The date of birth written in a PINFL (its first seven digits: century and sex, day, month, year). */
    public static function birthDateFromPinfl(?string $pinfl): ?string
    {
        if (! is_string($pinfl) || ! preg_match('/^([1-6])(\d{2})(\d{2})(\d{2})\d{7}$/', $pinfl, $m)) {
            return null;
        }
        $century = [1 => 1800, 2 => 1800, 3 => 1900, 4 => 1900, 5 => 2000, 6 => 2000][(int) $m[1]];
        $year = $century + (int) $m[4];

        return checkdate((int) $m[3], (int) $m[2], $year) && $year <= (int) date('Y') ? sprintf('%04d-%02d-%02d', $year, $m[3], $m[2]) : null;
    }

    protected static function booted(): void
    {
        static::creating(fn (Client $client) => $client->created_by ??= auth()->id());
    }

    /** Who added the client. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
