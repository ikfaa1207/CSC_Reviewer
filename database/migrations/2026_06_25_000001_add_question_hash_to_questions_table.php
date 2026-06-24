<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('question_hash', 32)->nullable()->index()->after('problem_type_tag');
        });

        // Backfill existing rows with their normalized hash
        DB::table('questions')->orderBy('id')->each(function ($row) {
            $normalized = \App\Models\Question::normalize($row->question_text);
            DB::table('questions')
                ->where('id', $row->id)
                ->update(['question_hash' => md5($normalized)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['question_hash']);
            $table->dropColumn('question_hash');
        });
    }
};
