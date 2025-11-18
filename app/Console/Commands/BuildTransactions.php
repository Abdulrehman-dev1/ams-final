<?php

namespace App\Console\Commands;

use App\Models\DailyEmployee;
use App\Models\HccAttendanceTransaction;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuildTransactions extends Command
{
    protected $signature = 'transactions:build {--date=} {--person=} {--days=1}';

    protected $description = 'Build condensed transactions from HCC attendance transactions and daily employees';

    public function handle(): int
    {
        $tz = config('app.timezone', 'Asia/Karachi');

        $dateOption = $this->option('date');
        $daysBack = (int) $this->option('days');
        if ($daysBack < 1) {
            $daysBack = 1;
        }

        $dateRange = $this->resolveDateRange($dateOption, $daysBack, $tz);

        $this->info(sprintf(
            'Building transactions for %s to %s',
            $dateRange['start']->toDateString(),
            $dateRange['end']->toDateString()
        ));

        $personFilter = $this->option('person');

        $attendance = $this->loadAttendance($dateRange, $personFilter, $tz);
        if ($attendance->isEmpty()) {
            $this->warn('No attendance records found for given filters.');
            return Command::SUCCESS;
        }

        $employees = $this->loadEmployees($attendance->pluck('person_code')->filter()->unique()->values());

        $grouped = $attendance->groupBy(fn ($row) => $row->person_code.'|'.$row->attendance_date);

        $progress = $this->output->createProgressBar($grouped->count());
        $progress->start();

        DB::transaction(function () use ($grouped, $employees, $tz, $progress) {
            foreach ($grouped as $key => $rows) {
                [$personCode, $date] = explode('|', $key);

                $payload = $this->buildPayload($rows, $employees->get($personCode), $tz);
                $payload['person_code'] = $personCode;
                $payload['date'] = $date;

                Transaction::updateOrCreate(
                    ['person_code' => $personCode, 'date' => $date],
                    $payload
                );

                $progress->advance();
            }
        });

        $progress->finish();
        $this->newLine(2);
        $this->info('Transactions build complete.');

        return Command::SUCCESS;
    }

    protected function resolveDateRange(?string $dateOption, int $daysBack, string $tz): array
    {
        if ($dateOption) {
            $date = Carbon::parse($dateOption, $tz)->startOfDay();
            return [
                'start' => $date,
                'end' => $date->copy()->endOfDay(),
            ];
        }

        $now = Carbon::now($tz);
        $start = $now->copy()->startOfDay()->setTime(6, 0, 0);
        $end = $now;

        if ($now->lessThan($start)) {
            $start = $now->copy()->subDay()->startOfDay()->setTime(6, 0, 0);
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    protected function loadAttendance(array $range, ?string $personFilter, string $tz): Collection
    {
        $query = HccAttendanceTransaction::query()
            ->select(['person_code', 'attendance_date', 'attendance_time', 'source_data'])
            ->whereBetween('attendance_date', [
                $range['start']->copy()->startOfDay()->toDateString(),
                $range['end']->copy()->endOfDay()->toDateString(),
            ])
            ->orderBy('person_code')
            ->orderBy('attendance_date')
            ->orderBy('attendance_time');

        if ($personFilter) {
            $query->where('person_code', $personFilter);
        }

        return $query->get()
            ->map(function ($row) use ($tz) {
                $source = $this->decodeSource($row->source_data ?? []);

                $date = Carbon::parse($row->attendance_date, $tz);

                $clock = $this->normalizeTime($row->attendance_time ?? data_get($source, 'clockTime'));
                if ($clock) {
                    $datetime = $date->copy()->setTimeFromTimeString($clock);
                } else {
                    $datetime = $date->copy();
                }

                $row->attendance_date = $date->toDateString();
                $row->attendance_time = $clock;
                $row->attendance_datetime = $datetime->copy();
                $row->source = $source;
                return $row;
            })
            ->filter(function ($row) use ($range) {
                return $row->attendance_datetime->between($range['start'], $range['end']);
            })
            ->values();
    }

    protected function loadEmployees(Collection $personCodes): Collection
    {
        if ($personCodes->isEmpty()) {
            return collect();
        }

        return DailyEmployee::query()
            ->select(['person_code', 'time_in', 'time_out'])
            ->whereIn('person_code', $personCodes->all())
            ->get()
            ->keyBy('person_code')
            ->map(function ($employee) {
                $timeIn = $employee->time_in ?: '09:00:00';
                $timeOut = $employee->time_out ?: '19:00:00';
                if (strlen($timeIn) === 5) {
                    $timeIn .= ':00';
                }
                if (strlen($timeOut) === 5) {
                    $timeOut .= ':00';
                }
                return [
                    'time_in' => $timeIn,
                    'time_out' => $timeOut,
                ];
            });
    }

    protected function decodeSource($source): array
    {
        if (is_array($source)) {
            return $source;
        }
        if (is_string($source)) {
            $decoded = json_decode($source, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    protected function buildPayload(Collection $rows, ?array $employee, string $tz): array
    {
        $first = $rows->first();
        $source = $first->source;

        $expectedIn = $employee['time_in'] ?? '09:00:00';
        $expectedOut = $employee['time_out'] ?? '19:00:00';
        $expectedInCarbon = Carbon::createFromFormat('H:i:s', $expectedIn, $tz);
        $expectedOutCarbon = Carbon::createFromFormat('H:i:s', $expectedOut, $tz);

        $checkEvents = $rows->map(function ($row) use ($tz) {
            $sourceClock = data_get($row->source, 'clockTime');
            $clock = $row->attendance_time ?: $sourceClock;
            if (!$clock) {
                return null;
            }

            $clock = $this->normalizeTime($clock);
            if (!$clock) {
                return null;
            }

            $dt = Carbon::parse($row->attendance_datetime ?? $row->attendance_date.' '.$clock, $tz);

            return [
                'datetime' => $dt,
                'raw' => $row,
            ];
        })->filter()->sortBy('datetime')->values();

        $checkIn = $checkEvents->first();
        $checkOut = $this->determineCheckout($checkEvents, $expectedOutCarbon);

        $lateMinutes = 0;
        $overtimeMinutes = 0;

        if ($checkIn) {
            if ($checkIn['datetime']->greaterThan($expectedInCarbon)) {
                $lateMinutes = $expectedInCarbon->diffInMinutes($checkIn['datetime']);
            } else {
                $overtimeMinutes += $checkIn['datetime']->diffInMinutes($expectedInCarbon);
            }
        }

        if ($checkOut) {
            if ($checkOut['datetime']->greaterThan($expectedOutCarbon)) {
                $overtimeMinutes += $expectedOutCarbon->diffInMinutes($checkOut['datetime']);
            }
        }

        $location = data_get($source, 'location');
        if (is_array($location)) {
            $location = data_get($location, 'detailAddress') ?: data_get($location, 'briefAddress') ?: json_encode($location);
        }

        return [
            'name' => data_get($source, 'fullName') ?: trim(collect([
                data_get($source, 'firstName'),
                data_get($source, 'lastName'),
            ])->filter()->implode(' ')) ?: data_get($first, 'raw.person_code'),
            'department' => data_get($source, 'fullPath') ?: data_get($source, 'groupName') ?: data_get($source, 'complete_record.fullPath'),
            'expected_in' => $expectedIn,
            'check_in' => $checkIn ? $checkIn['datetime']->format('H:i:s') : null,
            'expected_out' => $expectedOut,
            'check_out' => $checkOut ? $checkOut['datetime']->format('H:i:s') : null,
            'data_source' => data_get($source, 'dataSource'),
            'location' => $location,
            'latitude' => $this->toDecimal(data_get($source, 'latitude') ?? data_get($source, 'location.latitude')),
            'longitude' => $this->toDecimal(data_get($source, 'longitude') ?? data_get($source, 'location.longitude')),
            'device_name' => data_get($source, 'deviceName') ?: data_get($source, 'complete_record.deviceName'),
            'device_serial' => data_get($source, 'deviceSerial') ?: data_get($source, 'complete_record.deviceSerial'),
            'device_id' => data_get($source, 'deviceId') ?: data_get($source, 'complete_record.deviceId'),
            'late_minutes' => $lateMinutes,
            'overtime_minutes' => $overtimeMinutes,
        ];
    }

    protected function determineCheckout(Collection $events, Carbon $expectedOut): ?array
    {
        if ($events->count() <= 1) {
            return null;
        }

        $first = $events->first();
        $candidate = null;
        foreach ($events->slice(1) as $event) {
            if ($event['datetime']->diffInMinutes($first['datetime']) >= 240) {
                $candidate = $event;
            }
        }

        return $candidate;
    }

    protected function normalizeTime(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        $value = str_replace(['a.m.', 'p.m.', 'AM', 'PM'], ['am', 'pm', 'am', 'pm'], strtolower($value));
        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function toDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }
}

