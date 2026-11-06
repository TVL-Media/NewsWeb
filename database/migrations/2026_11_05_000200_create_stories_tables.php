<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_url')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'published', 'rejected', 'corrected', 'removed'])->default('draft')->index();
            $table->enum('publish_mode', ['auto', 'manual'])->default('manual');
            $table->boolean('is_breaking')->default(false);
            $table->unsignedTinyInteger('importance')->default(5); // 1–10, used by PickMorning5 and the hero
            $table->string('photo_path')->nullable();
            $table->string('photo_credit')->nullable();
            $table->unsignedTinyInteger('read_minutes')->default(2);
            $table->string('correction_label')->nullable();
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('shares_count')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('story_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->enum('language', ['en', 'hi']);
            $table->string('headline');
            $table->text('card_text'); // ≤ 60 words
            $table->json('key_points'); // exactly 3
            $table->text('why_it_matters')->nullable();
            $table->text('simple_text')->nullable();
            $table->longText('full_text')->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedSmallInteger('audio_seconds')->nullable();
            $table->boolean('ai_assisted')->default(true);
            $table->timestamps();
            $table->unique(['story_id', 'language']);

            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'])) {
                $table->fullText(['headline', 'card_text']);
            }
        });

        Schema::create('story_clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('feed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_source_id')->constrained()->cascadeOnDelete();
            $table->string('url', 700)->unique();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('image_url', 700)->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('cluster_id')->nullable()->constrained('story_clusters')->nullOnDelete();
            $table->boolean('processed')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('ai_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->enum('risk_level', ['low', 'sensitive'])->default('low');
            $table->json('sensitive_reasons')->nullable();
            $table->decimal('confidence', 4, 3)->default(0);
            $table->json('fact_mismatches')->nullable();
            $table->decimal('copy_score', 4, 3)->default(0);
            $table->enum('decision', ['auto', 'approval']);
            $table->timestamps();
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editor_id')->constrained('admins')->cascadeOnDelete();
            $table->enum('action', ['approved', 'edited', 'rejected']);
            $table->text('reason')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
        });

        Schema::create('auto_publish_settings', function (Blueprint $table) {
            $table->id();
            $table->json('sensitive_categories'); // category slugs
            $table->json('sensitive_keywords');
            $table->decimal('confidence_threshold', 4, 3)->default(0.85);
            $table->decimal('copy_threshold', 4, 3)->default(0.15);
            $table->timestamps();
        });

        Schema::create('page_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_stories');
        Schema::dropIfExists('auto_publish_settings');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('ai_checks');
        Schema::dropIfExists('feed_items');
        Schema::dropIfExists('story_clusters');
        Schema::dropIfExists('story_versions');
        Schema::dropIfExists('stories');
    }
};
