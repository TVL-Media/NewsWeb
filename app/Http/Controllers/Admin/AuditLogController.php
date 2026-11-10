<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Support\Audit;
use Illuminate\Http\Request;

/** M18 Audit log (PRD §13, BR-12). Read-only. */
class AuditLogController extends Controller
{
    private function query(Request $r)
    {
        $q = AuditLog::with('admin')->latest('created_at')->latest('id');
        if ($a = $r->query('admin')) {
            $q->where('admin_id', $a);
        }
        if ($m = $r->query('module')) {
            $q->where('module', $m);
        }
        if ($s = $r->query('q')) {
            $q->where(fn ($w) => $w->where('action', 'like', "%{$s}%")->orWhere('subject_label', 'like', "%{$s}%")->orWhere('subject_id', $s));
        }
        if ($from = $r->query('from')) {
            $q->where('created_at', '>=', from_local_input($from.'T00:00'));
        }
        if ($to = $r->query('to')) {
            $q->where('created_at', '<=', from_local_input($to.'T23:59'));
        }

        return $q;
    }

    public function index(Request $r)
    {
        return view('admin.audit.index', ['items' => $this->query($r)->paginate(50)->withQueryString(), 'admins' => Admin::withTrashed()->orderBy('name')->get(['id', 'name']), 'modules' => AuditLog::distinct()->orderBy('module')->pluck('module')]);
    }

    public function show(AuditLog $log)
    {
        return view('admin.audit.show', ['log' => $log->load('admin')]);
    }

    public function export(Request $r)
    {
        Audit::log('audit.exported');

        return response()->streamDownload(function () use ($r) {
            $o = fopen('php://output', 'w');
            fputcsv($o, ['When ('.ptz().')', 'Admin', 'Action', 'Subject', 'Subject ID', 'Before', 'After', 'Reason', 'IP']);
            $this->query($r)->chunk(1000, function ($rows) use ($o) {
                foreach ($rows as $l) {
                    fputcsv($o, [$l->created_at->setTimezone(ptz())->toDateTimeString(), $l->admin?->name ?? 'System', $l->action, $l->subject_label, $l->subject_id, json_encode($l->before, JSON_UNESCAPED_UNICODE), json_encode($l->after, JSON_UNESCAPED_UNICODE), $l->reason, $l->ip]);
                }
            });
            fclose($o);
        }, 'audit-log.csv', ['Content-Type' => 'text/csv']);
    }
}
