<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyEmployee extends Model
{
    protected $table = 'daily_employees';

    protected $fillable = [
        'person_id','group_id','first_name','last_name','full_name','gender',
        'phone','email','person_code','description','start_date','end_date',
        'head_pic_url','group_name','raw_payload','is_enabled','base_salary',
        'latitude','longitude','time_in','time_out',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date'   => 'datetime',
        'raw_payload'=> 'array',
        'is_enabled' => 'boolean',
        'latitude'   => 'decimal:8',
        'longitude'  => 'decimal:8',
        'base_salary'=> 'decimal:2',
    ];
    
    protected $attributes = [
        'is_enabled' => true,
    ];
    
    // Accessor for time_in with default
    public function getTimeInAttribute($value)
    {
        return $value ?: '09:00:00';
    }
    
    // Accessor for time_out with default
    public function getTimeOutAttribute($value)
    {
        return $value ?: '19:00:00';
    }
    
    // Helper to get late cutoff (time_in + 15 minutes)
    public function getLateCutoffAttribute()
    {
        $timeIn = Carbon::parse($this->time_in);
        return $timeIn->copy()->addMinutes(15)->format('H:i:s');
    }

    // convenience accessor
    public function getDisplayNameAttribute(): string {
        return $this->full_name ?: trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }
}
