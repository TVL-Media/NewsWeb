<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Why a draft could not auto-publish (empty = every check passed). Shown in the approval queue and the auto-publish log. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_checks', function (Blueprint $table) {
            $table->json('reasons')->nullable()->after('decision');
            $table->json('risk_flags')->nullable()->after('sensitive_reasons'); // raw AI risk flags
        });
    }

    public function down(): void
    {
        Schema::table('ai_checks', function (Blueprint $table) {
            $table->dropColumn(['reasons', 'risk_flags']);
        });
    }
};
