<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** M1 Roles & permissions editor (PRD §2.2). */
class RoleController extends Controller
{
    public const SYSTEM = ['Super Admin', 'Chief Editor', 'Editor', 'Writer', 'Community Moderator', 'Analyst'];

    public function index()
    {
        $roles = Role::where('guard_name', 'admin')->withCount(['permissions', 'users'])->orderBy('id')->get();

        return view('admin.roles.index', ['roles' => $roles, 'groups' => PermissionSeeder::PERMISSIONS]);
    }

    public function store(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:60|unique:roles,name', 'copy_from' => 'nullable|exists:roles,id']);
        $role = Role::create(['name' => $d['name'], 'guard_name' => 'admin']);
        if (! empty($d['copy_from'])) {
            $role->syncPermissions(Role::find($d['copy_from'])->permissions);
        }
        Audit::log('role.created', $role);

        return redirect()->route('admin.roles.edit', $role)->with('ok', 'Role created. Choose its permissions.');
    }

    public function edit(Role $role)
    {
        return view('admin.roles.edit', ['role' => $role->load('permissions'), 'groups' => PermissionSeeder::PERMISSIONS, 'system' => in_array($role->name, self::SYSTEM, true)]);
    }

    public function update(Request $r, Role $role)
    {
        if ($role->name === 'Super Admin') {
            return back()->with('error', 'Super Admin always has every permission.');
        }
        if (auth('admin')->user()->hasRole($role->name) && ! auth('admin')->user()->isSuperAdmin()) {
            return back()->with('error', 'You cannot change the permissions of your own role.');
        }
        $perms = $r->validate(['permissions' => 'nullable|array', 'permissions.*' => 'exists:permissions,name'])['permissions'] ?? [];
        $before = $role->permissions->pluck('name')->sort()->values()->all();
        $role->syncPermissions($perms);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $added = array_values(array_diff($perms, $before));
        $removed = array_values(array_diff($before, $perms));
        Audit::log('role.permissions_changed', $role, ['removed' => $removed], ['added' => $added]);

        return back()->with('ok', 'Permissions saved: '.count($added).' added, '.count($removed).' removed.');
    }

    public function destroy(Role $role)
    {
        if (in_array($role->name, self::SYSTEM, true)) {
            return back()->with('error', 'Built-in roles cannot be deleted.');
        }
        if ($role->users()->exists()) {
            return back()->with('error', 'Move the admins in this role to another role first.');
        }
        $role->delete();
        Audit::log('role.deleted', null, null, null, null, $role->name);

        return redirect()->route('admin.roles.index')->with('ok', 'Role deleted.');
    }
}
