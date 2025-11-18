<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'person_code',
        'date',
        'name',
        'department',
        'expected_in',
        'check_in',
        'expected_out',
        'check_out',
        'data_source',
        'location',
        'latitude',
        'longitude',
        'device_name',
        'device_serial',
        'device_id',
        'late_minutes',
        'overtime_minutes',
    ];

    protected $casts = [
        'date' => 'date',
        'expected_in' => 'datetime:H:i:s',
        'check_in' => 'datetime:H:i:s',
        'expected_out' => 'datetime:H:i:s',
        'check_out' => 'datetime:H:i:s',
    ];
}

