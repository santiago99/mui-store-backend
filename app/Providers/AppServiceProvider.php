<?php

namespace App\Providers;

use App\Contracts\ProductSearchEngine;
use App\Services\EloquentProductSearchEngine;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProductSearchEngine::class, EloquentProductSearchEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        if (env('LOG_DATABASE_QUERIES', false)) {
            DB::listen(function ($query) {
                \Illuminate\Support\Facades\Log::debug($query->sql, $query->bindings);
            });
        }
    }
}
