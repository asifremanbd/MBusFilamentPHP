<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reading extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'register_id',
        'value',
        'timestamp',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'timestamp' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function register()
    {
        return $this->belongsTo(Register::class);
    }
}
