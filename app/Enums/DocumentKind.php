<?php

namespace App\Enums;

/**
 * What an attached file is. Valuation objects show a place for each kind
 * of document they need, so a missing passport or extract is seen at once.
 */
enum DocumentKind: string
{
    use HasOptions;

    case Passport = 'passport';
    case Application = 'application';
    case Cadastral = 'cadastral';
    case TechnicalPassport = 'technical_passport';
    case Contract = 'contract';
    // Asked for movable property and vehicles on the valuation portal (sales by the enforcement bureau).
    case Act = 'act';
    case Decision = 'decision';
    case Writ = 'writ';
    case Photo = 'photo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Passport => __('Copy of the passport (ID card)'),
            self::Application => __('Application'),
            self::Cadastral => __('Cadastral document'),
            self::TechnicalPassport => __('Technical passport'),
            self::Contract => __('Contract'),
            self::Act => __('Act'),
            self::Decision => __('Decision'),
            self::Writ => __('Writ of execution'),
            self::Photo => __('Photos of the object'),
            self::Other => __('Other files'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Passport => 'user',
            self::Application => 'file-pen-line',
            self::Cadastral => 'landmark',
            self::TechnicalPassport => 'file-badge',
            self::Contract => 'handshake',
            self::Act => 'file-check',
            self::Decision => 'gavel',
            self::Writ => 'scroll-text',
            self::Photo => 'camera',
            self::Other => 'paperclip',
        };
    }

    /**
     * Places for documents on the page of an object of this type (morph alias).
     *
     * @return list<self>
     */
    public static function slotsFor(string $type): array
    {
        return match ($type) {
            'real_estate' => [self::Passport, self::Application, self::Cadastral, self::TechnicalPassport, self::Photo, self::Other],
            'asset' => [self::Passport, self::Act, self::Decision, self::Writ, self::Application, self::Photo, self::Other],
            'vehicle' => [self::Passport, self::Act, self::Decision, self::Writ, self::Application, self::TechnicalPassport, self::Photo, self::Other],
            'damage_case' => [self::Passport, self::Application, self::TechnicalPassport, self::Photo, self::Other],
            default => [self::Passport, self::Application, self::Photo, self::Other],
        };
    }

    /**
     * The boxes of the documents on the valuation portal's page of an object
     * of this type; files of other kinds get a box of their own when there are any.
     *
     * @return list<self>
     */
    public static function portalSlotsFor(string $type): array
    {
        return match ($type) {
            'asset', 'vehicle' => [self::Passport, self::Act, self::Decision, self::Writ],
            default => self::slotsFor($type),
        };
    }

    /** The name of the box on the valuation portal. */
    public function portalLabel(): string
    {
        return $this === self::Passport ? __('Copy of the passport') : $this->label();
    }

    /**
     * Documents without which a valuation of this type cannot be completed.
     *
     * @return list<self>
     */
    public static function requiredFor(string $type): array
    {
        return match ($type) {
            'real_estate' => [self::Passport, self::Cadastral, self::Photo],
            'vehicle', 'damage_case' => [self::Passport, self::TechnicalPassport, self::Photo],
            default => [self::Passport, self::Photo],
        };
    }

    /** The kind told by the name of a file, for files uploaded without one. */
    public static function guess(string $name, bool $isImage): self
    {
        return match (true) {
            (bool) preg_match('/pasport|passport|паспорт|id.?card|id.?karta/iu', $name) => self::Passport,
            (bool) preg_match('/ariza|ариза|zayavlen|заявлен/iu', $name) => self::Application,
            (bool) preg_match('/kadastr|кадастр|ko.?chirma|кўчирма|выписк|guvohnoma|гувоҳнома/iu', $name) => self::Cadastral,
            (bool) preg_match('/tex.?pas|тех.?пас|texnik|техник/iu', $name) => self::TechnicalPassport,
            (bool) preg_match('/shartnoma|шартнома|dogovor|договор|contract/iu', $name) => self::Contract,
            (bool) preg_match('/ijro|ижро|исполнит/iu', $name) => self::Writ,
            (bool) preg_match('/qaror|қарор|карор|решени|постановлени/iu', $name) => self::Decision,
            (bool) preg_match('/(^|[^a-zа-я])(akt|акт)([^a-zа-я]|$)|dalolatnoma|далолатнома/iu', $name) => self::Act,
            $isImage => self::Photo,
            default => self::Other,
        };
    }
}
