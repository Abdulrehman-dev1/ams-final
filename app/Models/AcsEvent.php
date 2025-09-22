<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcsEvent extends Model
{
    protected $table = 'acs_events';

    protected $fillable = [
        'record_guid',
        'element_id','element_name','element_type',
        'area_id','area_name','device_id','device_name',
        'card_reader_id','card_reader_name',
        'dev_serial_no','event_type','event_main_type',
        'swipe_auth_result','direction','attendance_status','masks_status',
        'has_camera_snap_pic','has_dev_video_record',
        'card_number',
        'person_id','person_code','first_name','last_name','full_name','full_path','gender','email','phone','photo_url',
        'occur_time_utc','device_time_tz','record_time_utc',
        'occur_time_pk','occur_date_pk',
        'acs_snap_pics','temperature_info','associated_camera_list','raw_payload',
    ];

    protected $casts = [
        'occur_time_utc'     => 'datetime',
        'record_time_utc'    => 'datetime',
        'occur_time_pk'      => 'datetime',
        'acs_snap_pics'      => 'array',
        'temperature_info'   => 'array',
    ];

    // Helper: first snapshot URL
    public function getPrimarySnapUrlAttribute(): ?string
    {
        $list = $this->acs_snap_pics ?? [];
        if (is_array($list) && isset($list[0]['snapPicUrl'])) {
            return $list[0]['snapPicUrl'];
        }
        return null;
    }
}
