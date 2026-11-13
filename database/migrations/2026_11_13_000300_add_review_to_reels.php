<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reels in the admin panel: who reviewed a reel and why it was rejected / unpublished,
 * plus the reels.view / reels.manage permissions for the newsroom roles.
 */
return new class extends Migration
{
    private const GRANTS = [
        'Chief Editor' => ['reels.view', 'reels.manage'],
        'Editor' => ['reels.view', 'reels.manage'],
        'Community Moderator' => ['reels.view'],
    ];

    public function up(): void
    {
        Schema::table('reels', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('error_message');
            $table->foreignId('reviewed_by')->nullable()->after('created_by')->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['reels.view', 'reels.manage'] as $name) {
            Permission::findOrCreate($name, 'admin');
        }
        foreach (self::GRANTS as $role => $perms) {
            Role::where(['name' => $role, 'guard_name' => 'admin'])->first()?->givePermissionTo($perms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('reels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['rejection_reason', 'reviewed_at']);
        });
        Permission::whereIn('name', ['reels.view', 'reels.manage'])->where('guard_name', 'admin')->delete();
    }
};
