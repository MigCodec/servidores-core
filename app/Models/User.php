<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected ?array $credentialServerCache = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->relationLoaded('groups')
            ? $this->groups->contains('is_admin', true)
            : $this->groups()->where('is_admin', true)->exists();
    }

    public function manageableServerIds(): array
    {
        if ($this->isAdmin()) {
            return [];
        }

        $groupIds = $this->groups()->pluck('groups.id');

        return Group::query()
            ->with('servers:id')
            ->whereIn('id', $groupIds)
            ->get()
            ->flatMap(fn (Group $group) => $group->servers->pluck('id'))
            ->unique()
            ->values()
            ->all();
    }

    public function canAccessServer(Server $server): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $serverIds = $this->manageableServerIds();

        return in_array($server->id, $serverIds, true);
    }

    public function credentialServerIds(): array
    {
        if ($this->isAdmin()) {
            return [];
        }

        if ($this->credentialServerCache !== null) {
            return $this->credentialServerCache;
        }

        $groupIds = $this->groups()->pluck('groups.id')->all();

        if ($groupIds === []) {
            return $this->credentialServerCache = [];
        }

        $ids = DB::table('group_server')
            ->whereIn('group_id', $groupIds)
            ->where('can_view_credentials', true)
            ->pluck('server_id')
            ->unique()
            ->values()
            ->all();

        return $this->credentialServerCache = $ids;
    }

    public function canAccessServerCredentials(Server $server): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($server->id, $this->credentialServerIds(), true);
    }
}
