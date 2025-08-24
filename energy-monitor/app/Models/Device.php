<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slave_id',
        'location_tag',
        'gateway_id',
        'manufacturer',
        'part_number',
        'serial_number',
        'notes',
    ];

    public function gateway()
    {
        return $this->belongsTo(Gateway::class);
    }

    public function registers()
    {
        return $this->hasMany(Register::class);
    }

    public function readings()
    {
        return $this->hasMany(Reading::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }
}
