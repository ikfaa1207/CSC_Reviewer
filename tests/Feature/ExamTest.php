<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use App\Models\User;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;

class ExamTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_take_and_submit_exam(): void
    {
        // 1. Setup User and Exam content
        $user = User::factory()->create();

        $category = ExamCategory::create([
            'name' => 'Numerical Ability',
            'description' => 'Math problems',
            'level' => 'both',
        ]);

        $question = Question::create([
            'exam_category_id' => $category->id,
            'question_text' => 'What is 2 + 2?',
            'explanation' => '2 + 2 equals 4.',
        ]);

        $correctOption = QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => '4',
            'is_correct' => true,
        ]);

        $wrongOption = QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => '5',
            'is_correct' => false,
        ]);

        // 2. Start the Exam Attempt
        $response = $this->actingAs($user)->post(route('exams.start'), [
            'level' => 'professional',
            'category_id' => $category->id,
            'mode' => 'timed',
        ]);

        $attempt = ExamAttempt::first();
        $this->assertNotNull($attempt);
        $this->assertEquals('in_progress', $attempt->status);
        $this->assertEquals(1, $attempt->total_questions);
        
        $response->assertRedirect(route('exams.show', $attempt->id));

        // 3. Save an Answer
        $answer = ExamAttemptAnswer::first();
        $this->assertNotNull($answer);
        
        $saveResponse = $this->actingAs($user)->post(route('exams.saveAnswer', $answer->id), [
            'selected_option_id' => $correctOption->id,
            'is_flagged' => false,
        ]);

        $saveResponse->assertStatus(200);
        $answer->refresh();
        $this->assertEquals($correctOption->id, $answer->selected_option_id);
        $this->assertTrue($answer->is_correct);

        // 4. Submit the Exam
        $submitResponse = $this->actingAs($user)->post(route('exams.submit', $attempt->id));
        
        $attempt->refresh();
        $this->assertEquals('completed', $attempt->status);
        $this->assertEquals(1, $attempt->score);
        
        $submitResponse->assertRedirect(route('exams.results', $attempt->id));

        // 5. Access Results
        $resultsResponse = $this->actingAs($user)->get(route('exams.results', $attempt->id));
        $resultsResponse->assertStatus(200);
    }

    public function test_admin_can_generate_question_via_ai(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $category = ExamCategory::create([
            'name' => 'General Information',
            'description' => 'Constitution questions',
            'level' => 'both',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.questions.generateAI'), [
            'exam_category_id' => $category->id,
            'level' => 'professional',
            'count' => 1,
        ]);

        $response->assertRedirect(route('admin.questions.index'));
        
        // Assert question is created
        $this->assertEquals(1, Question::count());
        $this->assertEquals(4, QuestionOption::count());
        
        $question = Question::first();
        $this->assertEquals($category->id, $question->exam_category_id);
        $this->assertEquals(1, QuestionOption::where('is_correct', true)->count());
    }

    public function test_admin_can_bulk_generate_questions_via_ai(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $category = ExamCategory::create([
            'name' => 'Numerical Ability',
            'description' => 'Math problems',
            'level' => 'both',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.questions.generateAI'), [
            'exam_category_id' => $category->id,
            'level' => 'professional',
            'count' => 5,
        ]);

        $response->assertRedirect(route('admin.questions.index'));
        
        // Assert 5 questions are created, with exactly 4 options each
        $this->assertEquals(5, Question::count());
        $this->assertEquals(20, QuestionOption::count());
        $this->assertEquals(5, QuestionOption::where('is_correct', true)->count());
    }

    public function test_non_admin_cannot_generate_question_via_ai(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        
        $category = ExamCategory::create([
            'name' => 'General Information',
            'level' => 'both',
        ]);

        $response = $this->actingAs($user)->post(route('admin.questions.generateAI'), [
            'exam_category_id' => $category->id,
            'level' => 'professional',
            'count' => 1,
        ]);

        $response->assertStatus(403);
        $this->assertEquals(0, Question::count());
    }
}
