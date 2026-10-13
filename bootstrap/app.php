<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AdminMiddleware;
use App\Providers\RepositoryServiceProvider;


return Application::configure(basePath: dirname(__DIR__))
    ->withEvents()
    ->withProviders([
        RepositoryServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', 'auth', 'Admin'])
                ->prefix('admin')
                ->group(base_path('routes/backend.php'));
            Route::middleware(['web', 'auth', 'Admin'])
                ->prefix('admin/settings')
                ->group(base_path('routes/settings.php'));

            // Public Media is served directly via the public/storage symlink.
            // Files stored in storage/app/public/ are accessible at /storage/...
            // through Laravel's default Storage::url() helper.
            // No custom media serving controller is needed.
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'Admin' => AdminMiddleware::class,
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
        
        $middleware->appendToGroup('api', \App\Http\Middleware\CurrencyMiddleware::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\LanguageMiddleware::class);

        // Admin routes are already protected by 'auth' + 'Admin' middleware,
        // so CSRF would be redundant and can cause token mismatch issues.
        $middleware->validateCsrfTokens(except: [
            'admin/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                $apiResponse = new class {
                    use \App\Traits\ApiResponse;
                };

                if ($e instanceof ValidationException) {
                    return $apiResponse->error($e->getMessage(), 422, $e->errors());
                }

                if ($e instanceof ModelNotFoundException) {
                    return $apiResponse->error($e->getMessage(), 404);
                }

                if ($e instanceof AuthenticationException) {
                    return $apiResponse->error($e->getMessage(), 401);
                }

                if ($e instanceof AuthorizationException) {
                    return $apiResponse->error($e->getMessage(), 403);
                }

                // Dynamically determine the status code if available
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                return $apiResponse->error($e->getMessage(), $statusCode);
            } else {
                return null;
            }
        });
    })->create();
