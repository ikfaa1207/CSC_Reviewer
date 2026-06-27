<?php

namespace App\Enums;

enum ExamLevel: string
{
    case PROFESSIONAL = 'professional';
    case SUB_PROFESSIONAL = 'sub_professional';
    case BOTH = 'both';

    /**
     * Get the display name for the level.
     */
    public function label(): string
    {
        return match($this) {
            self::PROFESSIONAL => 'Professional',
            self::SUB_PROFESSIONAL => 'Sub-Professional',
            self::BOTH => 'Both',
        };
    }

    /**
     * Get all levels as an array for dropdowns.
     */
    public static function options(): array
    {
        return [
            self::PROFESSIONAL->value => self::PROFESSIONAL->label(),
            self::SUB_PROFESSIONAL->value => self::SUB_PROFESSIONAL->label(),
            self::BOTH->value => self::BOTH->label(),
        ];
    }

    /**
     * Create from a string value.
     */
    public static function fromString(string $value): self
    {
        return match(strtolower($value)) {
            'professional' => self::PROFESSIONAL,
            'sub_professional', 'subprofessional' => self::SUB_PROFESSIONAL,
            default => self::BOTH,
        };
    }
}
