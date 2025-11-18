<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function view(User $user, Service $service): bool
    {
        return $user->canAccessServer($service->server);
    }

    public function create(User $user, Server $server): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Service $service): bool
    {
        return $user->canAccessServer($service->server);
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->isAdmin();
    }
}
