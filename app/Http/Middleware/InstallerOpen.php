<?php

namespace App\Http\Middleware;

use App\Support\Install\Installer;
use Closure;
use Illuminate\Http\Request;

/** /install only exists before installation. Afterwards it is a plain 404. */
class InstallerOpen
{
    public function handle(Request $request, Closure $next)
    {
        abort_if(Installer::isInstalled(), 404);

        return $next($request);
    }
}
