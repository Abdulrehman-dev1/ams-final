<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HccDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'name',
        'serial_no',
        'category',
        'raw',
    ];

    protected $casts = [
        'raw' => 'array',
    ];

    /**
     * Get attendance records for this device.
     */
    public function attendances()
    {
        return $this->hasMany(HccAttendanceTransaction::class, 'device_id', 'device_id');
    }
}

