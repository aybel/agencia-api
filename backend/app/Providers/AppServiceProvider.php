<?php

namespace App\Providers;

use App\Repositories\BranchRepository;
use App\Repositories\BranchRepositoryInterface;
use App\Repositories\MenuItemRepository;
use App\Repositories\MenuItemRepositoryInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Interface => implementación de cada repository.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        MenuItemRepositoryInterface::class => MenuItemRepository::class,
        BranchRepositoryInterface::class => BranchRepository::class,
    ];

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
        // 5 intentos de login por minuto por combinación de email e IP (frena fuerza bruta).
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });
    }
}
