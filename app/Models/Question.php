<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['exam_category_id', 'question_text', 'explanation'];

    public function category()
    {
        return $this->belongsTo(ExamCategory::class, 'exam_category_id');
    }

    public function options()
    {
        return $this->hasMany(QuestionOption::class);
    }
}
