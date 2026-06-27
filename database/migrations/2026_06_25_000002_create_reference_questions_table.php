<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reference_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_category_id')->constrained('exam_categories')->onDelete('cascade');
            $table->string('level');
            $table->string('subtopic_tag')->index();
            $table->text('question_text');
            $table->json('options');
            $table->integer('correct_option_index');
            $table->text('explanation')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reference_questions');
    }
};
