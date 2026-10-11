<?php

namespace App\Enums;

enum ValuationPurpose: string
{
    use HasOptions;

    case Collateral = 'collateral';
    case Sale = 'sale';
    case Notary = 'notary';
    case Court = 'court';
    case Insurance = 'insurance';
    case CharterCapital = 'charter_capital';
    case Accounting = 'accounting';
    case Lease = 'lease';
    case Compensation = 'compensation';
    case Damage = 'damage';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Collateral => __('Bank loan collateral'),
            self::Sale => __('Purchase and sale'),
            self::Notary => __('Notarial actions (inheritance, gift)'),
            self::Court => __('For court'),
            self::Insurance => __('Insurance'),
            self::CharterCapital => __('Contribution to charter capital'),
            self::Accounting => __('Accounting and balance sheet'),
            self::Lease => __('Lease'),
            self::Compensation => __('Compensation for seized property'),
            self::Damage => __('Determining damage'),
            self::Other => __('Other'),
        };
    }
}
