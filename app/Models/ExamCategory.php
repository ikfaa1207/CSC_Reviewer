<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamCategory extends Model
{
    protected $fillable = ['name', 'description', 'level'];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
