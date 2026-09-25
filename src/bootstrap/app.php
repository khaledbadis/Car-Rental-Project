<?php

use App\Http\Middleware\AccountAccess;
use App\Modules\Reservations\Conflict;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $m): void {
        $m->web(append: [AccountAccess::class]);
        $m->alias(['account' => AccountAccess::class]);
    })
    ->withExceptions(function (Exceptions $e): void {
        $e->render(function (Conflict $error, Request $request) {
            return response()->json(['message' => $error->getMessage(), 'code' => $error->errorCode, 'details' => $error->details], 409);
        });
        $e->render(function (App\Modules\Rentals\Conflict $error, Request $request) {
            return response()->json(['message' => $error->getMessage(), 'code' => $error->errorCode, 'details' => $error->details], 409);
        });
        $e->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson());
    })->create();
