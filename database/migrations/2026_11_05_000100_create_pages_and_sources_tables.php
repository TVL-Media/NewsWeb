<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_hi')->nullable();
            $table->string('slug')->unique();
            $table->enum('type', ['topic', 'city', 'show'])->default('topic');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->text('description')->nullable();
            $table->string('desk_label')->nullable(); // "SPORTS DESK"
            $table->boolean('verified')->default(false);
            // Black result strip on the topic page (shown only when set).
            $table->string('pinned_label')->nullable();   // "RESULT"
            $table->string('pinned_text')->nullable();    // score line
            $table->unsignedBigInteger('pinned_story_id')->nullable(); // "Match report ›"
            // "Upcoming" date tiles in the right rail: [{date, title, subtitle}]
            $table->json('upcoming')->nullable();
            $table->timestamps();
        });

        Schema::create('page_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['page_id', 'user_id']);
        });

        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url')->nullable();
            $table->timestamps();
        });

        Schema::create('feed_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->enum('type', ['agency', 'rss'])->default('rss');
            $table->boolean('trusted')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_sources');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('page_follows');
        Schema::dropIfExists('pages');
    }
};
