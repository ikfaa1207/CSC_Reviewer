<?php

namespace App\Services\AI\Generators;

use App\DTOs\QuestionData;
use App\Models\Question;
use App\Models\Setting;
use App\Services\AI\QuestionNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Groq API question generator.
 * Generates high-quality CSE questions using Groq's fast LLMs (e.g., Llama 3.3).
 */
class GroqQuestionGenerator extends BaseQuestionGenerator
{
    protected string $providerName = 'Groq';
    
    private string $model;
    private string $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';

    /**
     * Create a new Groq generator instance.
     */
    public function __construct(string $model = null)
    {
        $this->model = $model ?? config('services.ai.model', 'llama-3.3-70b-versatile');
    }

    /**
     * {@inheritdoc}
     */
    protected function getApiKey(): ?string
    {
        return config('services.ai.key');
    }

    /**
     * {@inheritdoc}
     */
    protected function generateFromApi(
        string $categoryName,
        string $level,
        int $batchCount,
        string $apiKey,
        array $excludeTexts
    ): ?array {
        try {
            $prompt = $this->buildGenerationPrompt($categoryName, $level, $batchCount, $excludeTexts);
            
            $body = [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'response_format' => [
                    'type' => 'json_object'
                ],
                'temperature' => 0.7,
                'max_tokens' => 4096,
            ];

            $response = Http::withoutVerifying()
                ->timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey
                ])
                ->post($this->apiUrl, $body);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['choices'][0]['message']['content'] ?? null;
                
                if ($text) {
                    $decoded = json_decode($text, true);
                    
                    if (is_array($decoded)) {
                        Setting::set('ai_quota_exceeded_flag', '0');
                        Setting::set('ai_last_error', null);
                        
                        // Extract questions from various possible response formats
                        $questions = $this->extractQuestionsFromResponse($decoded);
                        
                        // Validate and normalize the response
                        return $this->normalizeAndValidateQuestions($questions);
                    }
                }
            }

            // Handle errors
            if ($response->status() === 429) {
                Setting::set('ai_quota_exceeded_flag', '1');
                Setting::set('ai_last_error', '429 Quota Exceeded on Groq API.');
            } else if ($response->failed()) {
                Setting::set('ai_last_error', 'Groq API call failed with status ' . $response->status() . ': ' . $response->body());
            }

            Log::warning('Groq API response format was invalid: ' . ($response->body() ?? 'Empty response'));
            return null;
            
        } catch (\Exception $e) {
            Log::error('Groq API generation failed: ' . $e->getMessage());
            Setting::set('ai_last_error', 'Groq API generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function generateMock(
        string $categoryName,
        string $level,
        int $count,
        int $seedOffset
    ): array {
        // Use the mock generator for fallback
        $mockGenerator = new MockQuestionGenerator();
        return $mockGenerator->generate($categoryName, $level, $count, $seedOffset);
    }

    /**
     * Extract questions from various response formats.
     */
    private function extractQuestionsFromResponse(array $decoded): array
    {
        // Check for direct array of questions
        if (isset($decoded['questions']) && is_array($decoded['questions'])) {
            return $decoded['questions'];
        }
        
        // Check for 'data' key
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }
        
        // Check if it's a numeric array
        if (array_keys($decoded) === range(0, count($decoded) - 1)) {
            return $decoded;
        }
        
        // Check for nested structure
        if (isset($decoded['result']) && is_array($decoded['result'])) {
            return $decoded['result'];
        }
        
        // If it's a single question object, wrap it in an array
        if (isset($decoded['question_text'])) {
            return [$decoded];
        }
        
        // Last resort: return as-is (might be already correct)
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build the generation prompt for Groq.
     */
    private function buildGenerationPrompt(
        string $categoryName,
        string $level,
        int $batchCount,
        array $excludeTexts
    ): string {
        $syllabusGuideline = $this->getSyllabusGuideline($categoryName, $level);
        $formattedLevel = $this->getFormattedLevel($level);
        
        // Get existing questions for duplicate prevention
        $existingQuestions = $this->getExistingQuestions($categoryName, 40);
        $allExcludeTexts = array_unique(array_merge($existingQuestions, $excludeTexts));

        $excludePrompt = '';
        if (!empty($allExcludeTexts)) {
            $excludePrompt = "\n\nCRITICAL REDUNDANCY PREVENTION:\n" .
                           "To avoid generating redundant or duplicate questions, DO NOT generate any questions that are identical or highly similar to the following list:\n";
            foreach ($allExcludeTexts as $eq) {
                $excludePrompt .= "- " . trim($eq) . "\n";
            }
        }

        // Category-specific formatting guidelines
        $formattingGuidelines = $this->getFormattingGuidelines($categoryName, $level);

        $prompt = "You are an expert question generator for the Philippine Civil Service Exam (CSE).\n\n" .
                  "Generate exactly {$batchCount} unique multiple-choice questions for a CSE self-assessment tool.\n" .
                  "Category: {$categoryName}\n" .
                  "Level: {$formattedLevel}\n\n" .
                  "Syllabus & Topic Guidelines:\n" .
                  "{$syllabusGuideline}\n" .
                  $excludePrompt . "\n" .
                  "{$formattingGuidelines}\n\n" .
                  "CRITICAL LOGIC RULE: PREVENT SEMANTIC DUPLICATES. A semantic duplicate is a question that uses the exact same mathematical formula, logic pattern, or scenario type as a previous question, even if you change the names, places, or exact numbers. Every question you generate must test a completely different mathematical concept, logical problem type, or core subject matter.\n\n" .
                  "Requirements:\n" .
                  "1. The questions must test knowledge/skills relevant to the category and difficulty level of the Philippine Civil Service Exam.\n" .
                  "2. Define a specific 'problem_type_tag' (e.g. 'work-rate-pipes', 'percentage-discount', 'syllogism-all-some', 'sequence-geometric', 'vocabulary-antonym-sentence') that describes the exact logic/concept used.\n" .
                  "3. Each question must have exactly 4 multiple choice options.\n" .
                  "4. Define exactly one correct option index (0-indexed integer from 0 to 3) for each question.\n" .
                  "5. Provide a clear, step-by-step explanatory review detailing why that answer is correct.\n" .
                  "6. Return the response as a JSON object containing a 'questions' key which is an array of objects matching the required schema.\n" .
                  "7. CRITICAL: DO NOT prefix options in the 'options' array with letter headers (like 'A.', 'a.', 'B.', 'b.', '1.', etc.). Options must be pure, clean strings.";

        return $prompt;
    }

    /**
     * Get category-specific formatting guidelines for Groq.
     */
    private function getFormattingGuidelines(string $categoryName, string $level): string
    {
        $guidelines = [];

        if ($categoryName === 'Numerical Ability') {
            $guidelines[] = "For Numerical Ability (Mathematics):";
            $guidelines[] = "  1. Word Problems & Operations: Write mathematical expressions, percentages, and fractions in plain text format (e.g., use '1/2' or '33 1/3%' instead of special symbols or LaTeX math syntax).";
            $guidelines[] = "  2. Financial & Currency Formatting: Format all monetary/financial values using the Philippine Peso symbol '₱' and commas as thousands separators, with two decimal places (e.g., use '₱1,250.00' instead of '1250' or 'P1250').";
            
            if ($level === 'professional') {
                $guidelines[] = "  3. Data Sufficiency questions: Provide a mathematical question followed by two statements on new lines, labeled 1) and 2). The 4 options must represent sufficiency rules and be exactly:";
                $guidelines[] = "     - Statement (1) ALONE is sufficient, but statement (2) alone is not sufficient.";
                $guidelines[] = "     - Statement (2) ALONE is sufficient, but statement (1) alone is not sufficient.";
                $guidelines[] = "     - BOTH statements TOGETHER are sufficient, but NEITHER statement ALONE is sufficient.";
                $guidelines[] = "     - Statements (1) and (2) TOGETHER are NOT sufficient.";
            }
        }

        if ($categoryName === 'Verbal Ability') {
            $guidelines[] = "For Verbal Ability:";
            $guidelines[] = "  1. Vocabulary (Synonyms & Antonyms): Wrap the target vocabulary word in double quotes (e.g., \"apathetic\" or \"brusque\") inside a complete, natural sentence context.";
            $guidelines[] = "  2. Analogy:";
            $guidelines[] = "     - Single-word Analogy: Phrased as: 'Complete the analogy: Moby Dick : Herman Melville || The Old Man and the Sea : ________'";
            $guidelines[] = "     - Double-word Analogy: Phrased as: 'Identify the pair of words that shares the same relationship as the given pair: blend : mix'";
            $guidelines[] = "  3. Correct Usage: Ask the user to complete a sentence. E.g., 'Choose the word that correctly completes the sentence: ...'";
            $guidelines[] = "  4. Identifying Errors: Ask the user to identify the grammatically incorrect segment. Phrased as: 'Identify the word or phrase that is NOT acceptable in formal written English: ...'. Options should list the segments and 'No error' as the fourth option.";
        }

        if ($categoryName === 'Clerical Ability') {
            $guidelines[] = "For Clerical Ability:";
            $guidelines[] = "  1. Alphabetizing: Provide 4 items (such as names, government departments, or organizations) labeled A, B, C, and D. Phrased as: 'Arrange the following items in alphabetical order: \\nA. [Item A]\\nB. [Item B]\\nC. [Item C]\\nD. [Item D]'. The 4 options must be permutations of the letters A, B, C, D (e.g., 'ABCD', 'ACBD', 'BCAD', 'CBAD').";
            $guidelines[] = "  2. Spelling/Data Verification: Identify correctly spelled words or matching codes/data.";
        }

        if ($categoryName === 'Analytical Ability') {
            $guidelines[] = "For Analytical Ability (Professional level):";
            $guidelines[] = "  1. Logical Reasoning: Provide a set of premises or statements and ask for the logical conclusion or assumption.";
            $guidelines[] = "  2. Sequence/Inductive Reasoning: Ask to find the next item in a sequence of numbers or letters.";
            $guidelines[] = "  3. Abstract Reasoning: Use diagram tags for shape-based questions.";
        }

        return implode("\n", $guidelines);
    }

    /**
     * Normalize and validate questions from API response.
     */
    private function normalizeAndValidateQuestions(array $questions): array
    {
        $normalized = [];
        
        foreach ($questions as $q) {
            try {
                // Skip if not an array
                if (!is_array($q)) {
                    continue;
                }

                // Validate required fields
                if (empty($q['question_text']) || empty($q['options']) || !isset($q['correct_option_index'])) {
                    Log::warning('Invalid question structure from Groq API', ['question' => $q]);
                    continue;
                }

                // Ensure options is an array with exactly 4 items
                if (!is_array($q['options']) || count($q['options']) !== 4) {
                    Log::warning('Invalid options count from Groq API', ['options_count' => count($q['options'] ?? [])]);
                    continue;
                }

                // Validate correct_option_index
                $correctIndex = (int) $q['correct_option_index'];
                if ($correctIndex < 0 || $correctIndex > 3) {
                    Log::warning('Invalid correct_option_index from Groq API', ['index' => $correctIndex]);
                    continue;
                }

                // Clean up question text (remove any markdown formatting)
                $questionText = $this->cleanText($q['question_text'] ?? '');
                $options = array_map([$this, 'cleanText'], $q['options'] ?? []);
                $explanation = $this->cleanText($q['explanation'] ?? '');
                $problemTypeTag = $this->cleanTag($q['problem_type_tag'] ?? 'unknown');

                // Remove variation ID from question text if present
                $questionText = QuestionNormalizer::removeVariationId($questionText);

                $normalized[] = [
                    'question_text' => $questionText,
                    'options' => $options,
                    'correct_option_index' => $correctIndex,
                    'explanation' => $explanation,
                    'problem_type_tag' => $problemTypeTag,
                ];
                
            } catch (\Exception $e) {
                Log::warning('Error normalizing question: ' . $e->getMessage());
                continue;
            }
        }

        return $normalized;
    }

    /**
     * Clean text by removing markdown and special formatting.
     */
    private function cleanText(string $text): string
    {
        // Remove markdown bold/italic
        $text = preg_replace('/[\*_~`]/', '', $text);
        
        // Remove markdown headers
        $text = preg_replace('/^#+\s*/m', '', $text);
        
        // Remove markdown lists
        $text = preg_replace('/^[-*+]\s*/m', '', $text);
        $text = preg_replace('/^\d+\.\s*/m', '', $text);
        
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Clean problem type tag.
     */
    private function cleanTag(string $tag): string
    {
        // Remove special characters
        $tag = preg_replace('/[^a-zA-Z0-9\-_]/', '', $tag);
        
        // Ensure it's lowercase with hyphens
        $tag = strtolower($tag);
        $tag = preg_replace('/[\s_]+/', '-', $tag);
        
        return trim($tag, '-');
    }

    /**
     * Set the model to use.
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Get the current model.
     */
    public function getModel(): string
    {
        return $this->model;
    }
}
