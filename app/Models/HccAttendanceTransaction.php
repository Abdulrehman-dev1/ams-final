<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HccAttendanceTransaction extends Model
{
    use HasFactory;

    protected $table = 'hcc_attendance_transactions';

    protected $fillable = [
        'person_code',
        'full_name',
        'department',
        'attendance_date',
        'attendance_time',
        'device_id',
        'device_name',
        'device_serial',
        'weekday',
        'source_data',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'source_data' => 'array',
    ];

    /**
     * Get the device associated with this attendance record.
     */
    public function device()
    {
        return $this->belongsTo(HccDevice::class, 'device_id', 'device_id');
    }
}
