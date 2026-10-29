<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OidcController;
use App\Http\Middleware\CentralSession;
use App\Services\Tokens;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::view('/password', 'auth.password')->name('password.edit');
    Route::post('/password', [AuthController::class, 'password'])->middleware('throttle:6,1')->name('password.update');
    Route::get('/', function () {
        // Sistemas disponibles en el portal: clientes activos con URL de inicio.
        $systems = \App\Models\Client::query()
            ->where('active', true)
            ->whereNotNull('launch_url')
            ->orderBy('name')
            ->get(['code', 'name', 'description', 'launch_url']);

        return view('home', ['systems' => $systems]);
    })->middleware('password.changed')->name('home');
    Route::prefix('admin')->name('admin.')->middleware(['password.changed', 'admin'])->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::get('/users/create', [AdminController::class, 'userForm'])->name('users.create');
        Route::post('/users', [AdminController::class, 'saveUser'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminController::class, 'userForm'])->name('users.edit');
        Route::put('/users/{user}', [AdminController::class, 'saveUser'])->name('users.update');
        Route::get('/clients', [AdminController::class, 'clients'])->name('clients');
        Route::get('/clients/create', [AdminController::class, 'clientForm'])->name('clients.create');
        Route::post('/clients', [AdminController::class, 'saveClient'])->name('clients.store');
        Route::get('/clients/{client}/edit', [AdminController::class, 'clientForm'])->name('clients.edit');
        Route::put('/clients/{client}', [AdminController::class, 'saveClient'])->name('clients.update');
        Route::get('/sessions', [AdminController::class, 'sessions'])->name('sessions');
        Route::delete('/sessions/{session}', [AdminController::class, 'revoke'])->name('sessions.revoke');
    });
});
Route::get('/authorize', [OidcController::class, 'authorize'])->middleware('throttle:60,1')->name('authorize');
// Protocol endpoints do not create or consult browser sessions.
Route::withoutMiddleware([
    PreventRequestForgery::class,
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    CentralSession::class,
])->group(function () {
    Route::post('/token', [OidcController::class, 'token'])->middleware('throttle:60,1');
    Route::get('/userinfo', [OidcController::class, 'userinfo'])->middleware('throttle:120,1');
    Route::get('/jwks', fn (Tokens $tokens) => response()->json(['keys' => [$tokens->jwk()]]));
    Route::get('/.well-known/openid-configuration', [OidcController::class, 'discovery']);
});
