<?php

namespace App\Providers;

use App\Enums\AIProvider;
use App\Services\AI\AIService;
use App\Services\AI\Contracts\AIServiceInterface;
use App\Services\AI\Contracts\QuestionGeneratorInterface;
use App\Services\AI\Contracts\QuestionVerifierInterface;
use App\Services\AI\Generators\GeminiQuestionGenerator;
use App\Services\AI\Generators\GroqQuestionGenerator;
use App\Services\AI\Generators\MockQuestionGenerator;
use App\Services\AI\Verifiers\GeminiQuestionVerifier;
use App\Services\AI\Verifiers\GroqQuestionVerifier;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider for AI Services.
 * Registers AI-related services for dependency injection.
 */
class AIServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the main AI service as a singleton
        $this->app->singleton(AIServiceInterface::class, function ($app) {
            $provider = config('services.ai.provider', 'gemini');
            return new AIService();
        });

        // Register the AIService as a concrete binding for backward compatibility
        $this->app->singleton(AIService::class, function ($app) {
            return new AIService();
        });

        // Register the question generator based on configured provider
        $this->app->bind(QuestionGeneratorInterface::class, function ($app) {
            $provider = AIProvider::fromString(config('services.ai.provider', 'gemini'));
            
            return match ($provider) {
                AIProvider::GEMINI => new GeminiQuestionGenerator(),
                AIProvider::GROQ => new GroqQuestionGenerator(),
                AIProvider::MOCK => new MockQuestionGenerator(),
            };
        });

        // Register the question verifier based on configured provider
        $this->app->bind(QuestionVerifierInterface::class, function ($app) {
            $provider = AIProvider::fromString(config('services.ai.provider', 'gemini'));
            
            return match ($provider) {
                AIProvider::GEMINI => new GeminiQuestionVerifier(),
                AIProvider::GROQ => new GroqQuestionVerifier(),
                AIProvider::MOCK => new class extends \App\Services\AI\Verifiers\BaseQuestionVerifier {
                    protected string $providerName = 'Mock';
                    
                    protected function getApiKey(): ?string {
                        return null;
                    }
                    
                    protected function verifyFromApi(array $questions, array $dbCandidates, string $apiKey): ?array {
                        return null; // Always use fallback
                    }
                },
            };
        });

        // Register specific generators for direct use
        $this->app->bind(GeminiQuestionGenerator::class, function ($app) {
            return new GeminiQuestionGenerator();
        });

        $this->app->bind(GroqQuestionGenerator::class, function ($app) {
            return new GroqQuestionGenerator();
        });

        $this->app->bind(MockQuestionGenerator::class, function ($app) {
            return new MockQuestionGenerator();
        });

        // Register specific verifiers for direct use
        $this->app->bind(GeminiQuestionVerifier::class, function ($app) {
            return new GeminiQuestionVerifier();
        });

        $this->app->bind(GroqQuestionVerifier::class, function ($app) {
            return new GroqQuestionVerifier();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // You can publish configuration files here if needed
        // $this->publishes([
        //     __DIR__.'/../../config/ai.php' => config_path('ai.php'),
        // ], 'config');
    }
}
