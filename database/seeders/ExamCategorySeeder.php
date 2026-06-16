<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExamCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Numerical Ability',
                'description' => 'Covers basic arithmetic, word problems, data interpretation, and number sequences.',
                'level' => 'both',
            ],
            [
                'name' => 'Verbal Ability',
                'description' => 'Covers grammar, vocabulary, sentence completion, paragraph organization, and reading comprehension.',
                'level' => 'both',
            ],
            [
                'name' => 'Analytical Ability',
                'description' => 'Covers word association, identifying assumptions, logical reasoning, and pattern/matrix analysis.',
                'level' => 'professional',
            ],
            [
                'name' => 'General Information',
                'description' => 'Covers the Philippine Constitution, Code of Conduct for Public Officials, Peace and Human Rights, and Environmental Concepts.',
                'level' => 'both',
            ],
        ];

        foreach ($categories as $category) {
            \App\Models\ExamCategory::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
