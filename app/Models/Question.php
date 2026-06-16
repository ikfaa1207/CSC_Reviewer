<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['exam_category_id', 'question_text', 'explanation', 'audit_status', 'audit_error'];

    public function category()
    {
        return $this->belongsTo(ExamCategory::class, 'exam_category_id');
    }

    public function options()
    {
        return $this->hasMany(QuestionOption::class);
    }
}
