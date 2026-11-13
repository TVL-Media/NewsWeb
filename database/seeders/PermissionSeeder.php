<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Admin panel permissions and default roles. Safe to re-run: existing roles keep their edits. */
class PermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'Dashboard' => ['dashboard.view' => 'View dashboard'],
        'Stories' => [
            'content.view' => 'View stories', 'content.create' => 'Write stories', 'content.edit.own' => 'Edit own stories',
            'content.edit.any' => 'Edit any story', 'content.edit.published' => 'Edit live stories', 'content.submit' => 'Submit for review',
            'content.publish' => 'Publish / unpublish', 'content.schedule' => 'Schedule', 'content.delete' => 'Delete stories *',
        ],
        'Review' => ['review.view' => 'View approval queue', 'review.decide' => 'Approve, reject, request changes', 'review.assign' => 'Assign reviewers', 'review.override' => 'Approve own stories (override) *'],
        'Newsroom' => ['feeds.manage' => 'Feeds & sources', 'autopublish.manage' => 'Auto-publish settings *', 'briefing.manage' => 'Morning 5', 'quiz.manage' => 'Daily quiz'],
        'Site' => ['categories.view' => 'View categories', 'categories.manage' => 'Create, edit, reorder categories and tags', 'categories.delete' => 'Delete empty categories *', 'pages.manage' => 'Pages (topic, city, show)'],
        'Media' => ['media.upload' => 'Upload & use media', 'media.delete' => 'Delete media'],
        'Reels' => ['reels.view' => 'View reels', 'reels.manage' => 'Approve, reject, upload & re-generate reels'],
        'Polls' => ['polls.view' => 'View polls', 'polls.manage' => 'Create, approve & manage polls', 'polls.close' => 'Close polls', 'polls.results' => 'View poll results'],
        'Translation' => ['translation.request' => 'Request AI translations', 'translation.review' => 'Review & approve translations', 'translation.settings' => 'Translation settings & glossary *'],
        'Readers' => ['users.view' => 'View readers', 'users.view.pii' => 'See full phone numbers', 'users.restrict' => 'Restrict readers', 'users.suspend' => 'Suspend readers *', 'streaks.manage' => 'Streak settings & fixes *'],
        'Moderation' => ['comments.moderate' => 'Hide / restore comments', 'reports.view' => 'View reports', 'reports.handle' => 'Act on reports', 'reports.escalate' => 'Escalate reports'],
        'Analytics' => ['analytics.view' => 'View analytics (own area)', 'analytics.all' => 'View all analytics tabs', 'analytics.export' => 'Export CSV'],
        'Administration' => ['settings.manage' => 'Platform settings *', 'admins.manage' => 'Manage admin team *', 'roles.manage' => 'Manage roles & permissions *', 'audit.view' => 'View audit log'],
    ];

    private const EDITOR = ['dashboard.view', 'content.view', 'content.create', 'content.edit.own', 'content.edit.any', 'content.edit.published', 'content.submit',
        'content.publish', 'content.schedule', 'review.view', 'review.decide', 'briefing.manage', 'quiz.manage', 'categories.view', 'media.upload',
        'polls.view', 'polls.manage', 'polls.close', 'polls.results', 'reels.view', 'reels.manage', 'translation.request', 'translation.review',
        'comments.moderate', 'reports.view', 'reports.handle', 'reports.escalate', 'analytics.view'];

    /** "Editor" / "Chief Editor" match the website's original editor / chief roles; admin → Super Admin. */
    public const ROLES = [
        'Super Admin' => '*',
        'Chief Editor' => [...self::EDITOR, 'review.assign', 'feeds.manage', 'autopublish.manage', 'content.delete', 'users.view', 'users.restrict', 'analytics.all', 'analytics.export', 'audit.view'],
        'Editor' => self::EDITOR,
        'Writer' => ['dashboard.view', 'content.view', 'content.create', 'content.edit.own', 'content.submit', 'categories.view', 'media.upload', 'translation.request'],
        'Community Moderator' => ['dashboard.view', 'comments.moderate', 'users.view', 'users.restrict', 'users.suspend', 'reports.view', 'reports.handle', 'reports.escalate', 'polls.view', 'polls.manage', 'reels.view', 'analytics.view', 'analytics.export'],
        'Analyst' => ['dashboard.view', 'analytics.view', 'analytics.all', 'analytics.export'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (self::PERMISSIONS as $perms) {
            foreach (array_keys($perms) as $name) {
                Permission::findOrCreate($name, 'admin');
            }
        }
        foreach (self::ROLES as $role => $perms) {
            $r = Role::findOrCreate($role, 'admin');
            if ($perms !== '*' && $r->permissions()->count() === 0) {
                $r->syncPermissions(array_values(array_unique($perms)));
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Website role → panel role, for staff created before the panel existed. */
    public static function roleFor(?string $legacy): string
    {
        return match ($legacy) {
            'admin' => 'Super Admin',
            'chief' => 'Chief Editor',
            default => 'Editor',
        };
    }
}
