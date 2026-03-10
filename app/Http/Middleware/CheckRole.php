<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!in_array($user->role, $roles)) {
            \Illuminate\Support\Facades\Log::info("CheckRole Failed: User Role: {$user->role} vs Allowed: " . implode(',', $roles));
            return response()->json(['error' => 'Forbidden: You do not have permission to access this resource'], 403);
        }

        return $next($request);
    }
}
