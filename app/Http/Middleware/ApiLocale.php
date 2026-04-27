<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get locale from header, query parameter, or default
        $locale = $request->header('Accept-Language')
            ?? $request->get('lang')
            ?? $request->get('locale')
            ?? config('app.locale');
        // Extract language code if full locale is provided (e.g., 'ar-SA' -> 'ar')
        $locale = substr($locale, 0, 2);

        // Validate locale
        if (!in_array($locale, config('app.available_locales', ['ar', 'en','it']))) {
            $locale = config('app.locale');
        }
        // Set application locale
        app()->setLocale($locale);
        return $next($request);
    }
}
