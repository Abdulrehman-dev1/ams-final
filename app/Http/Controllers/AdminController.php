<?php

namespace App\Http\Controllers;

use App\Models\DailyEmployee;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function index()
    {
        $tz = config('app.timezone', 'Asia/Karachi');
        $today = Carbon::now($tz)->toDateString();

        $employeeColumns = [
            'person_code',
            'full_name',
            'first_name',
            'last_name',
            'group_name',
            'head_pic_url',
        ];

        if (Schema::hasColumn('daily_employees', 'photo_url')) {
            $employeeColumns[] = 'photo_url';
        }

        $enabledEmployees = DailyEmployee::where('is_enabled', true)
            ->get($employeeColumns);

        $transactions = Transaction::whereDate('date', $today)->get();

        $totalEmployees = $enabledEmployees->count();
        $totalCheckins = $transactions->count();

        $employeeDirectory = $enabledEmployees->keyBy(function ($employee) {
            return (string) ($employee->person_code ?? '');
        });

        $formatEmployee = function (?DailyEmployee $employee = null, ?Transaction $transaction = null) {
            $nameFromEmployee = optional($employee)->full_name
                ?: trim((optional($employee)->first_name ?? '') . ' ' . (optional($employee)->last_name ?? ''));
            $fallbackName = $transaction->name ?? '—';
            $displayName = $nameFromEmployee !== '' ? $nameFromEmployee : $fallbackName;

            $photoUrl = optional($employee)->photo_url ?? optional($employee)->head_pic_url ?? null;
            $initial = Str::upper(Str::substr(trim($displayName), 0, 1)) ?: '—';

            return [
                'person_code' => optional($employee)->person_code ?? ($transaction->person_code ?? '—'),
                'name' => $displayName,
                'group' => optional($employee)->group_name ?? ($transaction->department ?? '—'),
                'photo' => $photoUrl,
                'initial' => $initial,
            ];
        };

        $mapTransactionsToDetails = function ($filterCallback) use ($transactions, $employeeDirectory, $formatEmployee) {
            return $transactions->filter($filterCallback)->map(function ($transaction) use ($employeeDirectory, $formatEmployee) {
                $code = (string) ($transaction->person_code ?? '');
                $employee = $code !== '' ? $employeeDirectory->get($code) : null;
                return $formatEmployee($employee, $transaction);
            })->values();
        };

        $onTimeDetails = $mapTransactionsToDetails(function ($transaction) {
            return $transaction->check_in && (int) $transaction->late_minutes === 0;
        });

        $lateDetails = $mapTransactionsToDetails(function ($transaction) {
            return (int) $transaction->late_minutes > 0;
        });

        $onTimeCount = $onTimeDetails->count();
        $lateCount = $lateDetails->count();

        $onTimePercentage = $totalCheckins > 0
            ? round(($onTimeCount / $totalCheckins) * 100, 1)
            : 0;

        $mobileDetails = $mapTransactionsToDetails(function ($transaction) {
            $source = Str::lower($transaction->data_source ?? '');
            return Str::contains($source, ['mobile', 'app']);
        });

        $deviceDetails = $mapTransactionsToDetails(function ($transaction) {
            $source = Str::lower($transaction->data_source ?? '');
            return Str::contains($source, 'device');
        });

        $earlyLeaveDetails = $mapTransactionsToDetails(function ($transaction) {
            if (!$transaction->check_out || !$transaction->expected_out) {
                return false;
            }
            return $transaction->check_out < $transaction->expected_out;
        });

        $overtimeDetails = $mapTransactionsToDetails(function ($transaction) {
            return (int) $transaction->overtime_minutes > 0;
        });

        $mobileCheckins = $mobileDetails->count();
        $deviceCheckins = $deviceDetails->count();
        $earlyLeaveCount = $earlyLeaveDetails->count();
        $overtimeCount = $overtimeDetails->count();

        $presentCodes = $transactions->pluck('person_code')->filter()->map(fn ($code) => (string) $code)->all();

        $allEmployeeDetails = $enabledEmployees->map(function ($employee) use ($formatEmployee) {
            return $formatEmployee($employee);
        })->values();

        $absentEmployees = $enabledEmployees->filter(function ($employee) use ($presentCodes) {
            $code = (string) ($employee->person_code ?? '');
            if ($code === '') {
                return true;
            }
            return !in_array($code, $presentCodes, true);
        })->map(function ($employee) use ($formatEmployee) {
            return $formatEmployee($employee);
        })->values();

        $metrics = [
            'totalEmployees' => $totalEmployees,
            'onTimePercentage' => $onTimePercentage,
            'onTimeToday' => $onTimeCount,
            'lateToday' => $lateCount,
            'mobileCheckinsToday' => $mobileCheckins,
            'deviceCheckinsToday' => $deviceCheckins,
            'earlyLeaveToday' => $earlyLeaveCount,
            'overtimeToday' => $overtimeCount,
            'absentToday' => $absentEmployees->count(),
        ];

        $detailLists = [
            'totalEmployees' => $allEmployeeDetails,
            'onTimePercentage' => $onTimeDetails,
            'onTimeToday' => $onTimeDetails,
            'lateToday' => $lateDetails,
            'mobileCheckinsToday' => $mobileDetails,
            'deviceCheckinsToday' => $deviceDetails,
            'earlyLeaveToday' => $earlyLeaveDetails,
            'overtimeToday' => $overtimeDetails,
            'absentToday' => $absentEmployees,
        ];

        return view('admin.index', [
            'metrics' => $metrics,
            'absentEmployees' => $absentEmployees->take(100),
            'detailLists' => $detailLists,
            'today' => $today,
        ]);
    }
}
