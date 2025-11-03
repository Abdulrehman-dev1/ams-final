<?php

namespace App\Http\Controllers;

use App\Models\AcsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminRollupWebController extends Controller
{
    /**
     * Live ACS-only daily view (no attendance table).
     * Shows per-person first (check-in) and last (check-out) event for a date.
     */
    public function index(Request $req)
    {
        $tz = config('app.timezone', 'Asia/Karachi');

        // Filters
        $date        = $req->query('date');                 // YYYY-MM-DD
        $personCode  = trim((string) $req->query('person_code', ''));
        $nameLike    = trim((string) $req->query('name', ''));
        $sourceWant  = $req->query('source');               // Device | Mobile | (blank)
        $perPageReq  = (int) $req->query('perPage', 25);
        $perPage     = max(10, min(200, $perPageReq));

        // Default to today (local) if no date provided
        if (!$date) $date = now($tz)->toDateString();

        // Build base query for the given local date window
        $start = Carbon::parse($date, $tz)->startOfDay();
        $end   = Carbon::parse($date, $tz)->endOfDay();

        $q = AcsEvent::query()
            ->whereBetween('occur_time_pk', [$start, $end]);

        // Lightweight pre-filters to reduce set:
        if ($personCode !== '') {
            $q->where('person_code', $personCode);
        }

        if ($nameLike !== '') {
            $q->where(function($w) use ($nameLike) {
                $w->where('full_name','like',"%{$nameLike}%")
                  ->orWhere('first_name','like',"%{$nameLike}%")
                  ->orWhere('last_name','like',"%{$nameLike}%");
            });
        }

        // Order so first() / last() per person can be found in one pass
        $q->orderBy('person_code')->orderBy('occur_time_pk');

        // Select only the fields we actually use
        $events = $q->get([
            'person_code','card_number','occur_time_pk','occur_date_pk',
            'device_id','device_name','card_reader_name',
            'first_name','last_name','full_name','full_path','photo_url',
            'record_guid','event_type','direction','swipe_auth_result','card_number'
        ]);

        // Group by person_code (skip blanks)
        $grouped = [];
        foreach ($events as $e) {
            $pc = trim((string) ($e->person_code ?? ''));
            if ($pc === '') continue; // unknown → skip; optional: bucket separately if needed
            if (!isset($grouped[$pc])) $grouped[$pc] = [];
            $grouped[$pc][] = $e;
        }

        // Build per-person summaries (first = check-in, last = check-out)
        $rows = [];
        foreach ($grouped as $pc => $rowsForPerson) {
            // rowsForPerson already ordered by occur_time_pk (because main query had orderBy)
            $first = $rowsForPerson[0];
            $last  = $rowsForPerson[count($rowsForPerson) - 1];

            [$inSource,  $inDeviceName]  = $this->sourceFromEvent($first);
            [$outSource, $outDeviceName] = $this->sourceFromEvent($last);

            // If UI wants to filter by source, apply now (match if either IN or OUT matches)
            if (in_array($sourceWant, ['Device','Mobile'], true)) {
                if (!($inSource === $sourceWant || $outSource === $sourceWant)) {
                    continue;
                }
            }

            // Prefer values from first; fallback to last if blank
            $firstName = $first->first_name ?: $last->first_name;
            $lastName  = $first->last_name  ?: $last->last_name;
            $fullName  = $first->full_name  ?: $last->full_name;
            $groupName = $first->full_path  ?: $last->full_path;
            $photoUrl  = $first->photo_url  ?: $last->photo_url;

            $rows[] = [
                'person_code'     => $pc,
                'occur_date_pk'   => (string) ($first->occur_date_pk ?: $date), // keep provided date as fallback
                'first_event'     => $first, // we will format time in Blade
                'last_event'      => $last,
                'photo_url'       => $photoUrl,
                'first_name'      => $firstName,
                'last_name'       => $lastName,
                'full_name'       => $fullName,
                'group_name'      => $groupName,
                'in_source'       => $inSource,       // Device | Mobile
                'out_source'      => $outSource,      // Device | Mobile
                'in_device_name'  => $inDeviceName,   // nullable
                'out_device_name' => $outDeviceName,  // nullable
            ];
        }

        // Manual pagination for the computed array
        $page     = (int) max(1, (int) $req->query('page', 1));
        $total    = count($rows);
        $offset   = ($page - 1) * $perPage;
        $slice    = array_slice($rows, $offset, $perPage);

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => $req->url(), 'query' => $req->query()]
        );

        return view('admin.acs_daily', [
            'page'    => $paginator,
            'filters' => $req->all(),
            'tz'      => $tz,
        ]);
    }

    /**
     * Raw timeline of events for a given person_code + date (for modal).
     */
    public function timeline(Request $req)
    {
        $pc   = $req->query('person_code');
        $date = $req->query('date');
        if (!$pc || !$date) {
            return response()->json(['ok' => false, 'message' => 'Missing person_code or date'], 422);
        }

        $tz    = config('app.timezone', 'Asia/Karachi');
        $start = Carbon::parse($date, $tz)->startOfDay();
        $end   = $start->copy()->endOfDay();

        $events = AcsEvent::query()
            ->where('person_code', $pc)
            ->whereBetween('occur_time_pk', [$start, $end])
            ->orderBy('occur_time_pk')
            ->get([
                'record_guid','occur_time_pk',
                'device_id','device_name','card_reader_name',
                'event_type','direction','swipe_auth_result','card_number',
                'photo_url','first_name','last_name','full_name','full_path'
            ]);

        return response()->json(['ok' => true, 'events' => $events]);
    }

    /**
     * Decide Mobile vs Device.
     * If any of device_name / card_reader_name / device_id is non-empty => Device; else Mobile.
     */
    private function sourceFromEvent($e): array
    {
        $dn = trim((string) ($e->device_name ?? ''));
        $cr = trim((string) ($e->card_reader_name ?? ''));
        $di = trim((string) ($e->device_id ?? ''));

        $hasDevice  = ($dn !== '') || ($cr !== '') || ($di !== '');
        $source     = $hasDevice ? 'Device' : 'Mobile';
        $deviceName = $dn !== '' ? $dn : ($cr !== '' ? $cr : null);

        return [$source, $deviceName];
    }
}
