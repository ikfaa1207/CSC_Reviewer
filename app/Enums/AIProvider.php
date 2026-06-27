<?php

namespace App\Enums;

enum AIProvider: string
{
    case GEMINI = 'gemini';
    case GROQ = 'groq';
    case MOCK = 'mock';

    /**
     * Get the display name for the provider.
     */
    public function label(): string
    {
        return match($this) {
            self::GEMINI => 'Google Gemini',
            self::GROQ => 'Groq',
            self::MOCK => 'Mock (Fallback)',
        };
    }

    /**
     * Get the default model for this provider.
     */
    public function defaultModel(): string
    {
        return match($this) {
            self::GEMINI => 'gemini-2.5-flash',
            self::GROQ => 'llama-3.3-70b-versatile',
            self::MOCK => 'mock',
        };
    }

    /**
     * Get the API endpoint for this provider.
     */
    public function apiEndpoint(): string
    {
        return match($this) {
            self::GEMINI => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
            self::GROQ => 'https://api.groq.com/openai/v1/chat/completions',
            self::MOCK => '',
        };
    }

    /**
     * Get all providers as an array for dropdowns.
     */
    public static function options(): array
    {
        return [
            self::GEMINI->value => self::GEMINI->label(),
            self::GROQ->value => self::GROQ->label(),
            self::MOCK->value => self::MOCK->label(),
        ];
    }

    /**
     * Create from a string value.
     */
    public static function fromString(string $value): self
    {
        return match(strtolower($value)) {
            'groq' => self::GROQ,
            'mock' => self::MOCK,
            default => self::GEMINI,
        };
    }
}
