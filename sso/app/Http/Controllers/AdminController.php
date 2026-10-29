<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Audit;
use App\Services\Tokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index', [
            'users' => User::count(), 'clients' => Client::where('active', true)->count(),
            'sessions' => UserSession::whereNull('revoked_at')->where('expires_at', '>', now())
                ->where('last_activity_at', '>', now()->subMinutes(config('session.lifetime')))->count(),
            'events' => DB::table('audit_logs')->latest('id')->limit(15)->get(),
        ]);
    }

    public function users(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = User::query();
        if ($q = $request->input('q')) {
            $query->where(fn ($query) => $query->where('dni', 'like', '%'.$q.'%')->orWhere('nombres', 'like', '%'.$q.'%')->orWhere('apellidos', 'like', '%'.$q.'%'));
        }

        return view('admin.users', ['users' => $query->orderBy('apellidos')->paginate(20)->withQueryString()]);
    }

    public function userForm(?User $user = null)
    {
        return view('admin.user-form', ['account' => $user ?? new User]);
    }

    public function saveUser(Request $request, ?User $user = null)
    {
        $creating = ! $user;
        $user ??= new User;
        $data = $request->validate([
            'dni' => ['required', 'regex:/^[0-9]{8}$/', Rule::unique('users')->ignore($user->id)],
            'nombres' => ['required', 'string', 'max:150'], 'apellidos' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => [$creating ? 'required' : 'nullable', 'confirmed', 'max:72', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'active' => ['required', 'boolean'],
        ]);
        if ($user->id === $request->user()->id && ! $data['active']) {
            throw ValidationException::withMessages(['active' => 'No puede bloquear su propia cuenta.']);
        }
        // Administrators are provisioned only by the protected CLI command.
        DB::transaction(function () use ($data, $user, $creating) {
            $reset = ! empty($data['password']);
            if (! $reset) {
                unset($data['password']);
            } else {
                $data['must_change_password'] = true;
            }
            $user->fill($data)->save();
            if (! $user->active || $reset) {
                UserSession::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                DB::table('auth_codes')->where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
            }
            Audit::record($creating ? 'user.created' : 'user.updated', $user->id);
            if ($reset) {
                Audit::record('password.reset', $user->id);
            }
        });

        return redirect()->route('admin.users')->with('status', 'Usuario guardado. Las contraseñas temporales deben cambiarse al ingresar.');
    }

    public function clients()
    {
        return view('admin.clients', ['clients' => Client::with('redirectUris')->orderBy('name')->paginate(20)]);
    }

    public function clientForm(?Client $client = null)
    {
        return view('admin.client-form', ['client' => $client ?? new Client]);
    }

    public function saveClient(Request $request, ?Client $client = null)
    {
        $creating = ! $client;
        $client ??= new Client;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'alpha_dash:ascii', 'max:80', Rule::unique('clients')->ignore($client->id)],
            'active' => ['required', 'boolean'], 'redirect_uris' => ['required', 'string', 'max:10000'],
        ]);
        $uris = array_values(array_unique(array_filter(array_map('trim', preg_split('/\r?\n/', $data['redirect_uris'])))));
        if (count($uris) < 1 || count($uris) > 10) {
            throw ValidationException::withMessages(['redirect_uris' => 'Registre entre 1 y 10 URL de retorno.']);
        }
        foreach ($uris as $uri) {
            $parts = parse_url($uri);
            $local = app()->environment(['local', 'testing']) && in_array($parts['host'] ?? '', ['localhost', '127.0.0.1'], true);
            if (! filter_var($uri, FILTER_VALIDATE_URL) || strlen($uri) > 2048 || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])
                || str_contains($uri, '*') || ! in_array($parts['scheme'] ?? '', $local ? ['https', 'http'] : ['https'], true)) {
                throw ValidationException::withMessages(['redirect_uris' => 'Use URL HTTPS completas, sin fragmentos, credenciales ni comodines. HTTP solo se permite en localhost durante desarrollo.']);
            }
        }
        unset($data['redirect_uris']);
        $secret = $creating ? Tokens::base64url(random_bytes(48)) : null;
        DB::transaction(function () use ($client, $data, $uris, $secret, $creating) {
            if ($creating) {
                $client->client_id = (string) Str::uuid();
                $client->client_secret_hash = Hash::make($secret);
            }
            $client->fill($data)->save();
            $client->redirectUris()->delete();
            foreach ($uris as $uri) {
                $client->redirectUris()->create(['redirect_uri' => $uri]);
            }
            DB::table('auth_codes')->where('client_id', $client->id)->whereNull('used_at')->update(['used_at' => now()]);
            Audit::record($creating ? 'client.created' : 'client.updated', null, $client->id);
        });
        // Render directly: secrets never enter flash data or browser URLs.
        if ($secret) {
            return response()->view('admin.client-secret', compact('client', 'secret'));
        }

        return redirect()->route('admin.clients')->with('status', 'Sistema actualizado.');
    }

    public function sessions()
    {
        return view('admin.sessions', ['sessions' => DB::table('user_sessions')->join('users', 'users.id', '=', 'user_sessions.user_id')
            ->select('user_sessions.*', 'users.nombres', 'users.apellidos')->orderByDesc('authenticated_at')->paginate(20)]);
    }

    public function revoke(UserSession $session)
    {
        $session->update(['revoked_at' => now()]);
        Audit::record('session.revoked', $session->user_id);

        return back()->with('status', 'Sesión central revocada.');
    }
}
