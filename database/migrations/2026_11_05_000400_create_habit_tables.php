<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('briefings', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->json('story_ids'); // exactly 5, in order
            $table->string('audio_path_en')->nullable();
            $table->string('audio_path_hi')->nullable();
            $table->unsignedSmallInteger('audio_seconds')->nullable();
            // Start second of each story in the joined audio, used to move the list highlight.
            $table->json('chapter_offsets')->nullable();
            $table->timestamps();
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->enum('status', ['draft', 'approved', 'published'])->default('draft');
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('question');
            $table->json('options'); // exactly 4
            $table->unsignedTinyInteger('correct_index');
            $table->text('explanation')->nullable();
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->json('answers')->nullable(); // [question_id => chosen_index]
            $table->unsignedTinyInteger('score')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'quiz_id']);
        });

        Schema::create('streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current')->default(0);
            $table->unsignedInteger('longest')->default(0);
            $table->date('last_day')->nullable();      // last IST day that counted
            $table->unsignedTinyInteger('freezes_left')->default(2);
            $table->string('freezes_month', 7)->nullable(); // "2026-11": when freezes were last reset
            $table->timestamps();
        });

        Schema::create('reading_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->date('read_on'); // IST calendar day, for streaks
            $table->unique(['user_id', 'story_id', 'read_on']);
            $table->index(['user_id', 'read_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_events');
        Schema::dropIfExists('streaks');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('briefings');
    }
};
