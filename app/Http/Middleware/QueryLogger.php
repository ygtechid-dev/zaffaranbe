<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryLogger
{
    public function handle($request, Closure $next)
    {
        DB::enableQueryLog();
        $response = $next($request);
        Log::info(DB::getQueryLog());
        return $response;
    }
}
