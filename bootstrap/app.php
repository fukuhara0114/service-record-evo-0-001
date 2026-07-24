<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectTo(
            guests: '/login', // 未ログイン時のリダイレクト先
            users: '/home'        // ログイン済みなのにログインページを開こうとしたとき
        );

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => !$request->header('X-Inertia') && (
                $request->is('api/*')
                || $request->is('servicerecord/attachments/*')
                || $request->is('servicerecord/notes')
                || $request->is('servicerecord/notes/*')
                || $request->is('servicerecord/files')
                || $request->is('servicerecord/files/*')
                || $request->is('servicerecords/*/attachments')
                || $request->expectsJson()
                || $request->ajax()
            ),
        );
    })->create();




