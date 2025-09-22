<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyAttendance extends Model
{
    protected $table = 'daily_attendance';

    protected $fillable = [
        'person_code','first_name','last_name','full_name','group_name','photo_url',
        'date',
        'expected_in','expected_out',
        'in_actual','out_actual',
        'in_source','out_source',
        'in_source_provisional','out_source_provisional',
        'location_in','location_out',
        'late_minutes','early_leave_minutes','overtime_minutes',
        'raw_refs','source_updated_at',
    ];

    protected $casts = [
        'date'                 => 'date',
        'in_actual'            => 'datetime',
        'out_actual'           => 'datetime',
        'in_source_provisional'=> 'boolean',
        'out_source_provisional'=> 'boolean',
        'raw_refs'             => 'array',
        'source_updated_at'    => 'datetime',
    ];
}
