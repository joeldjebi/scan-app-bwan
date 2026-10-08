<?php

use App\Http\Middleware\AjaxRedirects;
use App\Http\Middleware\EnsureApiDocsEnabled;
use App\Http\Middleware\EnsureUserIsActive;
use App\Services\AuditLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'api-docs' => EnsureApiDocsEnabled::class,
        ]);

        $middleware->web(append: [
            AjaxRedirects::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (in_array($exception->getStatusCode(), [403, 429], true)) {
                app(AuditLogger::class)->record(
                    $exception->getStatusCode() === 403 ? 'access.denied' : 'access.throttled',
                    $exception->getStatusCode() === 403
                        ? 'Accès refusé à '.$request->method().' /'.$request->path()
                        : 'Trop de tentatives sur '.$request->method().' /'.$request->path(),
                    properties: ['message' => $exception->getMessage() ?: null],
                );
            }

            return null;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
