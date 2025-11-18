<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'name',
        'url',
        'port',
        'username',
        'password',
    ];

    protected $casts = [
        'password' => 'encrypted',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}
