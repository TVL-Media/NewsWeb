<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comments, saves and shares can belong to a story OR a reel (exactly one of story_id / reel_id).
 * Likes are already polymorphic (likeable) and reports use target_type, so they need no change.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['comments', 'saves', 'shares'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('story_id')->nullable()->change();
                $table->foreignId('reel_id')->nullable()->after('story_id')->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('saves', fn (Blueprint $table) => $table->unique(['user_id', 'reel_id']));
        Schema::table('comments', fn (Blueprint $table) => $table->index(['reel_id', 'status']));
    }

    public function down(): void
    {
        Schema::table('comments', fn (Blueprint $table) => $table->dropIndex(['reel_id', 'status']));
        Schema::table('saves', fn (Blueprint $table) => $table->dropUnique(['user_id', 'reel_id']));

        foreach (['comments', 'saves', 'shares'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('reel_id');
            });
        }
    }
};
