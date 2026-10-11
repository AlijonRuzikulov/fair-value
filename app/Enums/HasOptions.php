<?php

namespace App\Enums;

/**
 * Shared helpers for enums that are shown in the interface.
 */
trait HasOptions
{
    abstract public function label(): string;

    /**
     * @return array<string, string> value => translated label
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labelFor(?string $value): string
    {
        return $value !== null && ($case = self::tryFrom($value)) ? $case->label() : (string) $value;
    }
}
