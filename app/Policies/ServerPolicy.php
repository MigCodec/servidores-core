<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;

class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Server $server): bool
    {
        return $user->canAccessServer($server);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Server $server): bool
    {
        return $user->canAccessServer($server);
    }

    public function delete(User $user, Server $server): bool
    {
        return $user->isAdmin();
    }

    public function viewCredentials(User $user, Server $server): bool
    {
        return $user->canAccessServerCredentials($server);
    }
}
