<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiDocsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('parking.api_docs'), 404);

        return $next($request);
    }
}
