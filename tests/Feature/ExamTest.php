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
        $this->assertEquals('general-info-constitution-rights', $question->problem_type_tag);
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

    public function test_admin_can_generate_sub_professional_clerical_questions_via_ai(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $category = ExamCategory::create([
            'name' => 'Clerical Ability',
            'description' => 'Filing and coding',
            'level' => 'sub_professional',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.questions.generateAI'), [
            'exam_category_id' => $category->id,
            'level' => 'sub_professional',
            'count' => 5,
        ]);

        $response->assertRedirect(route('admin.questions.index'));

        // Assert 5 questions are created
        $this->assertEquals(5, Question::count());
        $this->assertEquals(20, QuestionOption::count());
        $this->assertEquals(5, QuestionOption::where('is_correct', true)->count());

        // Assert that the questions belong to the Clerical Ability category
        $questions = Question::all();
        foreach ($questions as $q) {
            $this->assertEquals($category->id, $q->exam_category_id);
            // Verify mock question text contains either 'alphabetical filing' or 'clerical code'
            $this->assertTrue(
                str_contains($q->question_text, 'alphabetical filing') ||
                str_contains($q->question_text, 'clerical code')
            );
        }
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

    public function test_admin_can_clean_duplicate_questions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $category = ExamCategory::create([
            'name' => 'General Information',
            'level' => 'both',
        ]);

        // Create duplicate questions
        $q1 = Question::create([
            'exam_category_id' => $category->id,
            'question_text' => 'Duplicate Question Text (Variation ID: 1)',
        ]);
        QuestionOption::create([
            'question_id' => $q1->id,
            'option_text' => 'Option 1',
            'is_correct' => true,
        ]);

        $q2 = Question::create([
            'exam_category_id' => $category->id,
            'question_text' => 'duplicate question text (Variation ID: 2) ', // Case difference, trailing space, and different variation ID
        ]);
        QuestionOption::create([
            'question_id' => $q2->id,
            'option_text' => 'Option 1',
            'is_correct' => true,
        ]);

        $this->assertEquals(2, Question::count());

        $response = $this->actingAs($admin)->post(route('admin.questions.cleanDuplicates'));

        $response->assertRedirect(route('admin.questions.index'));
        
        // Assert only 1 question remains (the oldest one: $q1)
        $this->assertEquals(1, Question::count());
        $this->assertEquals($q1->id, Question::first()->id);
    }

    public function test_ai_question_generator_skips_duplicates(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $category = ExamCategory::create([
            'name' => 'General Information',
            'level' => 'both',
        ]);

        // Pre-create the RA 6713 question in the database matching mock seed 1 format
        $questionText = "What Republic Act is otherwise known as the 'Code of Conduct and Ethical Standards for Public Officials and Employees'? (Variation ID: 2)";
        
        Question::create([
            'exam_category_id' => $category->id,
            'question_text' => $questionText,
        ]);

        $this->assertEquals(1, Question::count());

        // Ask generator to generate 5 questions (which is a validated valid count)
        // Since the generator templates repeat and RA 6713 already exists, duplicate ones will be skipped
        $response = $this->actingAs($admin)->post(route('admin.questions.generateAI'), [
            'exam_category_id' => $category->id,
            'level' => 'professional',
            'count' => 5,
        ]);

        $response->assertRedirect(route('admin.questions.index'));
        
        // Total should be 6 questions (1 pre-existing + 5 unique newly generated ones, because the duplicate was skipped and regenerated).
        $this->assertEquals(6, Question::count());
    }
    public function test_admin_can_bulk_delete_questions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $category = ExamCategory::create([
            'name' => 'General Information',
            'level' => 'both',
        ]);

        $q1 = Question::create([
            'exam_category_id' => $category->id,
            'question_text' => 'Question 1',
        ]);
        $q2 = Question::create([
            'exam_category_id' => $category->id,
            'question_text' => 'Question 2',
        ]);
        $q3 = Question::create([
            'exam_category_id' => $category->id,
            'question_text' => 'Question 3',
        ]);

        $this->assertEquals(3, Question::count());

        $response = $this->actingAs($admin)->delete(route('admin.questions.bulkDestroy'), [
            'ids' => [$q1->id, $q2->id],
        ]);

        $response->assertRedirect(route('admin.questions.index'));
        
        // Assert selected questions are deleted, and others remain
        $this->assertEquals(1, Question::count());
        $this->assertEquals($q3->id, Question::first()->id);
    }

    public function test_non_admin_cannot_bulk_delete_questions(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $category = ExamCategory::create([
            'name' => 'General Information',
            'level' => 'both',
        ]);

        $q1 = Question::create([
            'exam_category_id' => $category->id,
            'question_text' => 'Question 1',
        ]);

        $response = $this->actingAs($user)->delete(route('admin.questions.bulkDestroy'), [
            'ids' => [$q1->id],
        ]);

        $response->assertStatus(403);
        $this->assertEquals(1, Question::count());
    }

    public function test_admin_can_run_integrity_audit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $category = ExamCategory::create([
            'name' => 'General Information',
            'level' => 'both',
        ]);

        // 1. Question with too few options (3 instead of 4)
        $q1 = Question::create([
            'exam_category_id' => $category->id,
            'question_text' => 'Structural Mismatch: Too few options',
        ]);
        QuestionOption::create(['question_id' => $q1->id, 'option_text' => 'Opt 1', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $q1->id, 'option_text' => 'Opt 2', 'is_correct' => false]);
        QuestionOption::create(['question_id' => $q1->id, 'option_text' => 'Opt 3', 'is_correct' => false]);

        // 2. Question with multiple correct options (2 correct)
        $q2 = Question::create([
            'exam_category_id' => $category->id,
            'question_text' => 'Structural Mismatch: Multiple correct options',
        ]);
        QuestionOption::create(['question_id' => $q2->id, 'option_text' => 'Opt 1', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $q2->id, 'option_text' => 'Opt 2', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $q2->id, 'option_text' => 'Opt 3', 'is_correct' => false]);
        QuestionOption::create(['question_id' => $q2->id, 'option_text' => 'Opt 4', 'is_correct' => false]);

        // 3. Question with no correct option
        $q3 = Question::create([
            'exam_category_id' => $category->id,
            'question_text' => 'Structural Mismatch: No correct options',
        ]);
        QuestionOption::create(['question_id' => $q3->id, 'option_text' => 'Opt 1', 'is_correct' => false]);
        QuestionOption::create(['question_id' => $q3->id, 'option_text' => 'Opt 2', 'is_correct' => false]);
        QuestionOption::create(['question_id' => $q3->id, 'option_text' => 'Opt 3', 'is_correct' => false]);
        QuestionOption::create(['question_id' => $q3->id, 'option_text' => 'Opt 4', 'is_correct' => false]);

        // 4. Valid question (passes structural)
        $q4 = Question::create([
            'exam_category_id' => $category->id,
            'question_text' => 'Valid structure',
        ]);
        QuestionOption::create(['question_id' => $q4->id, 'option_text' => 'Opt 1', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $q4->id, 'option_text' => 'Opt 2', 'is_correct' => false]);
        QuestionOption::create(['question_id' => $q4->id, 'option_text' => 'Opt 3', 'is_correct' => false]);
        QuestionOption::create(['question_id' => $q4->id, 'option_text' => 'Opt 4', 'is_correct' => false]);

        $response = $this->actingAs($admin)->post(route('admin.questions.runAudit'));

        $response->assertRedirect(route('admin.questions.index'));

        // Refresh and assert audit values
        $q1->refresh();
        $this->assertEquals('failed_structure', $q1->audit_status);
        $this->assertStringContainsString('3 options', $q1->audit_error);

        $q2->refresh();
        $this->assertEquals('failed_structure', $q2->audit_status);
        $this->assertStringContainsString('Multiple correct options', $q2->audit_error);

        $q3->refresh();
        $this->assertEquals('failed_structure', $q3->audit_status);
        $this->assertStringContainsString('No correct option marked', $q3->audit_error);

        $q4->refresh();
        $this->assertEquals('passed', $q4->audit_status);
        $this->assertNull($q4->audit_error);
    }

    public function test_admin_can_reset_ai_usage(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Pre-set some setting values
        \App\Models\Setting::set('ai_generated_count', 123);
        \App\Models\Setting::set('ai_quota_exceeded_flag', 1);
        \App\Models\Setting::set('ai_last_error', 'Some mock API error');

        $this->assertEquals(123, \App\Models\Setting::get('ai_generated_count'));
        $this->assertEquals(1, \App\Models\Setting::get('ai_quota_exceeded_flag'));

        $response = $this->actingAs($admin)->post(route('admin.questions.resetAIUsage'));

        $response->assertRedirect(route('admin.questions.index'));

        $this->assertEquals(0, \App\Models\Setting::get('ai_generated_count'));
        $this->assertEquals(0, \App\Models\Setting::get('ai_quota_exceeded_flag'));
        $this->assertNull(\App\Models\Setting::get('ai_last_error'));
    }

    public function test_exam_start_limit_rules(): void
    {
        $user = User::factory()->create();

        $category = ExamCategory::create([
            'name' => 'General Information',
            'level' => 'both',
        ]);

        // Create 160 questions in the category
        $questionsData = [];
        for ($i = 1; $i <= 160; $i++) {
            $questionsData[] = [
                'exam_category_id' => $category->id,
                'question_text' => "Question Number $i",
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Question::insert($questionsData);

        // 1. Verify specific category exam starts with exactly 20 questions
        $response = $this->actingAs($user)->post(route('exams.start'), [
            'level' => 'professional',
            'category_id' => $category->id,
            'mode' => 'timed',
        ]);

        $attempt1 = ExamAttempt::orderBy('id', 'desc')->first();
        $this->assertNotNull($attempt1);
        $this->assertEquals(20, $attempt1->total_questions);
        $this->assertEquals('timed', $attempt1->mode);

        // 2. Verify Full Mock Exam (category_id = null) pulls up to 150 questions
        $response2 = $this->actingAs($user)->post(route('exams.start'), [
            'level' => 'professional',
            'category_id' => null,
            'mode' => 'review',
        ]);

        $attempt2 = ExamAttempt::orderBy('id', 'desc')->first();
        $this->assertNotNull($attempt2);
        $this->assertNotEquals($attempt1->id, $attempt2->id);
        $this->assertEquals(150, $attempt2->total_questions);
        $this->assertEquals('review', $attempt2->mode);
    }

    public function test_ai_generation_self_correcting_retry_loop_with_http_fake(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $category = ExamCategory::create([
            'name' => 'General Information',
            'level' => 'both',
        ]);

        config(['services.ai.key' => 'fake-test-api-key']);

        \Illuminate\Support\Facades\Http::fake([
            'https://generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::sequence()
                // Attempt 1: Generate 1 question
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    [
                                        'question_text' => 'Duplicate Q',
                                        'options' => ['A', 'B', 'C', 'D'],
                                        'correct_option_index' => 0,
                                        'explanation' => 'Exp',
                                        'problem_type_tag' => 'test-tag',
                                    ]
                                ])
                            ]]
                        ]
                    ]]
                ], 200)
                // Attempt 1: Verify 1 question (invalid/duplicate)
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    [
                                        'question_text' => 'Duplicate Q',
                                        'options' => ['A', 'B', 'C', 'D'],
                                        'correct_option_index' => 0,
                                        'explanation' => 'Exp',
                                        'problem_type_tag' => 'test-tag',
                                        'is_valid' => false,
                                        'error_reason' => 'Semantic duplicate'
                                    ]
                                ])
                            ]]
                        ]
                    ]]
                ], 200)
                // Attempt 2: Generate 1 question
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    [
                                        'question_text' => 'Valid Q1',
                                        'options' => ['A', 'B', 'C', 'D'],
                                        'correct_option_index' => 1,
                                        'explanation' => 'Exp',
                                        'problem_type_tag' => 'test-tag',
                                    ]
                                ])
                            ]]
                        ]
                    ]]
                ], 200)
                // Attempt 2: Verify 1 question (valid)
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    [
                                        'question_text' => 'Valid Q1',
                                        'options' => ['A', 'B', 'C', 'D'],
                                        'correct_option_index' => 1,
                                        'explanation' => 'Exp',
                                        'problem_type_tag' => 'test-tag',
                                        'is_valid' => true,
                                        'error_reason' => null
                                    ]
                                ])
                            ]]
                        ]
                    ]]
                ], 200)
        ]);

        $response = $this->actingAs($admin)->post(route('admin.questions.generateAI'), [
            'exam_category_id' => $category->id,
            'level' => 'professional',
            'count' => 1,
        ]);

        $response->assertRedirect(route('admin.questions.index'));

        // Assert 1 question was successfully saved (Valid Q1)
        $this->assertEquals(1, Question::count());
        $this->assertEquals(4, QuestionOption::count());

        $savedQuestion = Question::first();
        $this->assertEquals('Valid Q1', $savedQuestion->question_text);

        // Verify that 'Duplicate Q' was skipped
        $this->assertFalse(Question::where('question_text', 'Duplicate Q')->exists());
    }

    public function test_admin_can_generate_all_categories_combined_via_ai(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        // Seed the 4 active categories for professional level
        $cat1 = ExamCategory::create(['name' => 'Numerical Ability', 'level' => 'both']);
        $cat2 = ExamCategory::create(['name' => 'Verbal Ability', 'level' => 'both']);
        $cat3 = ExamCategory::create(['name' => 'Analytical Ability', 'level' => 'professional']);
        $cat4 = ExamCategory::create(['name' => 'General Information', 'level' => 'both']);

        // We request a total of 5 questions
        // Distributing 5 across 4 categories:
        // Cat 1 (Numerical): 2 questions
        // Cat 2 (Verbal): 1 question
        // Cat 3 (Analytical): 1 question
        // Cat 4 (General Info): 1 question
        // Falls back to mock questions since no API key is present in test env
        $response = $this->actingAs($admin)->post(route('admin.questions.generateAI'), [
            'exam_category_id' => 'all',
            'level' => 'professional',
            'count' => 5,
        ]);

        $response->assertRedirect(route('admin.questions.index'));

        // Assert 5 questions are created in total
        $this->assertEquals(5, Question::count());

        // Assert that the questions are distributed correctly across categories
        $this->assertEquals(2, Question::where('exam_category_id', $cat1->id)->count());
        $this->assertEquals(1, Question::where('exam_category_id', $cat2->id)->count());
        $this->assertEquals(1, Question::where('exam_category_id', $cat3->id)->count());
        $this->assertEquals(1, Question::where('exam_category_id', $cat4->id)->count());
    }
}
