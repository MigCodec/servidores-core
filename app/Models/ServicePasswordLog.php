<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePasswordLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'recorded_by',
        'password',
    ];

    protected $casts = [
        'password' => 'encrypted',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
