<?php

namespace App\Enums;

enum AuditStatus: string
{
    case PASSED = 'passed';
    case FAILED_STRUCTURE = 'failed_structure';
    case FAILED_FACTS = 'failed_facts';
    case DUPLICATE = 'duplicate';
    case PENDING = 'pending';

    /**
     * Get the display name for the status.
     */
    public function label(): string
    {
        return match($this) {
            self::PASSED => 'Passed',
            self::FAILED_STRUCTURE => 'Failed - Structure',
            self::FAILED_FACTS => 'Failed - Facts',
            self::DUPLICATE => 'Duplicate',
            self::PENDING => 'Pending',
        };
    }

    /**
     * Check if this status indicates a failure.
     */
    public function isFailed(): bool
    {
        return match($this) {
            self::PASSED, self::PENDING => false,
            default => true,
        };
    }

    /**
     * Get all statuses as an array for dropdowns.
     */
    public static function options(): array
    {
        return [
            self::PASSED->value => self::PASSED->label(),
            self::FAILED_STRUCTURE->value => self::FAILED_STRUCTURE->label(),
            self::FAILED_FACTS->value => self::FAILED_FACTS->label(),
            self::DUPLICATE->value => self::DUPLICATE->label(),
            self::PENDING->value => self::PENDING->label(),
        ];
    }

    /**
     * Create from a string value.
     */
    public static function fromString(?string $value): self
    {
        if ($value === null) {
            return self::PENDING;
        }
        return match(strtolower($value)) {
            'passed' => self::PASSED,
            'failed_structure' => self::FAILED_STRUCTURE,
            'failed_facts' => self::FAILED_FACTS,
            'duplicate' => self::DUPLICATE,
            default => self::PENDING,
        };
    }
}
