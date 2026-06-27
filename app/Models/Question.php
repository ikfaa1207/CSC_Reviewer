<?php

namespace App\Models;

use App\Services\AI\QuestionNormalizer;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'exam_category_id',
        'reference_question_id',
        'question_text',
        'explanation',
        'audit_status',
        'audit_error',
        'problem_type_tag',
        'question_hash',
    ];

    /**
     * Normalize question text for duplicate detection.
     * Delegates to QuestionNormalizer service.
     */
    public static function normalize(string $text): string
    {
        return QuestionNormalizer::normalize($text);
    }

    /**
     * Compute a 32-char MD5 hash from normalized question text.
     * Delegates to QuestionNormalizer service.
     */
    public static function computeHash(string $text): string
    {
        return QuestionNormalizer::computeHash($text);
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

    public function referenceQuestion()
    {
        return $this->belongsTo(ReferenceQuestion::class, 'reference_question_id');
    }

    public function options()
    {
        return $this->hasMany(QuestionOption::class);
    }
}
