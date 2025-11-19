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
        'ssh_host',
        'ssh_port',
        'ssh_username',
        'ssh_password',
        'os_name',
        'os_version',
        'kernel_version',
        'cpu_cores',
        'owner',
        'environment',
        'location',
        'critical_services',
    ];

    protected $casts = [
        'is_physical' => 'boolean',
        'ssh_port' => 'integer',
        'ssh_password' => 'encrypted',
        'cpu_cores' => 'integer',
        'critical_services' => 'array',
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
        return $this->belongsToMany(Group::class)->withTimestamps();
    }

    public function healthLogs()
    {
        return $this->hasMany(ServerHealthLog::class);
    }
}
