<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJson
{
    // Makes every /api request get JSON answers (validation errors, 401s...)
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
