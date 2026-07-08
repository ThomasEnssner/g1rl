<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the application locale from the browser's Accept-Language header.
 */
class SetLocaleFromBrowser
{
    /**
     * The locales the application ships translations for. The first entry
     * doubles as the fallback when the browser prefers something else.
     *
     * @var list<string>
     */
    private const array SUPPORTED_LOCALES = ['en', 'de'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($request->getPreferredLanguage(self::SUPPORTED_LOCALES) ?? 'en');

        return $next($request);
    }
}
