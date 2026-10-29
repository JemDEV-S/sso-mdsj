<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CentralSession
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $request->user()->refresh();
            $session = UserSession::find($request->session()->get('central_session_id'));
            if (! $request->user()->active || ! $session || ! $session->isValid()
                || $session->user_id !== $request->user()->id
                || ! hash_equals($session->session_identifier, hash('sha256', $request->session()->getId()))) {
                if ($session) {
                    $session->update(['revoked_at' => now()]);
                }
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            } else {
                $session->update(['last_activity_at' => now()]);
                $request->attributes->set('central_session', $session);
            }
        }

        return $next($request);
    }
}
