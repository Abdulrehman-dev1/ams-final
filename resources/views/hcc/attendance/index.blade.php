@extends('layouts.master')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">HikCentral Connect - Attendance Records</h4>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row ">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">Total Records</h6>
                    <h3 class="mb-0">{{ number_format($stats['total_records']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">Today's Records</h6>
                    <h3 class="mb-0">{{ number_format($stats['today_records']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">Unique Employees</h6>
                    <h3 class="mb-0">{{ number_format($stats['unique_employees']) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Main Card -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <h4 class="card-title">Attendance Records</h4>
                        <div>
                            <form action="{{ route('acs.daily.syncScraper', request()->query()) }}" method="POST"
                                  onsubmit="
                                    const btn = this.querySelector('button');
                                    btn.disabled = true;
                                    const spinner = document.createElement('span');
                                    spinner.className = 'spinner-border spinner-border-sm';
                                    spinner.setAttribute('role', 'status');
                                    btn.innerHTML = '';
                                    btn.appendChild(spinner);
                                    return true;
                                  "
                                  class="d-inline">
                                @csrf
                                <input type="hidden" name="redirect_to" value="hcc_attendance">
                                <button type="submit" class="btn btn-warning btn-sm" title="Run Python Playwright scraper to fetch attendance data">
                                    <i class="mdi mdi-robot"></i> Sync Scraper
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Filters -->
                    <form method="GET" action="{{ route('admin.hcc.attendance.index') }}" class="mb-3">
                        <div class="row">
                            <div class="col-md-3">
                                <input type="date" name="date_from" class="form-control"
                                       placeholder="From Date" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="date_to" class="form-control"
                                       placeholder="To Date" value="{{ request('date_to') }}">
                            </div>
                            <div class="col-md-2">
                                <input type="text" name="search" class="form-control"
                                       placeholder="Search..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <input type="text" name="department" class="form-control"
                                       placeholder="Department" value="{{ request('department') }}">
                            </div>
                            <div class="col-md-2">
                                <select name="device_id" class="form-control">
                                    <option value="">All Devices</option>
                                    @foreach($devices as $device)
                                        <option value="{{ $device->device_id }}"
                                                {{ request('device_id') == $device->device_id ? 'selected' : '' }}>
                                            {{ $device->name ?? $device->device_id }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="mdi mdi-filter"></i> Filter
                                </button>
                                <a href="{{ route('admin.hcc.attendance.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="mdi mdi-filter-remove"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Person Code</th>
                                    <th>Full Name</th>
                                    <th>Department</th>
                                    <th>Device</th>
                                    <th>Weekday</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($attendances as $attendance)
                                    <tr>
                                        <td>{{ $attendance->attendance_date->format('Y-m-d') }}</td>
                                        <td>{{ $attendance->attendance_time }}</td>
                                        <td><strong>{{ $attendance->person_code }}</strong></td>
                                        <td>{{ $attendance->full_name }}</td>
                                        <td>{{ $attendance->department ?? '-' }}</td>
                                        <td>
                                            @if($attendance->device_name)
                                                <span class="badge badge-info" title="{{ $attendance->device_serial }}">
                                                    {{ $attendance->device_name }}
                                                </span>
                                            @else
                                                <span class="text-muted">{{ $attendance->device_id ?? '-' }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $attendance->weekday }}</td>
                                        <td>
                                            <a href="{{ route('admin.hcc.attendance.show', $attendance->id) }}"
                                               class="btn btn-sm btn-outline-primary" title="View Details">
                                                <i class="mdi mdi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">
                                            <p class="my-3">No attendance records found.</p>
                                            <form action="{{ route('admin.hcc.sync.recent') }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="mdi mdi-refresh"></i> Sync Now
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-3">
                        {{ $attendances->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


