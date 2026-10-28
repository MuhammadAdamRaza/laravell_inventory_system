<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Guard as SanctumGuard;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTabToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            throw new AuthenticationException('Unauthenticated.', ['sanctum']);
        }

        $configuredGuards = config('sanctum.guard', ['web']);
        config(['sanctum.guard' => []]);

        try {
            $user = (new SanctumGuard(
                Auth::getFacadeRoot(),
                config('sanctum.expiration'),
                config('auth.guards.sanctum.provider'),
                config('sanctum.last_used_at', true),
            ))($request);
        } finally {
            config(['sanctum.guard' => $configuredGuards]);
        }

        if (! $user instanceof User || ! $user->is_active) {
            throw new AuthenticationException('Unauthenticated.', ['sanctum']);
        }

        $request->setUserResolver(static fn (): User => $user);
        Auth::guard('sanctum')->setUser($user);
        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
