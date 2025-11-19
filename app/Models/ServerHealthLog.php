<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServerHealthLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'status',
        'latency_ms',
        'ssh_connected',
        'ram_usage_percent',
        'cpu_load1',
        'services_status',
    ];

    protected $casts = [
        'ssh_connected' => 'boolean',
        'ram_usage_percent' => 'float',
        'cpu_load1' => 'float',
        'services_status' => 'array',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}
