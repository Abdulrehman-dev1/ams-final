<?php

namespace App\Http\Controllers;

use App\Models\AcsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AcsEventSyncController extends Controller
{
    public function sync(Request $request)
    {
        // ---- Inputs (optional) ----
        $validated = $request->validate([
            'from'      => 'nullable|date',
            'to'        => 'nullable|date|after_or_equal:from',
            'pageSize'  => 'nullable|integer|min:1|max:500',
            'pageIndex' => 'nullable|integer|min:1',
            'maxPages'  => 'nullable|integer|min:1|max:100',
        ]);

        $from      = isset($validated['from']) ? Carbon::parse($validated['from']) : now()->subDay();
        $to        = isset($validated['to'])   ? Carbon::parse($validated['to'])   : now();
        $pageSize  = $validated['pageSize']  ?? 100;
        $pageIndex = $validated['pageIndex'] ?? 1;
        $maxPages  = $validated['maxPages']  ?? 5;

        // Env (already working in your attendance flow)
        $baseUrl = rtrim(config('services.hik.base_url', env('HIK_BASE_URL')), '/');
          $token = $request->header('X-HIK-TOKEN')
        ?: $request->bearerToken()
        ?: $request->input('token')
        ?: config('services.hik.token');

    if (!$token) {
        return response()->json(['ok' => false, 'message' => 'Missing Hik token'], 422);
    }

        // Hik endpoint
        $url = $baseUrl . '/acs/v1/event/certificaterecords/search';

        $inserted = 0;
        $updated  = 0;
        $fetched  = 0;
        $pagesDone = 0;

        $http = Http::acceptJson()
    ->timeout(30)
    ->withHeaders([
        'Token'       => $token,            // <-- required by this endpoint
        'Content-Type'=> 'application/json'
    ]);


        // Loop pages
        for ($p = 0; $p < $maxPages; $p++) {
            $payload = [
                'pageIndex' => $pageIndex,
                'pageSize'  => $pageSize,
                'condition' => [
                    // Time filter on occurTime (if backend supports; otherwise omit)
                    'startTime' => $from->toIso8601ZuluString(),
                    'endTime'   => $to->toIso8601ZuluString(),
                ],
            ];

            $resp = $http->retry(2, 500)->post($url, $payload);

            if (!$resp->ok()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Hik request failed',
                    'status' => $resp->status(),
                    'body' => $resp->json(),
                    'payload' => $payload,
                ], 502);
            }

            $json = $resp->json();

            // Expect shape: { data: { totalNum, pageIndex, pageSize, recordList: [...] }, errorCode? }
            if (!isset($json['data']['recordList']) || !is_array($json['data']['recordList'])) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Unexpected response shape: recordList missing',
                    'body' => $json,
                ], 500);
            }

            $records   = $json['data']['recordList'];
            $totalNum  = $json['data']['totalNum'] ?? null;
            $thisCount = count($records);
            $fetched  += $thisCount;

            // Map -> rows
            $rows = [];
            foreach ($records as $r) {
                $guid = $r['recordGuid'] ?? null;
                if (!$guid) continue;

                $occurUtc   = isset($r['occurTime'])  ? Carbon::parse($r['occurTime'])->utc()  : null;
                $recordUtc  = isset($r['recordTime']) ? Carbon::parse($r['recordTime'])->utc() : null;
                $deviceTime = $r['deviceTime'] ?? null;

                // Derive Asia/Karachi
                $occurPk = $occurUtc ? $occurUtc->copy()->setTimezone('Asia/Karachi') : null;
                $occurDatePk = $occurPk ? $occurPk->toDateString() : null;

                $baseInfo = $r['personInfo']['baseInfo'] ?? [];
                $snapList = $r['acsSnapPicList'] ?? null;
                $tempInfo = $r['temperatureInfo'] ?? null;

                $rows[] = [
                    'record_guid'          => $guid,

                    'element_id'           => $r['elementId']   ?? null,
                    'element_name'         => $r['elementName'] ?? null,
                    'element_type'         => $r['elementType'] ?? null,
                    'area_id'              => $r['areaId']      ?? null,
                    'area_name'            => $r['areaName']    ?? null,
                    'device_id'            => $r['deviceId']    ?? null,
                    'device_name'          => $r['deviceName']  ?? null,
                    'card_reader_id'       => $r['cardReaderId']   ?? null,
                    'card_reader_name'     => $r['cardReaderName'] ?? null,
                    'dev_serial_no'        => $r['devSerialNo'] ?? null,

                    'event_type'           => $r['eventType'] ?? null,
                    'event_main_type'      => $r['eventMainType'] ?? null,
                    'swipe_auth_result'    => $r['swipeAuthResult'] ?? null,
                    'direction'            => $r['direction'] ?? null,
                    'attendance_status'    => $r['attendanceStatus'] ?? null,
                    'masks_status'         => $r['masksStatus'] ?? null,
                    'has_camera_snap_pic'  => $r['hasCameraSnapPic'] ?? null,
                    'has_dev_video_record' => $r['hasDevVideoRecord'] ?? null,

                    'card_number'          => isset($r['cardNumber']) ? (string)$r['cardNumber'] : null,

                    'person_id'            => $r['personInfo']['id'] ?? null,
                    'person_code'          => $baseInfo['personCode'] ?? null,
                    'first_name'           => $baseInfo['firstName'] ?? null,
                    'last_name'            => $baseInfo['lastName'] ?? null,
                    'full_name'            => $baseInfo['fullName'] ?? null,
                    'full_path'            => $baseInfo['fullPath'] ?? null,
                    'gender'               => $baseInfo['gender'] ?? null,
                    'email'                => $baseInfo['email'] ?? null,
                    'phone'                => $baseInfo['phoneNum'] ?? null,
                    'photo_url'            => $baseInfo['photoUrl'] ?? null,

                    'occur_time_utc'       => $occurUtc,
                    'device_time_tz'       => $deviceTime,
                    'record_time_utc'      => $recordUtc,

                    'occur_time_pk'        => $occurPk,
                    'occur_date_pk'        => $occurDatePk,

                   'acs_snap_pics'    => is_array($snapList) ? json_encode($snapList, JSON_UNESCAPED_UNICODE) : ($snapList ?? null),
                   'temperature_info' => is_array($tempInfo) ? json_encode($tempInfo, JSON_UNESCAPED_UNICODE) : ($tempInfo ?? null),
                    'associated_camera_list'=> is_array($r['associatedCameraList'] ?? null)
                                                ? json_encode($r['associatedCameraList'])
                                                : ($r['associatedCameraList'] ?? null),

                    'raw_payload'          => json_encode($r),
                    'updated_at'           => now(),
                    'created_at'           => now(),
                ];
            }

           if (!empty($rows)) {
    $upsertCols = array_keys($rows[0]);
    $updateCols = array_values(array_diff($upsertCols, ['record_guid', 'created_at']));

    \DB::table('acs_events')->upsert($rows, ['record_guid'], $updateCols);

    $inserted += count($rows);
}
            $pagesDone++;

            // If last page (based on totalNum and pagination math)
            if ($totalNum !== null) {
                $processedSoFar = $pageIndex * $pageSize;
                if ($processedSoFar >= $totalNum || $thisCount === 0) {
                    break;
                }
            } else {
                // If API doesn't send totalNum, stop if current page returned < pageSize
                if ($thisCount < $pageSize) {
                    break;
                }
            }

            $pageIndex++;
        }

        return response()->json([
            'ok'             => true,
            'fetched'        => $fetched,
            'processed'      => $inserted, // processed rows (inserted/updated)
            'pages_done'     => $pagesDone,
            'next_page_index'=> $pageIndex,
            'from'           => $from->toIso8601ZuluString(),
            'to'             => $to->toIso8601ZuluString(),
        ]);
    }
}
