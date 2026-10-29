<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PasswordChanged
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->must_change_password) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
