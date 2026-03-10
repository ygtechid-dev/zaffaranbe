<?php

namespace App\Http\Middleware;

use Closure;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        \Illuminate\Support\Facades\Log::info("CorsMiddleware: Handle request for " . $request->fullUrl() . " from " . ($request->header('Origin') ?: 'No Origin'));

        $origin = $request->header('Origin');
        $isLocalhost = $origin && (str_contains($origin, 'localhost') || str_contains($origin, '127.0.0.1'));

        $headers = [
            'Access-Control-Allow-Origin' => $origin ?: ($isLocalhost ? $origin : '*'),
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS, PUT, DELETE',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept'
        ];

        // Ensure we don't send * with credentials
        if ($headers['Access-Control-Allow-Origin'] === '*' && $headers['Access-Control-Allow-Credentials'] === 'true') {
            $headers['Access-Control-Allow-Credentials'] = 'false';
        }

        if ($request->isMethod('OPTIONS')) {
            return response()->json('{"method":"OPTIONS"}', 200, $headers);
        }

        $response = $next($request);

        // Handle response that isn't an Illuminate Response (e.g. Symfony Response)
        if (method_exists($response, 'header')) {
            foreach ($headers as $key => $value) {
                $response->header($key, $value);
            }
        } elseif (property_exists($response, 'headers') && method_exists($response->headers, 'set')) {
            foreach ($headers as $key => $value) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }
}
