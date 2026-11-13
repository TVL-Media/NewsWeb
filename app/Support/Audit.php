<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/** Writes immutable audit records (plan §10.18, BR-12). */
class Audit
{
    public static function log(string $action, ?Model $subject = null, ?array $before = null, ?array $after = null, ?string $reason = null, ?string $label = null): void
    {
        $req = request();
        [$before, $after] = self::diff($before, $after);
        AuditLog::create([
            'admin_id' => auth('admin')->id(),
            'action' => $action,
            'module' => explode('.', $action)[0],
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $label ?? self::labelFor($subject),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip' => $req?->ip(),
            'user_agent' => mb_substr((string) $req?->userAgent(), 0, 500),
        ]);
    }

    /** Keep only changed fields so the log shows a field-level diff. */
    public static function diff(?array $before, ?array $after): array
    {
        if ($before === null || $after === null) {
            return [$before, $after];
        }
        $b = [];
        $a = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $k) {
            if (in_array($k, ['updated_at', 'created_at'], true)) {
                continue;
            }
            $bv = $before[$k] ?? null;
            $av = $after[$k] ?? null;
            if (json_encode($bv) !== json_encode($av)) {
                $b[$k] = $bv;
                $a[$k] = $av;
            }
        }

        return [$b, $a];
    }

    private static function labelFor(?Model $m): ?string
    {
        if (! $m) {
            return null;
        }
        if ($m instanceof \App\Models\Story) {
            return mb_substr($m->title(), 0, 250);
        }
        foreach (['name_en'] as $f) {
            if (isset($m->{$f}) && is_string($m->{$f})) {
                return mb_substr($m->{$f}, 0, 250);
            }
        }
        foreach (['title', 'name', 'question', 'key', 'email'] as $f) {
            if (isset($m->{$f}) && is_string($m->{$f})) {
                return mb_substr($m->{$f}, 0, 250);
            }
        }

        return class_basename($m).' #'.$m->getKey();
    }
}
