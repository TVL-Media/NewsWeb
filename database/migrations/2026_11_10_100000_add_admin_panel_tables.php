<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin panel on top of the NewsWeb schema.
 *
 * Only additive changes to the website's tables (new nullable columns, enum → string so the
 * newsroom workflow can add statuses). Every existing website query keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Staff accounts: 2FA, invites, status ──────────────────────────────
        Schema::table('admins', function (Blueprint $t) {
            $t->string('password')->nullable()->change();          // invited staff have no password yet
            $t->string('role', 20)->nullable()->default(null)->change(); // legacy; access now comes from roles & permissions
            $t->string('avatar_path')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('status', 20)->default('active');           // active | inactive | invited
            $t->string('timezone', 64)->nullable();
            $t->text('two_factor_secret')->nullable();
            $t->text('two_factor_recovery_codes')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
            $t->string('invite_token', 100)->nullable()->index();
            $t->timestamp('invite_expires_at')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->softDeletes();
        });

        // ── Readers: restrictions ─────────────────────────────────────────────
        Schema::table('users', function (Blueprint $t) {
            $t->string('status', 20)->default('active')->index();  // active | restricted | suspended
            $t->timestamp('restricted_until')->nullable();
        });

        // ── Categories: description, icon, colour, on/off ─────────────────────
        Schema::table('categories', function (Blueprint $t) {
            $t->string('description', 300)->nullable();
            $t->string('visual_type', 10)->default('image');        // icon | image
            $t->string('icon_path')->nullable();
            $t->string('color_hex', 7)->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
        });

        // ── Stories: editorial workflow ───────────────────────────────────────
        Schema::table('stories', function (Blueprint $t) {
            // draft | pending_approval | changes_requested | scheduled | published | corrected | rejected | removed
            $t->string('status', 30)->default('draft')->change();
            $t->foreignId('author_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->foreignId('assigned_reviewer_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('scheduled_at')->nullable()->index();
            $t->unsignedTinyInteger('publish_attempts')->default(0);
            $t->text('last_publish_error')->nullable();
            $t->timestamp('unpublished_at')->nullable();
            $t->unsignedInteger('version_no')->default(1);
        });

        Schema::table('approvals', function (Blueprint $t) {
            // approved | edited | rejected | changes_requested
            $t->string('action', 30)->change();
        });

        Schema::table('story_versions', function (Blueprint $t) {
            $t->string('translation_status', 20)->nullable(); // ai | edited | approved | out_of_date (when machine-translated)
        });

        Schema::create('story_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('story_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version_no');
            $t->json('snapshot');
            $t->string('change_note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamps();
            $t->index(['story_id', 'version_no']);
        });

        Schema::create('publish_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('story_id')->constrained()->cascadeOnDelete();
            $t->timestamp('scheduled_for')->nullable();
            $t->timestamp('attempted_at')->useCurrent();
            $t->string('result', 10);
            $t->text('error_message')->nullable();
        });

        Schema::create('tags', function (Blueprint $t) {
            $t->id();
            $t->string('name', 60);
            $t->string('slug', 80)->unique();
            $t->timestamps();
        });

        Schema::create('story_tag', function (Blueprint $t) {
            $t->foreignId('story_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $t->primary(['story_id', 'tag_id']);
        });

        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->string('disk', 20)->default('public');
            $t->string('path')->nullable();
            $t->string('original_name')->nullable();
            $t->string('mime_type', 100)->nullable();
            $t->string('kind', 20)->index(); // image | video | audio | document
            $t->unsignedBigInteger('size_bytes')->default(0);
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->string('alt_text')->nullable();
            $t->string('caption', 500)->nullable();
            $t->string('credit')->nullable();
            $t->string('external_url', 500)->nullable();
            $t->string('provider', 20)->default('upload');
            $t->json('conversions')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });

        // ── AI translation EN ↔ HI ────────────────────────────────────────────
        Schema::create('translation_jobs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('story_id')->constrained()->cascadeOnDelete();
            $t->string('source_language', 2);
            $t->string('target_language', 2);
            $t->string('status', 20)->default('queued'); // queued | running | ai_completed | approved | rejected | failed
            $t->string('provider', 30)->nullable();
            $t->json('result')->nullable();
            $t->text('error')->nullable();
            $t->unsignedInteger('characters')->default(0);
            $t->foreignId('requested_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });

        Schema::create('translation_glossary', function (Blueprint $t) {
            $t->id();
            $t->string('language_code', 10)->index();
            $t->string('source_term', 120);
            $t->string('target_term', 120)->nullable();
            $t->boolean('do_not_translate')->default(false);
            $t->timestamps();
        });

        // ── Polls and moderation ──────────────────────────────────────────────
        Schema::table('polls', function (Blueprint $t) {
            $t->string('status', 20)->default('pending')->change(); // pending | live | closed | rejected
            $t->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete(); // editorial polls
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->text('rejection_reason')->nullable();
        });

        Schema::table('reports', function (Blueprint $t) {
            $t->string('status', 20)->default('open')->change(); // open | escalated | actioned | dismissed
            $t->text('details')->nullable();
            $t->foreignId('handled_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('handled_at')->nullable();
        });

        Schema::create('moderation_actions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('report_id')->nullable();
            $t->string('target_type', 30);
            $t->unsignedBigInteger('target_id');
            $t->string('action', 30);
            $t->string('reason', 60)->nullable();
            $t->text('note')->nullable();
            $t->unsignedInteger('reports_closed')->default(0);
            $t->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['target_type', 'target_id']);
        });

        Schema::create('user_status_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->string('reason', 60)->nullable();
            $t->text('note')->nullable();
            $t->timestamp('until')->nullable();
            $t->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->unsignedBigInteger('report_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        // ── Panel infrastructure ──────────────────────────────────────────────
        Schema::create('admin_login_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->string('email')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->boolean('success')->default(false);
            $t->string('failure_reason', 100)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->string('action', 80)->index();
            $t->string('module', 40)->nullable()->index();
            $t->string('subject_type', 120)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('subject_label')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->text('reason')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
            $t->index(['subject_type', 'subject_id']);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('group', 40)->index();
            $t->string('key', 80)->unique();
            $t->json('value')->nullable();
            $t->boolean('is_encrypted')->default(false);
            $t->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('admin_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $t->string('type', 60);
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('url')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['admin_id', 'read_at']);
        });
    }

    public function down(): void
    {
        foreach (['admin_notifications', 'settings', 'audit_logs', 'admin_login_logs', 'user_status_logs', 'moderation_actions',
            'translation_glossary', 'translation_jobs', 'media', 'story_tag', 'tags', 'publish_logs', 'story_snapshots'] as $table) {
            Schema::dropIfExists($table);
        }
        // Column additions are left in place: they are nullable and harmless to the website.
    }
};
