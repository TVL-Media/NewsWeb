<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminLoginLog;
use App\Models\AuditLog;
use App\Notifications\AdminInvitation;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/** M1 Admin team (PRD §2, §13). */
class AdminTeamController extends Controller
{
    use Concerns;

    public function index(Request $r)
    {
        $q = Admin::with('roles');
        if ($s = $r->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        if ($role = $r->query('role')) {
            $q->role($role);
        }
        if ($st = $r->query('status')) {
            $q->where('status', $st);
        }

        return view('admin.team.index', ['admins' => $q->orderBy('name')->paginate($this->perPage($r))->withQueryString(), 'roles' => Role::where('guard_name', 'admin')->orderBy('name')->get()]);
    }

    public function invite(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|unique:admins,email', 'roles' => 'required|array|min:1', 'roles.*' => 'exists:roles,name']);
        $token = Str::random(48);
        $a = Admin::create(['name' => $data['name'], 'email' => $data['email'], 'status' => 'invited', 'invite_token' => hash('sha256', $token), 'invite_expires_at' => now()->addHours(48), 'created_by' => auth('admin')->id()]);
        $a->syncRoles($data['roles']);
        $url = route('admin.invite', $token);
        $a->notify(new AdminInvitation($url, auth('admin')->user()->name));
        Audit::log('admin.invited', $a, null, ['roles' => $data['roles']]);

        return back()->with('ok', "Invitation sent to {$a->email}. The link expires in 48 hours.")->with('invite_url', $url);
    }

    public function resendInvite(Admin $admin)
    {
        abort_unless($admin->status === 'invited', 422);
        $token = Str::random(48);
        $admin->update(['invite_token' => hash('sha256', $token), 'invite_expires_at' => now()->addHours(48)]);
        $url = route('admin.invite', $token);
        $admin->notify(new AdminInvitation($url, auth('admin')->user()->name));

        return back()->with('ok', 'Invitation re-sent.')->with('invite_url', $url);
    }

    public function edit(Admin $admin)
    {
        $logins = AdminLoginLog::where('admin_id', $admin->id)->latest('created_at')->limit(15)->get();
        $actions = AuditLog::where('admin_id', $admin->id)->latest('created_at')->limit(15)->get();

        return view('admin.team.edit', ['a' => $admin->load('roles'), 'roles' => Role::where('guard_name', 'admin')->orderBy('name')->get(), 'logins' => $logins, 'actions' => $actions]);
    }

    public function update(Request $r, Admin $admin)
    {
        $me = auth('admin')->user();
        $data = $r->validate(['name' => 'required|string|max:100', 'email' => ['required', 'email', Rule::unique('admins', 'email')->ignore($admin->id)], 'roles' => 'required|array|min:1', 'roles.*' => 'exists:roles,name']);
        if ($admin->id === $me->id && collect($data['roles'])->sort()->values()->all() !== $admin->roles->pluck('name')->sort()->values()->all()) {
            return back()->with('error', 'You cannot change your own role.');
        }
        if ($admin->isSuperAdmin() && ! in_array('Super Admin', $data['roles'], true) && $this->superAdminCount() <= 1) {
            return back()->with('error', 'This is the last Super Admin. Make someone else Super Admin first.');
        }
        $before = ['name' => $admin->name, 'email' => $admin->email, 'roles' => $admin->roles->pluck('name')->all()];
        $admin->update(['name' => $data['name'], 'email' => $data['email']]);
        $admin->syncRoles($data['roles']);
        Audit::log('admin.updated', $admin, $before, ['name' => $admin->name, 'email' => $admin->email, 'roles' => $data['roles']]);

        return back()->with('ok', 'Admin updated. Permission changes apply on their next request.');
    }

    public function toggle(Admin $admin)
    {
        if ($admin->id === auth('admin')->id()) {
            return back()->with('error', 'You cannot deactivate yourself.');
        }
        if ($admin->isSuperAdmin() && $admin->status === 'active' && $this->superAdminCount() <= 1) {
            return back()->with('error', 'The last Super Admin cannot be deactivated.');
        }
        $to = $admin->status === 'active' ? 'inactive' : 'active';
        $admin->update(['status' => $to]);
        if ($to === 'inactive') {
            DB::table('sessions')->where('user_id', $admin->id)->delete();
        }
        Audit::log('admin.'.($to === 'active' ? 'activated' : 'deactivated'), $admin);

        return back()->with('ok', "{$admin->name} is now {$to}.");
    }

    public function resetTwoFactor(Admin $admin)
    {
        $admin->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        Audit::log('admin.2fa_reset', $admin);

        return back()->with('ok', 'Two-factor reset. They will be asked to set it up again.');
    }

    private function superAdminCount(): int
    {
        return Admin::role('Super Admin')->where('status', 'active')->count();
    }
}
