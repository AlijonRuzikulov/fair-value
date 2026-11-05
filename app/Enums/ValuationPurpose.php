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
    // Purposes of the valuation portal.
    case ZeroPrivatization = 'zero_privatization';
    case Liquidation = 'liquidation';
    case Investment = 'investment';
    case Privatization = 'privatization';
    case Business = 'business';
    case Balance = 'balance';
    case Inheritance = 'inheritance';
    case Tax = 'tax';
    case Value = 'value';

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
            self::ZeroPrivatization => __('Privatisation at zero value'),
            self::Liquidation => __('Liquidation value'),
            self::Investment => __('Determining the investment value'),
            self::Privatization => __('Privatisation'),
            self::Business => __('Determining the value of a business'),
            self::Balance => __('Taking onto the balance sheet'),
            self::Inheritance => __('Inheritance'),
            self::Tax => __('Taxation'),
            self::Value => __('Determining the value'),
        };
    }

    /**
     * The purposes of real estate in the order of the valuation portal
     * (a purpose chosen before stays in the list).
     *
     * @return array<string, string>
     */
    public static function portalOptions(?string $current = null): array
    {
        $order = [
            self::Collateral, self::ZeroPrivatization, self::Liquidation, self::Investment, self::Privatization, self::CharterCapital,
            self::Business, self::Sale, self::Balance, self::Damage, self::Inheritance, self::Tax, self::Value,
        ];
        $options = [];
        foreach ($order as $case) {
            $options[$case->value] = $case->portalLabel();
        }
        if ($current !== null && ! isset($options[$current]) && ($case = self::tryFrom($current))) {
            $options[$current] = $case->label();
        }

        return $options;
    }

    /** The short word of the portal's lists (under the number of the row). */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Collateral => __('Pledge'),
            default => $this->portalLabel(),
        };
    }

    /** The wording of the valuation portal. */
    public function portalLabel(): string
    {
        return match ($this) {
            self::Collateral => __('Pledge as collateral'),
            self::CharterCapital => __('Forming the charter fund'),
            self::Sale => __('Sale'),
            self::Damage => __('Determining the amount of material damage caused'),
            default => $this->label(),
        };
    }
}
