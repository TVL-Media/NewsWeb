<?php

namespace App\Http\Middleware;

use App\Support\Lang;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->user()?->language ?? $request->cookie('lang');

        // New visitors get the site's default language (Admin → Settings → General).
        $default = in_array(config('app.locale'), Lang::SUPPORTED, true) ? config('app.locale') : 'en';
        App::setLocale(in_array($lang, Lang::SUPPORTED, true) ? $lang : $default);

        return $next($request);
    }
}
