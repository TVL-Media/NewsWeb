<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Likes on stories and on comments ("Like · Reply · Report").
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('likeable');
            $table->timestamps();
            $table->unique(['user_id', 'likeable_type', 'likeable_id']);
        });

        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['surprising', 'unfair', 'need_info', 'good_news']);
            $table->timestamps();
            $table->unique(['user_id', 'story_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->enum('status', ['visible', 'hidden'])->default('visible');
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();
            $table->index(['story_id', 'status']);
        });

        Schema::create('saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'story_id']);
        });

        Schema::create('shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 30)->default('link'); // whatsapp, image, link, …
            $table->timestamps();
        });

        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); // null = editorial poll
            $table->string('question');
            $table->enum('status', ['pending', 'live', 'closed', 'rejected'])->default('pending')->index();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamps();
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['poll_id', 'user_id']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_type', 20); // story | comment | poll
            $table->unsignedBigInteger('target_id');
            $table->string('reason');
            $table->enum('status', ['open', 'actioned', 'dismissed'])->default('open')->index();
            $table->timestamps();
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
        Schema::dropIfExists('shares');
        Schema::dropIfExists('saves');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('reactions');
        Schema::dropIfExists('likes');
    }
};
