<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_hi');
            $table->string('slug')->unique();
            $table->string('image_path')->nullable(); // photo tile on Explore and onboarding
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_hi');
            $table->string('state');
            $table->timestamps();
        });

        // Readers sign in with phone + OTP only, so there is no email or password here.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone', 15)->unique();
            $table->enum('language', ['en', 'hi'])->default('en');
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('text_size', ['small', 'medium', 'large'])->default('medium');
            $table->enum('theme', ['light', 'dark'])->default('light');
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('category_user', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'user_id']);
        });

        Schema::create('otp_requests', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 15)->index();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['editor', 'chief', 'admin'])->default('editor');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary(); // used by admins (Filament)
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('otp_requests');
        Schema::dropIfExists('category_user');
        Schema::dropIfExists('users');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('categories');
    }
};
