<?php

use App\Admin\Services\TenantErrorContext;
use App\Admin\Services\TenantErrorLogService;
use App\Console\Commands\DispatchScheduledReportsCommand;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Central\Tenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command(DispatchScheduledReportsCommand::class)
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Record exceptions that occur in a tenant request context.
        // Stores the generated error code in TenantErrorContext so the
        // renderable callback below can surface it to the user.
        $exceptions->reportable(function (Throwable $e): void {
            $request = request();

            /** @var Tenant|null $tenant */
            $tenant = $request->attributes->get('current_tenant');

            if ($tenant === null || $e instanceof HttpException) {
                return;
            }

            try {
                /** @var TenantErrorLogService $service */
                $service = app(TenantErrorLogService::class);
                $log = $service->record($e, $tenant, $request);
                TenantErrorContext::set($log->errorCode);
            } catch (Throwable) {
                // Never let error logging itself bring down the request.
            }
        });

        // In production, replace the raw exception with a support-friendly
        // error code that the user can quote when contacting support.
        $exceptions->renderable(function (Throwable $e, Request $request) {
            $tenant = $request->attributes->get('current_tenant');

            if ($tenant === null || $e instanceof HttpException || ! app()->isProduction()) {
                return null;
            }

            $errorCode = TenantErrorContext::get() ?? 'UNKNOWN';

            TenantErrorContext::clear();

            $body = [
                'error_code' => $errorCode,
                'message' => 'An unexpected error occurred. Quote the error code when contacting support.',
            ];

            if ($request->is('api/*')) {
                return response()->json($body, Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->view('errors.tenant', $body, Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    })->create();
