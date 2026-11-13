<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the city list grow from the original 6 demo cities to every state/UT capital plus
 * major cities, while keeping "Use my current location" working: latitude/longitude support
 * nearest-city matching, and is_popular drives the picker's default (unfiltered) list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->decimal('latitude', 9, 6)->nullable()->after('state');
            $table->decimal('longitude', 9, 6)->nullable()->after('latitude');
            $table->boolean('is_popular')->default(false)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'is_popular']);
        });
    }
};
