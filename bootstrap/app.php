<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.acl'          => \Uzzal\Acl\Middleware\AuthenticateWithAcl::class,
            'resource.maker'    => \Uzzal\Acl\Middleware\ResourceMaker::class,
            'password.expiry'   => \App\Http\Middleware\PasswordExpiryCheck::class,
        ]);
    })
    ->withCommands([
        \Uzzal\Acl\Commands\AclResource::class,
        \App\Console\Commands\ExpireKvAssignments::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
