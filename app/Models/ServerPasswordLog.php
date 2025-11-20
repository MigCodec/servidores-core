<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServerPasswordLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'recorded_by',
        'password',
    ];

    protected $casts = [
        'password' => 'encrypted',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
