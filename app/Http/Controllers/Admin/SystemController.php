<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Install\Installer;
use App\Support\Install\Updater;
use Illuminate\Http\Request;

/** Admin → System update: apply a new version after its files were uploaded (see UPGRADE.md). */
class SystemController extends Controller
{
    public function update()
    {
        return view('admin.system.update', [
            'installed' => Updater::installedVersion(),
            'code' => Updater::codeVersion(),
            'pending' => Updater::pendingMigrations(),
            'needed' => Updater::needed(),
            'lock' => Installer::lockInfo() ?? [],
            'result' => session('update_result'),
        ]);
    }

    public function run(Request $request, Updater $updater)
    {
        $request->validate(['backup' => ['accepted']], ['backup.accepted' => 'Tick the box to confirm you have a backup.']);

        $result = $updater->run();
        Audit::log('system.update', null, ['version' => $result['from']], ['version' => $result['to'], 'ok' => $result['ok']], label: 'System update');

        return redirect()->route('admin.system.update')->with('update_result', $result)
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Update complete. You are on version '.$result['to'].'.' : 'The update stopped. See the steps below.');
    }
}
