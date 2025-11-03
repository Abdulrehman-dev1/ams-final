<?php

namespace App\Http\Controllers;

use App\Models\HccAttendanceTransaction;
use App\Models\HccDevice;
use App\Services\HccAttendanceIngestor;
use App\Services\HccDevicesSync;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HccAttendanceController extends Controller
{
    protected HccAttendanceIngestor $ingestor;
    protected HccDevicesSync $devicesSync;

    public function __construct(HccAttendanceIngestor $ingestor, HccDevicesSync $devicesSync)
    {
        $this->middleware('auth');
        $this->ingestor = $ingestor;
        $this->devicesSync = $devicesSync;
    }

    /**
     * Display HCC attendance records.
     */
    public function index(Request $request)
    {
        $query = HccAttendanceTransaction::query()->orderBy('attendance_date', 'desc')->orderBy('attendance_time', 'desc');

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('attendance_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('attendance_date', '<=', $request->date_to);
        }

        // Filter by person
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('person_code', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        // Filter by department
        if ($request->filled('department')) {
            $query->where('department', 'like', "%{$request->department}%");
        }

        // Filter by device
        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        $attendances = $query->paginate(50);
        $devices = HccDevice::orderBy('name')->get();

        // Statistics
        $stats = [
            'total_records' => HccAttendanceTransaction::count(),
            'today_records' => HccAttendanceTransaction::whereDate('attendance_date', Carbon::today())->count(),
            'unique_employees' => HccAttendanceTransaction::distinct('person_code')->count('person_code'),
            'devices_count' => HccDevice::count(),
            'latest_record' => HccAttendanceTransaction::orderBy('attendance_date', 'desc')
                ->orderBy('attendance_time', 'desc')
                ->first(),
        ];

        return view('hcc.attendance.index', compact('attendances', 'devices', 'stats'));
    }

    /**
     * Manually trigger recent ingestion using Dusk scraper with API.
     */
    public function syncRecent()
    {
        try {
            $from = Carbon::yesterday()->format('Y-m-d');
            $to = Carbon::today()->format('Y-m-d');

            // Run the API-direct scraper command
            \Illuminate\Support\Facades\Artisan::call('hcc:scrape-api-direct', [
                '--from' => $from,
                '--to' => $to,
            ]);

            $output = \Illuminate\Support\Facades\Artisan::output();

            // Count records for feedback
            $totalCount = HccAttendanceTransaction::whereBetween('attendance_date', [$from, $to])->count();

            Log::info('HCC manual sync completed', ['output' => $output]);

            return back()->with('success', "✓ Sync completed! Found {$totalCount} records from {$from} to {$to}.");
        } catch (\Exception $e) {
            Log::error('HCC manual sync failed', ['error' => $e->getMessage()]);
            return back()->with('error', '✗ Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Manually trigger device sync using Dusk scraper.
     */
    public function syncDevices()
    {
        try {
            // Run the device scraper command
            \Illuminate\Support\Facades\Artisan::call('hcc:scrape:devices');

            $output = \Illuminate\Support\Facades\Artisan::output();
            $count = HccDevice::count();

            Log::info('HCC device sync completed', ['output' => $output]);

            return back()->with('success', "✓ Device sync completed! Total {$count} devices in database.");
        } catch (\Exception $e) {
            Log::error('HCC device sync failed', ['error' => $e->getMessage()]);
            return back()->with('error', '✗ Device sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Display devices list.
     */
    public function devices()
    {
        $devices = HccDevice::orderBy('name')->paginate(20);

        $stats = [
            'total_devices' => HccDevice::count(),
            'latest_sync' => HccDevice::max('updated_at'),
        ];

        return view('hcc.devices.index', compact('devices', 'stats'));
    }

    /**
     * Backfill date range form.
     */
    public function backfillForm()
    {
        return view('hcc.attendance.backfill');
    }

    /**
     * Process backfill request using Dusk scraper with API.
     */
    public function backfill(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date|before_or_equal:date_to',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        try {
            // Run the API-direct scraper command for the date range
            \Illuminate\Support\Facades\Artisan::call('hcc:scrape-api-direct', [
                '--from' => $request->date_from,
                '--to' => $request->date_to,
            ]);

            $output = \Illuminate\Support\Facades\Artisan::output();

            // Count records in the date range
            $count = HccAttendanceTransaction::whereBetween('attendance_date', [$request->date_from, $request->date_to])->count();

            Log::info('HCC backfill completed', [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'output' => $output,
            ]);

            return back()->with('success', "✓ Backfill completed! Found {$count} records from {$request->date_from} to {$request->date_to}");
        } catch (\Exception $e) {
            Log::error('HCC backfill failed', [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', '✗ Backfill failed: ' . $e->getMessage());
        }
    }

    /**
     * Show attendance details.
     */
    public function show($id)
    {
        $attendance = HccAttendanceTransaction::with('device')->findOrFail($id);

        return view('hcc.attendance.show', compact('attendance'));
    }
}
