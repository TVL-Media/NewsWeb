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

        App::setLocale(in_array($lang, Lang::SUPPORTED, true) ? $lang : 'en');

        return $next($request);
    }
}
