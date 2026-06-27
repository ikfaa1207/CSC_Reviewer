# AI Service Implementation - CSC Reviewer

## Overview

This document describes the complete implementation of the **Gemini** and **Groq** AI question generators and verifiers for the CSC Reviewer application.

## Implementation Summary

### ✅ Completed Components

#### 1. **Question Generators**
- ✅ `GeminiQuestionGenerator` - Google Gemini API integration
- ✅ `GroqQuestionGenerator` - Groq API integration (Llama 3.3, etc.)
- ✅ `MockQuestionGenerator` - Fallback generator with deterministic output
- ✅ `BaseQuestionGenerator` - Abstract base class with common functionality

#### 2. **Question Verifiers**
- ✅ `GeminiQuestionVerifier` - Google Gemini API verification
- ✅ `GroqQuestionVerifier` - Groq API verification
- ✅ `BaseQuestionVerifier` - Abstract base class with common functionality

#### 3. **Validation & Formatting**
- ✅ `QuestionValidator` - Comprehensive question validation and improvement
- ✅ `QuestionNormalizer` - Text normalization for duplicate detection
- ✅ `QuestionSyllabus` - Centralized syllabus guidelines

#### 4. **Data Transfer Objects**
- ✅ `QuestionData` - Immutable question data with validation
- ✅ `GeneratedQuestionData` - Question with validation metadata

#### 5. **Enums**
- ✅ `AIProvider` - gemini, groq, mock
- ✅ `AuditStatus` - passed, failed_structure, failed_facts, duplicate, pending
- ✅ `ExamLevel` - professional, sub_professional, both

#### 6. **Service Provider**
- ✅ `AIServiceProvider` - Dependency injection configuration

#### 7. **Main Service**
- ✅ `AIService` - Facade that coordinates all components

---

## 🏗️ Architecture

```
app/
├── DTOs/
│   ├── QuestionData.php
│   └── GeneratedQuestionData.php
├── Enums/
│   ├── AIProvider.php
│   ├── AuditStatus.php
│   └── ExamLevel.php
├── Providers/
│   └── AIServiceProvider.php
└── Services/AI/
    ├── AIService.php                    # Main facade
    ├── Contracts/
    │   ├── AIServiceInterface.php
    │   ├── QuestionGeneratorInterface.php
    │   └── QuestionVerifierInterface.php
    ├── Generators/
    │   ├── BaseQuestionGenerator.php
    │   ├── GeminiQuestionGenerator.php  # ✨ NEW
    │   ├── GroqQuestionGenerator.php   # ✨ NEW
    │   └── MockQuestionGenerator.php
    ├── Verifiers/
    │   ├── BaseQuestionVerifier.php
    │   ├── GeminiQuestionVerifier.php   # ✨ NEW
    │   └── GroqQuestionVerifier.php     # ✨ NEW
    ├── QuestionNormalizer.php
    ├── QuestionSyllabus.php
    └── QuestionValidator.php            # ✨ NEW
```

---

## 🎯 Key Features

### 1. **Provider-Specific Implementation**

Each AI provider has its own optimized implementation:

#### **Gemini Generator** (`GeminiQuestionGenerator`)
- Uses Google's **Gemini 2.5 Flash** model
- Implements **JSON Schema** for structured output
- Supports **responseMimeType: application/json**
- Handles **429 Quota Exceeded** errors gracefully
- Validates and normalizes API responses

#### **Groq Generator** (`GroqQuestionGenerator`)
- Uses **Llama 3.3-70b-versatile** or other Groq models
- Implements **OpenAI-compatible API** format
- Supports **response_format: json_object**
- Handles various response formats (questions, data, nested)
- Validates and normalizes API responses

### 2. **Improved Output Quality**

The `QuestionValidator` class provides:

#### **Validation**
```php
QuestionValidator::validateQuestion($question);
// Returns: ['is_valid' => bool, 'errors' => array, 'warnings' => array]
```

#### **Formatting Improvement**
```php
QuestionValidator::improveFormatting($question);
// - Cleans markdown formatting
// - Removes variation ID tags
// - Normalizes whitespace
// - Validates structure
```

#### **Duplicate Detection**
```php
QuestionValidator::checkSemanticDuplicates($questions, $existingQuestions);
// - Checks for exact hash matches
// - Detects semantic similarity (>70% word overlap)
// - Returns detailed duplicate information
```

#### **Batch Processing**
```php
QuestionValidator::validateImproveAndCheckDuplicates($questions, $existingQuestions);
// Returns: ['valid' => [...], 'invalid' => [...], 'duplicates' => [...], 'warnings' => [...]]
```

### 3. **Error Handling & Fallback**

All generators and verifiers implement:
- **Automatic fallback to mock** when API fails
- **Graceful error handling** with logging
- **Quota exceeded detection** (429 status)
- **Response validation** to ensure correct structure
- **Normalization** of API responses

---

## 📖 Usage Examples

### Basic Usage

```php
// Using dependency injection
$aiService = app(AIService::class);

// Generate questions
$questions = $aiService->generateQuestions(
    'Numerical Ability',
    'professional',
    10
);

// Verify questions
$verified = $aiService->verifyQuestions($questions, $dbCandidates);
```

### Using Specific Provider

```php
// Use Gemini directly
$geminiGenerator = new GeminiQuestionGenerator();
$questions = $geminiGenerator->generate(
    'Verbal Ability',
    'sub_professional',
    5,
    0,
    []
);

// Use Groq directly
$groqGenerator = new GroqQuestionGenerator('llama-3.3-70b-versatile');
$questions = $groqGenerator->generate(
    'Analytical Ability',
    'professional',
    3
);
```

### Validation & Improvement

```php
// Validate a single question
$validation = QuestionValidator::validateQuestion($question);
if ($validation['is_valid']) {
    // Question is valid
}

// Improve formatting
$improved = QuestionValidator::improveFormatting($question);

// Check for duplicates
$duplicates = QuestionValidator::checkSemanticDuplicates($questions, $existingQuestions);

// Full validation pipeline
$result = QuestionValidator::validateImproveAndCheckDuplicates($questions, $existingQuestions);
```

### Configuration

```php
// In .env file
AI_PROVIDER=gemini      // or 'groq' or 'mock'
AI_KEY=your_api_key_here
AI_MODEL=gemini-2.5-flash  // or 'llama-3.3-70b-versatile' for Groq
```

---

## 🔧 Configuration Options

### Environment Variables

| Variable | Description | Default | Required |
|----------|-------------|---------|----------|
| `AI_PROVIDER` | AI provider to use | `gemini` | No |
| `AI_KEY` | API key for the provider | - | Yes (for non-mock) |
| `AI_MODEL` | Model to use | Provider default | No |

### Provider Defaults

| Provider | Default Model |
|----------|--------------|
| Gemini | `gemini-2.5-flash` |
| Groq | `llama-3.3-70b-versatile` |
| Mock | N/A |

---

## 🎨 Prompt Engineering

### Generation Prompts

Both generators use optimized prompts that include:

1. **Role Definition** - Expert question generator for CSE
2. **Category & Level** - Specific to the exam category
3. **Syllabus Guidelines** - Category-specific content requirements
4. **Duplicate Prevention** - List of existing questions to avoid
5. **Formatting Rules** - Category-specific formatting guidelines
6. **Structural Requirements** - 4 options, valid correct_option_index
7. **Output Schema** - JSON structure with required fields

### Verification Prompts

Verifiers use prompts that include:

1. **Role Definition** - Expert reviewer and quality auditor
2. **Questions to Audit** - The generated questions to verify
3. **Database Candidates** - Existing questions for duplicate checking
4. **Verification Rules** - Structural, factual, and semantic checks
5. **Output Schema** - JSON structure with validation metadata

---

## 📊 Output Structure

### Generated Question

```php
[
    [
        'question_text' => 'What is the capital of the Philippines?',
        'options' => [
            'Manila',
            'Cebu',
            'Davao',
            'Quezon City'
        ],
        'correct_option_index' => 0,
        'explanation' => 'Manila is the capital of the Philippines...',
        'problem_type_tag' => 'general-info-capital'
    ],
    // ... more questions
]
```

### Verified Question (GeneratedQuestionData)

```php
[
    GeneratedQuestionData {
        question_text: "What is the capital of the Philippines?"
        options: ["Manila", "Cebu", "Davao", "Quezon City"]
        correct_option_index: 0
        explanation: "Manila is the capital of the Philippines..."
        problem_type_tag: "general-info-capital"
        is_valid: true
        error_reason: null
    },
    // ... more questions
]
```

---

## 🛡️ Error Handling

### API Errors

All API calls handle:
- **Network errors** - Connection timeouts, DNS failures
- **Authentication errors** - Invalid API keys
- **Rate limiting** - 429 status codes
- **Invalid responses** - Malformed JSON, wrong structure
- **Quota exceeded** - Free tier limits

### Fallback Strategy

```
1. Try configured provider (Gemini/Groq)
2. On failure → Log error + Set error in Settings
3. On failure → Fall back to Mock generator
4. On failure → Return questions marked with error_reason
```

### Error Logging

All errors are logged with:
- **Error message**
- **Provider name**
- **API status code** (if available)
- **Response body** (if available)

---

## 🧪 Testing

### Unit Tests

```php
// Test mock generator
public function test_mock_generator()
{
    $generator = new MockQuestionGenerator();
    $questions = $generator->generate('Numerical Ability', 'professional', 5);
    
    $this->assertCount(5, $questions);
    $this->assertArrayHasKey('question_text', $questions[0]);
    $this->assertCount(4, $questions[0]['options']);
}

// Test validation
public function test_question_validation()
{
    $question = [
        'question_text' => 'Test question',
        'options' => ['A', 'B', 'C', 'D'],
        'correct_option_index' => 0,
        'explanation' => 'Test explanation',
        'problem_type_tag' => 'test'
    ];
    
    $validation = QuestionValidator::validateQuestion($question);
    $this->assertTrue($validation['is_valid']);
}

// Test with mocking
public function test_gemini_generator_with_mock()
{
    $mockHttp = $this->mock(Http::class);
    $mockHttp->shouldReceive('post')
        ->andReturn(new Response([
            'candidates' => [[
                'content' => [[
                    'parts' => [[
                        'text' => json_encode([['question_text' => 'Test', 'options' => ['A','B','C','D'], 'correct_option_index' => 0, 'explanation' => 'Test', 'problem_type_tag' => 'test']])
                    ]]
                ]]
            ]]
        ], 200));
    
    $generator = new GeminiQuestionGenerator();
    $questions = $generator->generate('Test', 'professional', 1);
    
    $this->assertCount(1, $questions);
}
```

---

## 📈 Performance Considerations

### Batching

- Questions are generated in **batches of 10** (max) to prevent token limits
- Verification is done in **batches of 10** to prevent payload issues
- Large requests are automatically chunked

### Caching

- **Existing questions** are fetched once per batch for duplicate prevention
- **Syllabus guidelines** are cached in the QuestionSyllabus class
- **API responses** could be cached (future enhancement)

### Timeouts

- **120 seconds** timeout for all API calls
- **No execution time limit** for generation (set_time_limit(0))

---

## 🚀 Deployment Notes

### Requirements

- PHP 8.3+
- Laravel 11+
- Guzzle HTTP client (included with Laravel)
- Internet access for API calls

### Configuration

```bash
# Set environment variables
cp .env.example .env
php artisan key:generate

# Configure AI provider
echo "AI_PROVIDER=gemini" >> .env
echo "AI_KEY=your_gemini_api_key" >> .env

# Or for Groq
echo "AI_PROVIDER=groq" >> .env
echo "AI_KEY=your_groq_api_key" >> .env
echo "AI_MODEL=llama-3.3-70b-versatile" >> .env
```

### Testing Configuration

```bash
# Run tests
php artisan test

# Check AI configuration
php artisan tinker
>>> app(AIService::class)->isConfigured();
```

---

## 🔮 Future Enhancements

### 1. **Additional Providers**
- OpenAI (GPT-4, GPT-3.5)
- Anthropic (Claude 3)
- Mistral AI
- Local LLMs (Ollama, etc.)

### 2. **Advanced Features**
- **Question difficulty scoring** - Rate questions by difficulty
- **Topic classification** - Auto-classify questions by topic
- **Quality scoring** - Score questions based on multiple criteria
- **Batch processing with queues** - Use Laravel queues for large batches

### 3. **Improved Validation**
- **Factual accuracy checking** - Cross-reference with knowledge base
- **Grammar checking** - Validate question text grammar
- **Option balance** - Ensure options are balanced and plausible
- **Explanation quality** - Validate explanation clarity

### 4. **Monitoring & Analytics**
- **Usage tracking** - Track API usage and costs
- **Performance metrics** - Monitor generation speed and quality
- **Error tracking** - Track and analyze API errors
- **Rate limiting** - Implement per-user rate limits

---

## 📚 API Documentation

### Google Gemini API

- **Endpoint**: `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`
- **Authentication**: API key in query parameter
- **Model**: `gemini-2.5-flash` (recommended)
- **Response Format**: JSON with structured schema

### Groq API

- **Endpoint**: `https://api.groq.com/openai/v1/chat/completions`
- **Authentication**: Bearer token in Authorization header
- **Model**: `llama-3.3-70b-versatile` (recommended)
- **Response Format**: OpenAI-compatible JSON

---

## 🎯 Best Practices

### 1. **Error Handling**
```php
try {
    $questions = $aiService->generateQuestions(...);
} catch (\Exception $e) {
    Log::error('AI generation failed: ' . $e->getMessage());
    // Fall back to mock or cached questions
}
```

### 2. **Validation**
```php
$validation = QuestionValidator::validateQuestion($question);
if (!$validation['is_valid']) {
    // Handle invalid question
}
```

### 3. **Duplicate Prevention**
```php
$duplicates = QuestionValidator::checkSemanticDuplicates($questions, $existingQuestions);
if (!empty($duplicates)) {
    // Filter out duplicates
}
```

### 4. **Configuration**
```php
// Always check if AI is configured
if (!app(AIService::class)->isConfigured()) {
    // Use mock or show error
}
```

---

## 📞 Support

For issues or questions:
1. Check the **REFACTORING_NOTES.md** for architecture details
2. Review the **AI_IMPLEMENTATION.md** for implementation details
3. Check Laravel logs for error details
4. Verify API keys and configuration

---

## 🏁 Conclusion

This implementation provides a **complete, production-ready** AI service for the CSC Reviewer application with:

✅ **Multiple AI providers** (Gemini, Groq, Mock)
✅ **Comprehensive validation** and formatting
✅ **Duplicate detection** and prevention
✅ **Error handling** and fallback mechanisms
✅ **Clean architecture** following SOLID principles
✅ **Full testability** with dependency injection
✅ **Extensibility** for future enhancements

The system is designed to be **reliable, maintainable, and production-ready** while providing high-quality CSE questions for users.
