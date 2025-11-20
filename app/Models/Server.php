<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Server extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'ip_address',
        'ram_gb',
        'storage_gb',
        'is_physical',
        'parent_id',
        'os_name',
        'os_version',
        'kernel_version',
        'cpu_cores',
        'owner',
        'environment',
        'location',
        'critical_services',
        'in_maintenance',
    ];

    protected $casts = [
        'is_physical' => 'boolean',
        'cpu_cores' => 'integer',
        'critical_services' => 'array',
        'in_maintenance' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Server::class, 'parent_id')->withTrashed();
    }

    public function virtualMachines()
    {
        return $this->hasMany(Server::class, 'parent_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class)
            ->withTimestamps()
            ->withPivot('can_view_credentials');
    }

    public function healthLogs()
    {
        return $this->hasMany(ServerHealthLog::class);
    }

    public function passwordLogs()
    {
        return $this->hasMany(ServerPasswordLog::class);
    }

    public function getSshServiceAttribute()
    {
        if ($this->relationLoaded('services')) {
            $service = $this->services->firstWhere('is_ssh', true);
            if ($service) {
                return $service;
            }
        } else {
            $service = $this->services()->where('is_ssh', true)->first();
            if ($service) {
                return $service;
            }
        }

        $legacyUsername = $this->getAttribute('ssh_username');
        if ($legacyUsername) {
            return (object) [
                'id' => null,
                'host' => $this->getAttribute('ssh_host') ?: $this->ip_address,
                'port' => $this->getAttribute('ssh_port') ?: 22,
                'username' => $legacyUsername,
                'password' => $this->getAttribute('ssh_password'),
                'is_ssh' => true,
                'passwordLogs' => collect(),
            ];
        }

        return null;
    }
}
