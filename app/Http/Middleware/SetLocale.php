<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * The frontend sends one language tag rather than a weighted list, so the
     * first two characters are the language. Anything we do not publish in is
     * left alone and reads English.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requested = substr((string) $request->header('Accept-Language'), 0, 2);

        if (in_array($requested, config('locales'), true)) {
            app()->setLocale($requested);
        }

        return $next($request);
    }
}
