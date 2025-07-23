<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'fixed_ip',
        'sim_number',
        'gsm_signal',
        'gnss_location',
    ];

    public function devices()
    {
        return $this->hasMany(Device::class);
    }
}
