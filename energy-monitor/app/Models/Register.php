<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Register extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'parameter_name',
        'register_address',
        'data_type',
        'unit',
        'scale',
        'normal_range',
        'critical',
        'notes',
    ];

    protected $casts = [
        'critical' => 'boolean',
        'scale' => 'decimal:4',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function readings()
    {
        return $this->hasMany(Reading::class);
    }
}
