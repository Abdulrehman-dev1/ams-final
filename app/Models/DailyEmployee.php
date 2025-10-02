<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyEmployee extends Model
{
    protected $table = 'daily_employees';

    protected $fillable = [
        'person_id','group_id','first_name','last_name','full_name','gender',
        'phone','email','person_code','description','start_date','end_date',
        'head_pic_url','group_name','raw_payload',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date'   => 'datetime',
        'raw_payload'=> 'array',
    ];

    // convenience accessor
    public function getDisplayNameAttribute(): string {
        return $this->full_name ?: trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }
}
