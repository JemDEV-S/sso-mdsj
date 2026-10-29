<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSession;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['dni' => ['required', 'regex:/^[0-9]{8}$/'], 'password' => ['required', 'string', 'max:1024']]);
        $key = 'login:'.hash('sha256', $data['dni']);
        if (RateLimiter::tooManyAttempts($key, config('sso.login_attempts'))) {
            throw ValidationException::withMessages(['dni' => 'Demasiados intentos. Intente nuevamente en '.RateLimiter::availableIn($key).' segundos.']);
        }
        $user = User::where('dni', $data['dni'])->first();
        // A valid dummy hash equalizes work for unknown identities.
        $valid = Hash::check($data['password'], $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $valid || ! $user?->active) {
            RateLimiter::hit($key, config('sso.lock_seconds'));
            Audit::record('login.failed', $user?->id);
            throw ValidationException::withMessages(['dni' => 'No se pudo iniciar sesión con las credenciales proporcionadas.']);
        }
        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();
        $session = UserSession::create([
            'user_id' => $user->id, 'session_identifier' => hash('sha256', $request->session()->getId()),
            'ip_address' => $request->ip(), 'user_agent' => substr($request->userAgent() ?? '', 0, 1000),
            'authenticated_at' => now(), 'last_activity_at' => now(),
            'expires_at' => now()->addHours(config('sso.session_hours')),
        ]);
        $request->session()->put('central_session_id', $session->id);
        $user->update(['last_login_at' => now()]);
        Audit::record('login.succeeded', $user->id);

        return $user->must_change_password ? redirect()->route('password.edit') : redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        UserSession::where('id', $request->session()->get('central_session_id'))->update(['revoked_at' => now()]);
        Audit::record('logout', $request->user()->id);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'La sesión central se ha cerrado.');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', 'max:72', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        DB::transaction(function () use ($request, $data) {
            $request->user()->update(['password' => $data['password'], 'must_change_password' => false]);
            UserSession::where('user_id', $request->user()->id)->where('id', '!=', $request->session()->get('central_session_id'))->update(['revoked_at' => now()]);
            DB::table('auth_codes')->where('user_id', $request->user()->id)->whereNull('used_at')->update(['used_at' => now()]);
            Audit::record('password.changed', $request->user()->id);
        });
        $request->session()->regenerate();
        UserSession::where('id', $request->session()->get('central_session_id'))->update(['session_identifier' => hash('sha256', $request->session()->getId())]);

        return redirect()->intended('/')->with('status', 'Contraseña actualizada.');
    }
}
