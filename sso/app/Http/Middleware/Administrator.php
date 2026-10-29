<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Administrator
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->is_admin, 403, 'Acceso reservado a administradores.');

        return $next($request);
    }
}
