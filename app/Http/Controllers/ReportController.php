<?php

namespace App\Http\Controllers;

use App\Models\AcsEvent;
use App\Models\DailyEmployee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $tz = config('app.timezone', 'Asia/Karachi');
        $now = Carbon::now($tz);

        $rangeType = $request->input('range_type', 'weekly');
        $startInput = $request->input('start_date');
        $endInput = $request->input('end_date');
        $datesInput = collect($request->input('dates', []))->filter(fn ($d) => !empty($d));

        [$start, $end, $label, $activeDates] = $this->resolveDateRange($rangeType, $startInput, $endInput, $datesInput, $now, $tz);

        // Build employee schedule (time_in, time_out, late_cutoff)
        $scheduleMap = $this->buildScheduleMap($tz);

        $eventsQuery = AcsEvent::query()
            ->select([
                'person_code',
                'card_number',
                'full_name',
                'first_name',
                'last_name',
                'group_name',
                'occur_time_pk',
                'device_name',
                'card_reader_name',
            ])
            ->orderBy('occur_time_pk');

        if (!empty($activeDates)) {
            $eventsQuery->whereIn('occur_date_pk', $activeDates);
        } else {
            $eventsQuery->whereBetween('occur_time_pk', [$start, $end]);
        }

        $events = $eventsQuery->get();

        $grouped = $events->groupBy(function ($item) {
            if (!empty($item->person_code)) {
                return (string) $item->person_code;
            }
            if (!empty($item->card_number)) {
                return 'card:' . $item->card_number;
            }
            return 'unknown:' . spl_object_id($item);
        });

        $reportRows = [];
        $totals = [
            'employees' => 0,
            'events' => 0,
            'late' => 0,
            'mobile' => 0,
            'device' => 0,
            'avg_work_minutes' => 0,
        ];

        foreach ($grouped as $key => $rows) {
            $rows = $rows->sortBy('occur_time_pk');
            $first = $rows->first();
            $last = $rows->last();

            $personCode = $first->person_code ?: (Str::startsWith($key, 'card:') ? substr($key, 5) : null);
            $schedule = $scheduleMap[$personCode] ?? $scheduleMap['__default'];

            $firstTime = Carbon::parse($first->occur_time_pk, $tz)->timezone($tz);
            $lastTime = Carbon::parse($last->occur_time_pk, $tz)->timezone($tz);

            $lateCutoff = Carbon::parse($firstTime->toDateString() . ' ' . $schedule['late_cutoff'], $tz);
            $expectedIn = Carbon::parse($firstTime->toDateString() . ' ' . $schedule['time_in'], $tz);
            $expectedOut = Carbon::parse($firstTime->toDateString() . ' ' . $schedule['time_out'], $tz);

            $isLate = $firstTime->greaterThan($lateCutoff);
            $lateMinutes = $isLate ? $lateCutoff->diffInMinutes($firstTime) : 0;

            $workedMinutes = max($firstTime->diffInMinutes($lastTime), 0);

            $mobilePunches = $rows->filter(fn ($row) => $this->isMobile($row))->count();
            $devicePunches = $rows->count() - $mobilePunches;

            $name = $this->resolveName($first);
            $group = $first->group_name ?: ($schedule['group_name'] ?? '—');

            $reportRows[] = [
                'person_code' => $personCode ?: '—',
                'name' => $name,
                'group' => $group,
                'total_events' => $rows->count(),
                'first_in' => $firstTime,
                'last_out' => $lastTime,
                'expected_in' => $expectedIn,
                'expected_out' => $expectedOut,
                'is_late' => $isLate,
                'late_minutes' => $lateMinutes,
                'worked_minutes' => $workedMinutes,
                'mobile_punches' => $mobilePunches,
                'device_punches' => $devicePunches,
            ];

            $totals['employees']++;
            $totals['events'] += $rows->count();
            $totals['mobile'] += $mobilePunches;
            $totals['device'] += $devicePunches;
            $totals['late'] += $isLate ? 1 : 0;
            $totals['avg_work_minutes'] += $workedMinutes;
        }

        usort($reportRows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        if ($totals['employees'] > 0) {
            $totals['avg_work_minutes'] = round($totals['avg_work_minutes'] / $totals['employees']);
        }

        return view('admin.reports.index', [
            'rows' => $reportRows,
            'totals' => $totals,
            'range_label' => $label,
            'range_type' => $rangeType,
            'start_date' => $start?->format('Y-m-d'),
            'end_date' => $end?->format('Y-m-d'),
            'selected_dates' => $datesInput,
            'request' => $request,
        ]);
    }

    protected function resolveDateRange(string $rangeType, ?string $startInput, ?string $endInput, Collection $datesInput, Carbon $now, string $tz): array
    {
        if ($datesInput->isNotEmpty()) {
            $parsedDates = $datesInput->map(function ($date) use ($tz) {
                try {
                    return Carbon::parse($date, $tz)->toDateString();
                } catch (\Throwable $e) {
                    return null;
                }
            })->filter();

            if ($parsedDates->isNotEmpty()) {
                $start = Carbon::parse($parsedDates->min(), $tz)->startOfDay();
                $end = Carbon::parse($parsedDates->max(), $tz)->endOfDay();
                return [$start, $end, 'Selected Dates', $parsedDates->values()->all()];
            }
        }

        $start = null;
        $end = null;
        $label = 'Custom Range';

        switch ($rangeType) {
            case 'weekly':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                $label = 'This Week';
                break;
            case 'monthly':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $label = 'This Month';
                break;
            case 'quarterly':
                $currentQuarter = (int) ceil($now->quarter);
                $start = $now->copy()->firstOfQuarter();
                $end = $now->copy()->lastOfQuarter();
                $label = 'This Quarter (Q' . $currentQuarter . ')';
                break;
            case 'custom':
            default:
                if ($startInput) {
                    $start = Carbon::parse($startInput, $tz)->startOfDay();
                }
                if ($endInput) {
                    $end = Carbon::parse($endInput, $tz)->endOfDay();
                }
                if (!$start && !$end) {
                    $start = $now->copy()->startOfWeek();
                    $end = $now->copy()->endOfWeek();
                }
                $label = 'Custom Range';
                break;
        }

        return [$start, $end, $label, []];
    }

    protected function buildScheduleMap(string $tz): array
    {
        $map = [];
        $employees = DailyEmployee::select(['person_code', 'full_name', 'group_name', 'time_in', 'time_out'])
            ->whereNotNull('person_code')
            ->where('person_code', '!=', '')
            ->get();

        foreach ($employees as $employee) {
            $timeIn = $employee->time_in ?: '09:00:00';
            $timeOut = $employee->time_out ?: '19:00:00';

            if (strlen($timeIn) === 5) {
                $timeIn .= ':00';
            }
            if (strlen($timeOut) === 5) {
                $timeOut .= ':00';
            }

            try {
                $lateCutoff = Carbon::createFromFormat('H:i:s', $timeIn, $tz)->addMinutes(15)->format('H:i:s');
            } catch (\Exception $e) {
                $lateCutoff = Carbon::createFromFormat('H:i:s', '09:00:00', $tz)->addMinutes(15)->format('H:i:s');
                $timeIn = '09:00:00';
            }

            $map[$employee->person_code] = [
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'late_cutoff' => $lateCutoff,
                'group_name' => $employee->group_name,
            ];
        }

        $map['__default'] = [
            'time_in' => '09:00:00',
            'time_out' => '19:00:00',
            'late_cutoff' => '09:15:00',
            'group_name' => null,
        ];

        return $map;
    }

    protected function isMobile($event): bool
    {
        $device = strtolower((string) $event->device_name);
        $reader = strtolower((string) $event->card_reader_name);
        return str_contains($device, 'mobile') || str_contains($device, 'app') || str_contains($reader, 'mobile') || str_contains($reader, 'app');
    }

    protected function resolveName($event): string
    {
        $name = trim((string) ($event->full_name ?? ''));
        if ($name === '') {
            $first = trim((string) ($event->first_name ?? ''));
            $last = trim((string) ($event->last_name ?? ''));
            $name = trim($first . ' ' . $last);
        }
        return $name !== '' ? $name : '—';
    }
}
