<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReferenceQuestion;
use App\Models\ExamCategory;

class ReferenceQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = ExamCategory::all()->pluck('id', 'name')->toArray();

        $numericalId = $categories['Numerical Ability'] ?? 1;
        $verbalId = $categories['Verbal Ability'] ?? 2;
        $analyticalId = $categories['Analytical Ability'] ?? 3;
        $clericalId = $categories['Clerical Ability'] ?? 4;
        $generalId = $categories['General Information'] ?? 5;

        $questions = [
            // ==========================================
            // Numerical Ability (Professional)
            // ==========================================
            [
                'exam_category_id' => $numericalId,
                'level' => 'professional',
                'subtopic_tag' => 'numerical-work-rate',
                'question_text' => 'If A can do a piece of work in 10 days and B can do it in 15 days, how many days will it take for both of them to complete the work together?',
                'options' => ['6 days', '8 days', '12 days', '5 days'],
                'correct_option_index' => 0,
                'explanation' => '1. Calculate work rates: A rate is 1/10 per day, B rate is 1/15 per day.\n2. Combined rate: 1/10 + 1/15 = 3/30 + 2/30 = 5/30 = 1/6.\n3. Days to complete together: 1 / (1/6) = 6 days.'
            ],
            [
                'exam_category_id' => $numericalId,
                'level' => 'professional',
                'subtopic_tag' => 'numerical-speed-distance',
                'question_text' => 'A car travels at 60 km/h for 2 hours and then at 80 km/h for 3 hours. What is the average speed of the car for the entire journey?',
                'options' => ['72 km/h', '70 km/h', '74 km/h', '75 km/h'],
                'correct_option_index' => 0,
                'explanation' => '1. Total distance = (60 * 2) + (80 * 3) = 120 + 240 = 360 km.\n2. Total time = 2 + 3 = 5 hours.\n3. Average speed = Total Distance / Total Time = 360 / 5 = 72 km/h.'
            ],
            [
                'exam_category_id' => $numericalId,
                'level' => 'professional',
                'subtopic_tag' => 'numerical-percentage-discount',
                'question_text' => 'An item is marked at ₱1,500.00. If a store offers a 20% discount, what is the selling price of the item?',
                'options' => ['₱1,200.00', '₱1,150.00', '₱1,300.00', '₱1,250.00'],
                'correct_option_index' => 0,
                'explanation' => '1. Discount amount = ₱1,500.00 * 20% = ₱300.00.\n2. Selling price = ₱1,500.00 - ₱300.00 = ₱1,200.00.'
            ],
            [
                'exam_category_id' => $numericalId,
                'level' => 'professional',
                'subtopic_tag' => 'numerical-interest-investment',
                'question_text' => 'Calculate the simple interest on a principal of ₱10,000.00 invested at an annual rate of 5% for 3 years.',
                'options' => ['₱1,500.00', '₱1,000.00', '₱1,200.00', '₱1,800.00'],
                'correct_option_index' => 0,
                'explanation' => 'Simple Interest (SI) = Principal (P) * Rate (R) * Time (T) = ₱10,000.00 * 0.05 * 3 = ₱1,500.00.'
            ],
            [
                'exam_category_id' => $numericalId,
                'level' => 'professional',
                'subtopic_tag' => 'numerical-age-problem',
                'question_text' => 'A father is currently three times as old as his son. In 12 years, he will be twice as old as his son. What is the father\'s current age?',
                'options' => ['36 years', '40 years', '30 years', '45 years'],
                'correct_option_index' => 0,
                'explanation' => '1. Let son age = x, father age = 3x.\n2. In 12 years: (3x + 12) = 2(x + 12) => 3x + 12 = 2x + 24 => x = 12.\n3. Father age = 3 * 12 = 36 years.'
            ],
            [
                'exam_category_id' => $numericalId,
                'level' => 'professional',
                'subtopic_tag' => 'numerical-sequence-completion',
                'question_text' => 'What is the next number in the sequence: 3, 7, 15, 31, 63, ________?',
                'options' => ['127', '120', '125', '128'],
                'correct_option_index' => 0,
                'explanation' => 'The pattern is that each term is double the previous term plus 1: (3*2)+1=7, (7*2)+1=15, (15*2)+1=31, (31*2)+1=63. The next term is (63*2)+1 = 127.'
            ],
            [
                'exam_category_id' => $numericalId,
                'level' => 'professional',
                'subtopic_tag' => 'numerical-ratio-proportion',
                'question_text' => 'Two numbers are in the ratio of 3:5. If their sum is 160, what is the value of the larger number?',
                'options' => ['100', '90', '110', '120'],
                'correct_option_index' => 0,
                'explanation' => '1. Let the parts be 3x and 5x.\n2. 3x + 5x = 160 => 8x = 160 => x = 20.\n3. Larger number = 5x = 5 * 20 = 100.'
            ],
            [
                'exam_category_id' => $numericalId,
                'level' => 'professional',
                'subtopic_tag' => 'numerical-geometry-measurement',
                'question_text' => 'A rectangular field has a length of 20 meters and a width of 15 meters. What is the perimeter of the field in meters?',
                'options' => ['70 meters', '60 meters', '80 meters', '50 meters'],
                'correct_option_index' => 0,
                'explanation' => 'Perimeter = 2 * (length + width) = 2 * (20 + 15) = 2 * 35 = 70 meters.'
            ],
            [
                'exam_category_id' => $numericalId,
                'level' => 'professional',
                'subtopic_tag' => 'numerical-algebraic-equations',
                'question_text' => 'Solve for x in the equation: 3x - 7 = 5x + 9.',
                'options' => ['-8', '8', '-6', '6'],
                'correct_option_index' => 0,
                'explanation' => '3x - 7 = 5x + 9 => -7 - 9 = 5x - 3x => -16 = 2x => x = -8.'
            ],
            [
                'exam_category_id' => $numericalId,
                'level' => 'professional',
                'subtopic_tag' => 'numerical-data-sufficiency',
                'question_text' => 'Is the integer n even?\n1) n + 3 is an odd integer.\n2) n/2 is an even integer.',
                'options' => [
                    'Statement (1) ALONE is sufficient, but statement (2) alone is not sufficient.',
                    'Statement (2) ALONE is sufficient, but statement (1) alone is not sufficient.',
                    'Each statement ALONE is sufficient.',
                    'BOTH statements TOGETHER are sufficient, but NEITHER statement ALONE is sufficient.'
                ],
                'correct_option_index' => 2,
                'explanation' => '1. From statement 1: n + 3 is odd, so n must be even. Sufficient.\n2. From statement 2: n/2 is even, so n must be a multiple of 4, which means n is even. Sufficient. Hence, each statement alone is sufficient.'
            ],

            // ==========================================
            // Numerical Ability (Sub-Professional)
            // ==========================================
            [
                'exam_category_id' => $numericalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'numerical-basic-arithmetic',
                'question_text' => 'Calculate the value of the expression: (12 + 8) * 3 - 15 / 5.',
                'options' => ['57', '60', '55', '50'],
                'correct_option_index' => 0,
                'explanation' => '1. Parentheses first: (12 + 8) = 20.\n2. Multiply: 20 * 3 = 60.\n3. Divide: 15 / 5 = 3.\n4. Subtract: 60 - 3 = 57.'
            ],
            [
                'exam_category_id' => $numericalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'numerical-fraction-operations',
                'question_text' => 'Find the sum of the fractions: 3/4 and 2/3.',
                'options' => ['17/12', '5/7', '6/7', '5/12'],
                'correct_option_index' => 0,
                'explanation' => 'Common denominator is 12: 3/4 + 2/3 = 9/12 + 8/12 = 17/12 (or 1 5/12).'
            ],
            [
                'exam_category_id' => $numericalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'numerical-decimal-percentage',
                'question_text' => 'What is 15% of ₱2,500.00?',
                'options' => ['₱375.00', '₱350.00', '₱400.00', '₱300.00'],
                'correct_option_index' => 0,
                'explanation' => 'Calculation: ₱2,500.00 * 0.15 = ₱375.00.'
            ],
            [
                'exam_category_id' => $numericalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'numerical-basic-averages',
                'question_text' => 'What is the average of 85, 90, 88, 92, and 85?',
                'options' => ['88', '89', '87', '90'],
                'correct_option_index' => 0,
                'explanation' => '1. Sum: 85 + 90 + 88 + 92 + 85 = 440.\n2. Divide by count: 440 / 5 = 88.'
            ],
            [
                'exam_category_id' => $numericalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'numerical-simple-word-problems',
                'question_text' => 'A student bought 5 notebooks for ₱25.00 each and 3 pens for ₱12.00 each. How much change will they receive from a ₱200.00 bill?',
                'options' => ['₱39.00', '₱45.00', '₱50.00', '₱35.00'],
                'correct_option_index' => 0,
                'explanation' => '1. Cost of notebooks: 5 * ₱25.00 = ₱125.00.\n2. Cost of pens: 3 * ₱12.00 = ₱36.00.\n3. Total cost: ₱125.00 + ₱36.00 = ₱161.00.\n4. Change: ₱200.00 - ₱161.00 = ₱39.00.'
            ],

            // ==========================================
            // Verbal Ability (Professional)
            // ==========================================
            [
                'exam_category_id' => $verbalId,
                'level' => 'professional',
                'subtopic_tag' => 'verbal-synonym-context',
                'question_text' => 'Choose the word closest in meaning to the quoted word: The manager was "diligent" in reviewing the reports before the meeting.',
                'options' => ['conscientious', 'negligent', 'hasty', 'apathetic'],
                'correct_option_index' => 0,
                'explanation' => 'The word "diligent" means showing care and conscientiousness in one\'s work. Thus, "conscientious" is the synonym. Negligent and apathetic are opposites.'
            ],
            [
                'exam_category_id' => $verbalId,
                'level' => 'professional',
                'subtopic_tag' => 'verbal-antonym-context',
                'question_text' => 'Choose the word opposite in meaning to the quoted word: Her actions were "conspicuous" during the quiet assembly.',
                'options' => ['unnoticeable', 'prominent', 'ostentatious', 'eccentric'],
                'correct_option_index' => 0,
                'explanation' => '"Conspicuous" means standing out so as to be clearly visible or attracting attention. The opposite is "unnoticeable". Prominent is a synonym.'
            ],
            [
                'exam_category_id' => $verbalId,
                'level' => 'professional',
                'subtopic_tag' => 'verbal-single-analogy',
                'question_text' => 'Complete the analogy: book : author || painting : ________',
                'options' => ['artist', 'canvas', 'sculptor', 'gallery'],
                'correct_option_index' => 0,
                'explanation' => 'The relationship is object to creator: an author creates a book, and an artist creates a painting.'
            ],
            [
                'exam_category_id' => $verbalId,
                'level' => 'professional',
                'subtopic_tag' => 'verbal-double-analogy',
                'question_text' => 'Identify the pair of words that shares the same relationship as the given pair: architect : building',
                'options' => ['sculptor : statue', 'doctor : medicine', 'lawyer : courtroom', 'soldier : country'],
                'correct_option_index' => 0,
                'explanation' => 'An architect designs/creates a building. Similarly, a sculptor shapes/creates a statue.'
            ],
            [
                'exam_category_id' => $verbalId,
                'level' => 'professional',
                'subtopic_tag' => 'verbal-identifying-errors',
                'question_text' => 'Identify the word or phrase that is NOT acceptable in formal written English: Neither the teacher nor the students was ready for the sudden fire drill. No error.',
                'options' => ['Neither the', 'nor the', 'was ready', 'No error'],
                'correct_option_index' => 2,
                'explanation' => 'With "neither/nor" connecting a singular and plural subject, the verb must agree with the closer subject ("students", plural), so "was" should be plural "were".'
            ],
            [
                'exam_category_id' => $verbalId,
                'level' => 'professional',
                'subtopic_tag' => 'verbal-paragraph-organization',
                'question_text' => 'Arrange the following sentences in a logical order to form a coherent paragraph:\nA. The results showed a significant improvement.\nB. Next, we collected data over three weeks.\nC. First, we set up the experimental variables.\nD. Finally, we compiled the report.\nE. This indicates the method was highly effective.',
                'options' => ['CBADE', 'CABDE', 'BCADE', 'CBAED'],
                'correct_option_index' => 0,
                'explanation' => 'The flow starts with setting up the variables (C), collecting data next (B), showing the results (A), compiling the report final (D), and drawing the conclusion (E).'
            ],
            [
                'exam_category_id' => $verbalId,
                'level' => 'professional',
                'subtopic_tag' => 'verbal-reading-comprehension',
                'question_text' => 'Read the passage: The Civil Service Commission recently announced new guidelines for the online application system. The update aims to decrease server downtime during peak registration periods and streamline the document verification process by introducing a pre-screening algorithm. What is the primary purpose of the new guidelines?',
                'options' => [
                    'To improve the performance and ease of the online application system.',
                    'To completely eliminate the manual verification of files.',
                    'To force all applicants to register at off-peak hours.',
                    'To increase the fees of civil service examination registration.'
                ],
                'correct_option_index' => 0,
                'explanation' => 'The text states the update aims to decrease downtime and streamline verification, which translates to improving performance and ease.'
            ],
            [
                'exam_category_id' => $verbalId,
                'level' => 'professional',
                'subtopic_tag' => 'verbal-correct-usage',
                'question_text' => 'Choose the word that correctly completes the sentence: One of the team members ________ going to represent the company at the convention next week.',
                'options' => ['is', 'are', 'were', 'have been'],
                'correct_option_index' => 0,
                'explanation' => 'The subject is "One", which is singular. Thus, it requires the singular verb "is".'
            ],

            // ==========================================
            // Verbal Ability (Sub-Professional)
            // ==========================================
            [
                'exam_category_id' => $verbalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'verbal-spelling-verification',
                'question_text' => 'Identify the correctly spelled word:',
                'options' => ['Accommodation', 'Acomodation', 'Accomodation', 'Acommodation'],
                'correct_option_index' => 0,
                'explanation' => 'The correct spelling is "Accommodation" with double c and double m.'
            ],
            [
                'exam_category_id' => $verbalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'verbal-simple-synonym',
                'question_text' => 'Choose the word closest in meaning to the quoted word: The task was \"simple\" and took only five minutes.',
                'options' => ['easy', 'arduous', 'protracted', 'complex'],
                'correct_option_index' => 0,
                'explanation' => '"Simple" means easy to understand or do. The synonym is "easy".'
            ],
            [
                'exam_category_id' => $verbalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'verbal-simple-antonym',
                'question_text' => 'Choose the word opposite in meaning to the quoted word: The water in the reservoir was \"shallow\".',
                'options' => ['deep', 'murky', 'turbulent', 'clear'],
                'correct_option_index' => 0,
                'explanation' => 'The opposite of "shallow" (little depth) is "deep".'
            ],
            [
                'exam_category_id' => $verbalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'verbal-single-analogy-sub',
                'question_text' => 'Complete the analogy: dog : bark || cat : ________',
                'options' => ['meow', 'purr', 'chirp', 'neigh'],
                'correct_option_index' => 0,
                'explanation' => 'A dog produces a bark sound; a cat produces a meow sound.'
            ],
            [
                'exam_category_id' => $verbalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'verbal-paragraph-org-sub',
                'question_text' => 'Arrange the sentences in logical order:\nA. He walked to school.\nB. He woke up early.\nC. He ate his breakfast.\nD. He brushed his teeth.',
                'options' => ['BCDA', 'BACD', 'CBDA', 'BDCA'],
                'correct_option_index' => 0,
                'explanation' => 'Logical sequence: Waking up early (B), eating breakfast (C), brushing teeth (D), then walking to school (A).'
            ],
            [
                'exam_category_id' => $verbalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'verbal-correct-usage-sub',
                'question_text' => 'Choose the word that correctly completes the sentence: The children left ________ bags on the table over ________.',
                'options' => ['their, there', 'there, their', 'they\'re, their', 'their, they\'re'],
                'correct_option_index' => 0,
                'explanation' => 'First blank requires possessive "their" (their bags). Second blank requires location adverb "there" (over there).'
            ],
            [
                'exam_category_id' => $verbalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'verbal-reading-comprehension-sub',
                'question_text' => 'Read the short passage: Maria feeds her cat every morning before going to work. Afterward, she leaves a bowl of fresh water. When does Maria feed her cat?',
                'options' => ['in the morning', 'in the afternoon', 'before sleeping', 'at noon'],
                'correct_option_index' => 0,
                'explanation' => 'The passage states: "Maria feeds her cat every morning".'
            ],

            // ==========================================
            // Analytical Ability (Professional Only)
            // ==========================================
            [
                'exam_category_id' => $analyticalId,
                'level' => 'professional',
                'subtopic_tag' => 'analytical-logical-syllogism',
                'question_text' => 'All employees who complete the training are eligible for promotion. Some engineers completed the training. What can be logically concluded?',
                'options' => [
                    'Some engineers are eligible for promotion.',
                    'All engineers are eligible for promotion.',
                    'Only engineers are eligible for promotion.',
                    'No engineers are eligible for promotion.'
                ],
                'correct_option_index' => 0,
                'explanation' => 'Since some engineers completed the training and anyone who completes it is eligible, it follows that some engineers are eligible.'
            ],
            [
                'exam_category_id' => $analyticalId,
                'level' => 'professional',
                'subtopic_tag' => 'analytical-identifying-assumptions',
                'question_text' => 'Our company sales will double next year because we are launching a new marketing campaign in July. What is the unstated assumption?',
                'options' => [
                    'The new marketing campaign will be successful in driving sales.',
                    'The sales team will work twice as hard next year.',
                    'Competitors will not launch marketing campaigns.',
                    'Sales have been decreasing over the past three years.'
                ],
                'correct_option_index' => 0,
                'explanation' => 'The argument connects launching a campaign to doubling sales, which assumes the campaign will indeed be successful.'
            ],
            [
                'exam_category_id' => $analyticalId,
                'level' => 'professional',
                'subtopic_tag' => 'analytical-word-association',
                'question_text' => 'Which of the following words does NOT belong in the group: circle, square, triangle, sphere?',
                'options' => ['sphere', 'circle', 'square', 'triangle'],
                'correct_option_index' => 0,
                'explanation' => 'Circle, square, and triangle are two-dimensional flat shapes. Sphere is a three-dimensional shape.'
            ],
            [
                'exam_category_id' => $analyticalId,
                'level' => 'professional',
                'subtopic_tag' => 'analytical-number-letter-sequence',
                'question_text' => 'Find the next item in the sequence: AZ, CX, EV, GT, ________',
                'options' => ['IR', 'HS', 'JQ', 'KP'],
                'correct_option_index' => 0,
                'explanation' => 'The first letter increases by 2 alphabetical positions (A, C, E, G -> I). The second letter decreases by 2 positions (Z, X, V, T -> R). Next is IR.'
            ],
            [
                'exam_category_id' => $analyticalId,
                'level' => 'professional',
                'subtopic_tag' => 'analytical-abstract-reasoning',
                'question_text' => 'Which option completes the sequence pattern?\n\n[diagram]{\"type\": \"sequence\", \"steps\": [{\"shapes\": [{\"shape\": \"square\", \"fill\": \"none\", \"rotation\": 0}]}, {\"shapes\": [{\"shape\": \"square\", \"fill\": \"solid\", \"rotation\": 45}]}, {\"shapes\": [{\"shape\": \"square\", \"fill\": \"none\", \"rotation\": 90}]}, {\"blank\": true}]}[/diagram]',
                'options' => [
                    '[diagram]{\"shapes\": [{\"shape\": \"square\", \"fill\": \"solid\", \"rotation\": 135}]}[/diagram]',
                    '[diagram]{\"shapes\": [{\"shape\": \"square\", \"fill\": \"none\", \"rotation\": 0}]}[/diagram]',
                    '[diagram]{\"shapes\": [{\"shape\": \"circle\", \"fill\": \"solid\", \"rotation\": 0}]}[/diagram]',
                    '[diagram]{\"shapes\": [{\"shape\": \"square\", \"fill\": \"none\", \"rotation\": 180}]}[/diagram]'
                ],
                'correct_option_index' => 0,
                'explanation' => 'The pattern alternates between unfilled (none) and filled (solid) squares, rotating by 45 degrees. The 4th shape should be a filled (solid) square rotated at 135 degrees.'
            ],

            // ==========================================
            // Clerical Ability (Sub-Professional Only)
            // ==========================================
            [
                'exam_category_id' => $clericalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'clerical-alphabetizing-names',
                'question_text' => "Arrange the following names in alphabetical order:\n1. De Guzman, Maria\n2. De Castro, Albert\n3. De Guzman, Alicia\n4. De Castro, Arthur",
                'options' => ['2, 4, 3, 1', '2, 3, 4, 1', '4, 2, 1, 3', '2, 4, 1, 3'],
                'correct_option_index' => 0,
                'explanation' => 'Sorting names alphabetically: De Castro, Albert (2) -> De Castro, Arthur (4) -> De Guzman, Alicia (3) -> De Guzman, Maria (1). Order: 2, 4, 3, 1.'
            ],
            [
                'exam_category_id' => $clericalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'clerical-alphabetizing-filing',
                'question_text' => 'In a government archive, which of the following departments should be filed first alphabetically?',
                'options' => [
                    'Department of Agriculture',
                    'Department of Agrarian Reform',
                    'Department of Budget and Management',
                    'Department of Education'
                ],
                'correct_option_index' => 1,
                'explanation' => '"Agrarian Reform" comes alphabetically before "Agriculture" due to the sixth letter ("i" vs "a" wait, a-g-r-a-r-i-a vs a-g-r-i-c-u. Agrarian starts with a-g-r-a, Agriculture with a-g-r-i. Since "a" comes before "i", Agrarian Reform is filed first).'
            ],
            [
                'exam_category_id' => $clericalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'clerical-spelling-rules',
                'question_text' => 'Which of the following spellings follows the spelling rule for adding suffixes starting with a vowel to words ending in a silent \'e\'?',
                'options' => ['guidance', 'guidence', 'guideance', 'guidans'],
                'correct_option_index' => 0,
                'explanation' => 'Rule: Drop silent "e" when adding suffix starting with a vowel: guide + ance = guidance.'
            ],
            [
                'exam_category_id' => $clericalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'clerical-coding-substitution',
                'question_text' => 'If the code for \'CODE\' is \'3-15-4-5\', what is the code for \'DATA\'?',
                'options' => ['4-1-20-1', '4-1-21-1', '3-1-20-1', '4-2-20-2'],
                'correct_option_index' => 0,
                'explanation' => 'D=4, A=1, T=20, A=1. The code is 4-1-20-1.'
            ],
            [
                'exam_category_id' => $clericalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'clerical-data-verification',
                'question_text' => "Compare the two columns. Are they exactly the same or different?\nColumn 1: 849204-A\nColumn 2: 849204-A",
                'options' => [
                    'Exactly the same',
                    'Different in number digit',
                    'Different in letter suffix',
                    'Different in punctuation'
                ],
                'correct_option_index' => 0,
                'explanation' => 'Both columns match exactly: 849204-A.'
            ],

            // ==========================================
            // General Information (Professional)
            // ==========================================
            [
                'exam_category_id' => $generalId,
                'level' => 'professional',
                'subtopic_tag' => 'general-info-constitution-rights',
                'question_text' => 'Which section of the Bill of Rights (Article III) of the 1987 Constitution protects citizens against unreasonable searches and seizures without a warrant?',
                'options' => ['Section 2', 'Section 1', 'Section 3', 'Section 4'],
                'correct_option_index' => 0,
                'explanation' => 'Section 2, Article III protects against unreasonable searches and seizures.'
            ],
            [
                'exam_category_id' => $generalId,
                'level' => 'professional',
                'subtopic_tag' => 'general-info-constitution-structure',
                'question_text' => 'Under the 1987 Philippine Constitution, how many terms can a member of the House of Representatives serve consecutively?',
                'options' => ['Three terms', 'Two terms', 'Four terms', 'No limit'],
                'correct_option_index' => 0,
                'explanation' => 'Section 7, Article VI specifies a term of three years, and limits members to no more than three consecutive terms.'
            ],
            [
                'exam_category_id' => $generalId,
                'level' => 'professional',
                'subtopic_tag' => 'general-info-ra6713-conduct',
                'question_text' => 'Which of the following acts is prohibited for public officials under Republic Act No. 6713?',
                'options' => [
                    'Accepting gifts or fees in connection with their official duties.',
                    'Filing their Statement of Assets, Liabilities, and Net Worth (SALN).',
                    'Responding to letters from the public within fifteen days.',
                    'Submitting reports on their performance twice a year.'
                ],
                'correct_option_index' => 0,
                'explanation' => 'Section 7 of RA 6713 prohibits public officials from soliciting or accepting gifts, loans, or favors.'
            ],
            [
                'exam_category_id' => $generalId,
                'level' => 'professional',
                'subtopic_tag' => 'general-info-peace-human-rights',
                'question_text' => 'What international document declares the basic human rights that all human beings are entitled to?',
                'options' => [
                    'Universal Declaration of Human Rights',
                    'Treaty of Versailles',
                    'Geneva Convention',
                    'Kyoto Protocol'
                ],
                'correct_option_index' => 0,
                'explanation' => 'The Universal Declaration of Human Rights (UDHR), adopted by the UN in 1948, lists fundamental human rights.'
            ],
            [
                'exam_category_id' => $generalId,
                'level' => 'professional',
                'subtopic_tag' => 'general-info-environmental-concepts',
                'question_text' => 'Which Philippine environmental law is otherwise known as the \'Clean Air Act of 1999\'?',
                'options' => ['RA 8749', 'RA 9003', 'RA 9275', 'RA 6969'],
                'correct_option_index' => 0,
                'explanation' => 'RA 8749 is the Philippine Clean Air Act of 1999.'
            ],

            // ==========================================
            // General Information (Sub-Professional)
            // ==========================================
            [
                'exam_category_id' => $generalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'general-info-constitution-rights',
                'question_text' => 'Under Article III Section 1 of the 1987 Constitution, no person shall be deprived of life, liberty, or property without ________.',
                'options' => ['due process of law', 'written consent', 'a trial by jury', 'compensation'],
                'correct_option_index' => 0,
                'explanation' => 'Section 1 states: "No person shall be deprived of life, liberty, or property without due process of law..."'
            ],
            [
                'exam_category_id' => $generalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'general-info-ra6713-conduct',
                'question_text' => 'According to RA 6713, within how many days must public officials respond to letters from the public?',
                'options' => ['15 working days', '10 working days', '5 working days', '30 working days'],
                'correct_option_index' => 0,
                'explanation' => 'RA 6713 requires a response to letters within 15 working days from receipt.'
            ],
            [
                'exam_category_id' => $generalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'general-info-peace-human-rights',
                'question_text' => 'Which of the following is a basic right of every child under the UN Convention on the Rights of the Child?',
                'options' => [
                    'The right to education and play',
                    'The right to own property and trade',
                    'The right to drive and work',
                    'The right to choose their tax rate'
                ],
                'correct_option_index' => 0,
                'explanation' => 'The UNCRC recognizes the right of children to receive free education and engage in play/leisure.'
            ],
            [
                'exam_category_id' => $generalId,
                'level' => 'sub_professional',
                'subtopic_tag' => 'general-info-environmental-concepts',
                'question_text' => 'Which Republic Act is otherwise known as the \'Ecological Solid Waste Management Act of 2000\'?',
                'options' => ['RA 9003', 'RA 8749', 'RA 9275', 'RA 9729'],
                'correct_option_index' => 0,
                'explanation' => 'RA 9003 is the Ecological Solid Waste Management Act of 2000.'
            ],
        ];

        foreach ($questions as $q) {
            ReferenceQuestion::create($q);
        }
    }
}
