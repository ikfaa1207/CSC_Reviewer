<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'exam_category_id',
        'question_text',
        'explanation',
        'audit_status',
        'audit_error',
        'problem_type_tag',
        'question_hash',
    ];

    /**
     * Normalize question text for duplicate detection.
     * Strips Variation ID tag, removes punctuation, collapses whitespace, lowercases.
     */
    public static function normalize(string $text): string
    {
        // Strip "(Variation ID: N)" tags
        $text = preg_replace('/\s*\(Variation ID:\s*\d+\)/i', '', $text);
        // Remove punctuation (keep alphanumeric and spaces)
        $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
        // Collapse multiple whitespace into single space and trim
        $text = trim(preg_replace('/\s+/', ' ', $text));
        // Lowercase
        return strtolower($text);
    }

    /**
     * Compute a 32-char MD5 hash from normalized question text.
     */
    public static function computeHash(string $text): string
    {
        return md5(self::normalize($text));
    }

    /**
     * Auto-compute question_hash on create and update.
     */
    protected static function boot(): void
    {
        parent::boot();

        $setHash = function ($model) {
            if (!empty($model->question_text)) {
                $model->question_hash = self::computeHash($model->question_text);
            }
        };

        static::creating($setHash);
        static::updating($setHash);
    }

    public function category()
    {
        return $this->belongsTo(ExamCategory::class, 'exam_category_id');
    }

    public function options()
    {
        return $this->hasMany(QuestionOption::class);
    }
}
