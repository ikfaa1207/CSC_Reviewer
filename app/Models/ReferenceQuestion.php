<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferenceQuestion extends Model
{
    protected $fillable = [
        'exam_category_id',
        'level',
        'subtopic_tag',
        'question_text',
        'options',
        'correct_option_index',
        'explanation',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(ExamCategory::class, 'exam_category_id');
    }

    public function generatedQuestions()
    {
        return $this->hasMany(Question::class, 'reference_question_id');
    }
}
