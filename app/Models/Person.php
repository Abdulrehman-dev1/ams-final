<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $fillable = [
        'person_id','person_code','group_id',
        'first_name','last_name','gender','phone','email','description','head_pic_url',
        'start_ms','end_ms','start_at','end_at',
    ];
}