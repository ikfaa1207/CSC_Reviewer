<?php

namespace App\Services\AI\Generators;

use App\DTOs\QuestionData;
use App\Models\Question;
use App\Models\Setting;
use App\Services\AI\Contracts\QuestionGeneratorInterface;
use App\Services\AI\QuestionNormalizer;
use App\Services\AI\QuestionSyllabus;
use Illuminate\Support\Facades\Log;

/**
 * Base class for question generators.
 * Provides common functionality for all generators.
 */
abstract class BaseQuestionGenerator implements QuestionGeneratorInterface
{
    protected string $providerName;

    /**
     * Get the API key for this generator.
     */
    abstract protected function getApiKey(): ?string;

    /**
     * Generate questions using the specific provider's API.
     */
    abstract protected function generateFromApi(
        string $categoryName,
        string $level,
        int $batchCount,
        string $apiKey,
        array $excludeTexts
    ): ?array;

    /**
     * Generate mock questions as a fallback.
     */
    abstract protected function generateMock(
        string $categoryName,
        string $level,
        int $count,
        int $seedOffset
    ): array;

    /**
     * {@inheritdoc}
     */
    public function generate(
        string $categoryName,
        string $level,
        int $count,
        int $seedOffset = 0,
        array $excludeTexts = []
    ): array {
        $apiKey = $this->getApiKey();

        if (empty($apiKey)) {
            Log::info("{$this->providerName} API key not configured, using mock generator");
            return $this->generateMock($categoryName, $level, $count, $seedOffset);
        }

        try {
            // Chunk count into batches of max 10 to prevent exceeding token output limits
            $batches = [];
            $tempCount = $count;
            while ($tempCount > 0) {
                $batchSize = min($tempCount, 10);
                $batches[] = $batchSize;
                $tempCount -= $batchSize;
            }

            $allQuestions = [];
            foreach ($batches as $batchSize) {
                // Get existing questions for this category to prevent duplication
                $inProgressTexts = array_map(function ($q) {
                    return strtolower(trim(QuestionNormalizer::removeVariationId($q['question_text'])));
                }, $allQuestions);

                $cleanedExtra = array_map(function ($text) {
                    return strtolower(trim(QuestionNormalizer::removeVariationId($text)));
                }, $excludeTexts);

                $mergedExclude = array_unique(array_merge($inProgressTexts, $cleanedExtra));

                $response = $this->generateFromApi(
                    $categoryName,
                    $level,
                    $batchSize,
                    $apiKey,
                    $mergedExclude
                );

                if ($response && is_array($response)) {
                    $allQuestions = array_merge($allQuestions, $response);
                } else {
                    // If any batch fails, throw exception to trigger mock fallback for the rest
                    throw new \Exception("Batch generation returned empty or invalid response.");
                }
            }

            if (count($allQuestions) > 0) {
                // Return exactly the requested count in case the model generated slightly more/less
                Setting::set('ai_quota_exceeded_flag', '0');
                Setting::set('ai_last_error', null);
                return array_slice($allQuestions, 0, $count);
            }
        } catch (\Exception $e) {
            Log::error("{$this->providerName} API bulk call failed, falling back to mock: " . $e->getMessage());
            Setting::set('ai_last_error', $e->getMessage());
        }

        // Fallback to mock generation
        return $this->generateMock($categoryName, $level, $count, $seedOffset);
    }

    /**
     * Get existing questions from the database for duplicate prevention.
     */
    protected function getExistingQuestions(string $categoryName, int $limit = 40): array
    {
        try {
            return Question::whereHas('category', function ($query) use ($categoryName) {
                $query->where('name', $categoryName);
            })
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get(['question_text', 'problem_type_tag'])
                ->map(function ($q) {
                    $stripped = strtolower(trim(QuestionNormalizer::removeVariationId($q->question_text)));
                    $tag = $q->problem_type_tag ? ' (concept: ' . trim($q->problem_type_tag) . ')' : '';
                    return $stripped . $tag;
                })
                ->unique()
                ->filter()
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to fetch existing questions for AI exclude list: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Normalize questions array to ensure consistent structure.
     */
    protected function normalizeQuestionsArray(array $questions): array
    {
        $normalized = [];
        foreach ($questions as $q) {
            if (is_array($q)) {
                $normalized[] = [
                    'question_text' => $q['question_text'] ?? '',
                    'options' => $q['options'] ?? [],
                    'correct_option_index' => $q['correct_option_index'] ?? 0,
                    'explanation' => $q['explanation'] ?? '',
                    'problem_type_tag' => $q['problem_type_tag'] ?? 'unknown',
                ];
            }
        }
        return $normalized;
    }

    /**
     * Get the syllabus guideline for a category and level.
     */
    protected function getSyllabusGuideline(string $categoryName, string $level): string
    {
        return QuestionSyllabus::getGuideline($categoryName, $level);
    }

    /**
     * Get the formatted level name.
     */
    protected function getFormattedLevel(string $level): string
    {
        return QuestionSyllabus::getFormattedLevel($level);
    }
}
