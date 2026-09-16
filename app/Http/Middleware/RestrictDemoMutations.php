<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictDemoMutations
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || blank($user->demo_access_id) || ! $user->hasActiveDemoAccess()) {
            return $next($request);
        }

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $route = (string) ($request->route()?->getName() ?? '');
        if ($this->denied($route) || ! $this->allowed($route)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Mutasi ini tidak diizinkan pada akun demo.'], 403);
            }

            return abort(403, 'Mutasi ini tidak diizinkan pada akun demo.');
        }

        return $next($request);
    }

    private function allowed(string $route): bool
    {
        foreach ((array) config('demo.mutation_allow', []) as $rule) {
            $rule = (string) $rule;
            if ($rule !== '' && ($route === $rule || (str_ends_with($rule, '.') && str_starts_with($route, $rule)))) {
                return true;
            }
        }

        return false;
    }

    private function denied(string $route): bool
    {
        if (filter_var(config('demo.integrations_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        foreach ((array) config('demo.mutation_deny_prefixes', []) as $prefix) {
            if ($prefix !== '' && str_starts_with($route, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }
}
