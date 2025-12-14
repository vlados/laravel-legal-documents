<?php

namespace Vlados\LegalDocuments\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegalDocumentsAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip if not authenticated
        if (! $user) {
            return $next($request);
        }

        // Skip if user doesn't have the trait
        if (! method_exists($user, 'needsToAcceptDocuments')) {
            return $next($request);
        }

        // Skip excluded routes
        if ($this->isExcludedRoute($request)) {
            return $next($request);
        }

        // Check if user needs to accept documents
        if ($user->needsToAcceptDocuments()) {
            $acceptanceRoute = config('legal-documents.acceptance_route', 'legal.accept');

            if (Route::has($acceptanceRoute)) {
                return redirect()->route($acceptanceRoute);
            }
        }

        return $next($request);
    }

    protected function isExcludedRoute(Request $request): bool
    {
        $excludedRoutes = config('legal-documents.excluded_routes', []);
        $currentRouteName = $request->route()?->getName();

        if (! $currentRouteName) {
            return false;
        }

        foreach ($excludedRoutes as $pattern) {
            if ($this->routeMatches($currentRouteName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function routeMatches(string $routeName, string $pattern): bool
    {
        // Exact match
        if ($routeName === $pattern) {
            return true;
        }

        // Wildcard match (e.g., 'legal.*')
        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim($pattern, '*');

            return str_starts_with($routeName, $prefix);
        }

        return false;
    }
}
