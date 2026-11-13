<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('language', ['en', 'hi']);
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('video_720_path')->nullable();
            $table->string('video_480_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->enum('source_type', ['auto', 'upload'])->default('auto');
            $table->string('source_path')->nullable(); // uploads: the original file, kept for re-processing
            $table->string('source_hash', 32)->nullable(); // auto: hash of the story content the reel was built from
            $table->enum('status', ['processing', 'pending_approval', 'published', 'rejected', 'failed'])->default('processing')->index();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete(); // uploader
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('shares_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
            $table->index(['story_id', 'language']);
        });

        Schema::create('reel_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reel_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('watched_seconds')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->index(['reel_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reel_views');
        Schema::dropIfExists('reels');
    }
};
