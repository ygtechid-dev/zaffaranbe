<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BasicAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            return response()->json([
                'success' => false,
                'message' => 'Missing or invalid Authorization header'
            ], 401);
        }

        $credentials = base64_decode(substr($authHeader, 6));
        [$username, $password] = explode(':', $credentials, 2);

        // Get credentials from environment
        $validUsername = env('OPENAPI_USERNAME', 'openapi_user');
        $validPassword = env('OPENAPI_PASSWORD', 'openapi_secret');

        if ($username !== $validUsername || $password !== $validPassword) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        return $next($request);
    }
}
