<?php

use App\Http\Middleware\Administrator;
use App\Http\Middleware\CentralSession;
use App\Http\Middleware\PasswordChanged;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [CentralSession::class]);
        $middleware->appendToPriorityList(StartSession::class, CentralSession::class);
        $middleware->alias([
            'admin' => Administrator::class,
            'password.changed' => PasswordChanged::class,
        ]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
        $middleware->preventRequestForgery(except: ['token']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
