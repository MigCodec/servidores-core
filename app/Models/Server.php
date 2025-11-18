<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ip_address',
        'ram_gb',
        'storage_gb',
        'is_physical',
        'parent_id',
    ];

    protected $casts = [
        'is_physical' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Server::class, 'parent_id');
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
}
