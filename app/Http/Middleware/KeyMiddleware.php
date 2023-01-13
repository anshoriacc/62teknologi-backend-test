<?php

namespace App\Http\Middleware;

use Closure;
use App\Helper\Formatter;

class KeyMiddleware
{
    public function handle($request, Closure $next)
    {
        if ($request->header('x-access-key') !== '62tech') {
            return Formatter::response(401, "Unauthorized", null, "Access key empty or invalid.");
        }

        return $next($request);
    }
}
