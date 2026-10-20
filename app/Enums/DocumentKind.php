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
            'vehicle', 'damage_case' => [self::Passport, self::Application, self::TechnicalPassport, self::Photo, self::Other],
            default => [self::Passport, self::Application, self::Photo, self::Other],
        };
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
            $isImage => self::Photo,
            default => self::Other,
        };
    }
}
