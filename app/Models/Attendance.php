<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendances';

    // Easiest fix: allow mass assignment for all columns except id
    protected $guarded = ['id'];

    // Optional but helpful: type casts (dates as Carbon)
    protected $casts = [
        'attendance_date' => 'date',
        'check_in_date'   => 'date',
        'check_out_date'  => 'date',
        'clock_in_date'   => 'date',
        'clock_out_date'  => 'date',
        // times remain strings (Laravel native "time" cast nahi deta)
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'emp_id');
    }
}
