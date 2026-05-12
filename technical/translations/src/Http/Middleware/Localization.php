<?php

namespace Technical\Translations\Http\Middleware;

use Closure;
use Functional\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class Localization
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $currentUser */
        $currentUser = auth()->user();

        $supportedLocales = config('translatable.locales');

        if (auth()->check()) {
            $locale = $currentUser->preferredLocale();
        } else {
            $locale = $request->getPreferredLanguage($supportedLocales);
        }

        $locale = Str::of($locale)->before('-')->lower();

        if (! in_array($locale, $supportedLocales)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
