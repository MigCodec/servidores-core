<?php

namespace App\Providers;

use App\Models\Group;
use App\Models\Service;
use App\Models\Server;
use App\Models\User;
use App\Policies\GroupPolicy;
use App\Policies\ServicePolicy;
use App\Policies\ServerPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application authentication / authorization services.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Server::class => ServerPolicy::class,
        Service::class => ServicePolicy::class,
        Group::class => GroupPolicy::class,
    ];

    /**
     * Bootstrap any application authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function (User $user) {
            return $user->isAdmin() ? true : null;
        });
    }
}
