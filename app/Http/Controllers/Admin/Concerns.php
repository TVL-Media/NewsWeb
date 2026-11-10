<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

/** Shared helpers for admin controllers. */
trait Concerns
{
    protected function need(string ...$perms): void
    {
        $a = auth('admin')->user();
        abort_unless(collect($perms)->contains(fn ($p) => $a->can($p)), 403, 'Your role does not include this action.');
    }

    protected function perPage(Request $r): int
    {
        return in_array((int) $r->query('per_page'), [25, 50, 100], true) ? (int) $r->query('per_page') : 25;
    }

    /** Resolve a date range from presets (today|7d|30d|month|custom). Returns [from, to, label] as local date strings. */
    protected function range(Request $r, string $default = '7d'): array
    {
        $tz = ptz();
        $p = $r->query('range', $default);
        $today = now($tz)->toDateString();

        return match ($p) {
            'today' => [$today, $today, 'Today', 'today'],
            '30d' => [now($tz)->subDays(29)->toDateString(), $today, 'Last 30 days', '30d'],
            'month' => [now($tz)->startOfMonth()->toDateString(), $today, 'This month', 'month'],
            '90d' => [now($tz)->subDays(89)->toDateString(), $today, 'Last 90 days', '90d'],
            'custom' => [$r->query('from', $today), $r->query('to', $today), 'Custom', 'custom'],
            default => [now($tz)->subDays(6)->toDateString(), $today, 'Last 7 days', '7d'],
        };
    }
}
