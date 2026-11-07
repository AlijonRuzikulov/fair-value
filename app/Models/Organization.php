<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bank or a valuation company the system is given to. Its people work in
 * its own workspace (see App\Support\Tenant) and its contracts and reports
 * carry its own company details, kept in "settings".
 */
class Organization extends Model
{
    protected $fillable = ['name', 'settings'];

    /** Details each organisation keeps for itself; printed on its contracts, reports and invoices. */
    public const DETAILS = [
        'legal_name', 'legal_name_ru', 'director', 'chief_accountant', 'license', 'insurance_policy', 'membership',
        'inn', 'oked', 'phone', 'phone2', 'email', 'address', 'bank_name', 'bank_account', 'bank_mfo',
        'report_city', 'vat_rate', 'contract_number_format', 'report_number_format', 'require_approval',
    ];

    /** Settings an organisation does not take from the company that runs the system, even when it leaves them empty. */
    public const OWN_SETTINGS = [...self::DETAILS, 'name', 'report_documents', 'report_script'];

    /** Rules of work rather than details: an empty one keeps the usual value from config/fairvalue.php. */
    private const USUAL = ['report_city', 'vat_rate', 'contract_number_format', 'report_number_format', 'require_approval', 'report_script'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** A setting of this organisation; its name stands for the company name until a legal name is written. */
    public function setting(string $key, ?string $default = null): ?string
    {
        $value = $this->settings[$key] ?? null;
        if (filled($value)) {
            return (string) $value;
        }

        return $default ?? match (true) {
            $key === 'name', $key === 'legal_name' => $this->name,
            in_array($key, self::USUAL, true) => config("fairvalue.company.{$key}"),
            default => null,
        };
    }
}
