<?php

namespace App\Providers;

use App\Events\StockLevelChanged;
use App\Listeners\BumpInventoryVersion;
use App\Listeners\EvaluateStockAlert;
use App\Listeners\InvalidateUnreadNotificationCount;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(StockLevelChanged::class, BumpInventoryVersion::class);
        Event::listen(StockLevelChanged::class, EvaluateStockAlert::class);
        Event::listen(NotificationSent::class, InvalidateUnreadNotificationCount::class);

        DevCommands::artisan('queue:listen --tries=1 --timeout=0 --queue=notifications,default,reports', 'queue');

        // Strict model checks: prevents N+1 queries, prevents silent attribute discarding
        Model::shouldBeStrict(! $this->app->isProduction());

        ResetPassword::createUrlUsing(static function (User $user, string $token): string {
            return rtrim((string) config('app.url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        // API Rate Limiters for brute-force protection
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by($email.'|'.$request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many login attempts. Please wait 60 seconds before trying again.',
                ], 429);
            });
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });
    }
}
