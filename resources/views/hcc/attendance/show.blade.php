@extends('layouts.master')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Attendance Record Details</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <h4 class="card-title">Record #{{ $attendance->id }}</h4>
                        <a href="{{ route('admin.hcc.attendance.index') }}" class="btn btn-secondary btn-sm">
                            <i class="mdi mdi-arrow-left"></i> Back to List
                        </a>
                    </div>

                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th width="30%">Person Code</th>
                                <td><strong>{{ $attendance->person_code }}</strong></td>
                            </tr>
                            <tr>
                                <th>Full Name</th>
                                <td>{{ $attendance->full_name }}</td>
                            </tr>
                            <tr>
                                <th>Department</th>
                                <td>{{ $attendance->department ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Date</th>
                                <td>{{ $attendance->attendance_date->format('Y-m-d (l)') }}</td>
                            </tr>
                            <tr>
                                <th>Time</th>
                                <td>{{ $attendance->attendance_time }}</td>
                            </tr>
                            <tr>
                                <th>Weekday</th>
                                <td>{{ $attendance->weekday }}</td>
                            </tr>
                            <tr>
                                <th>Device ID</th>
                                <td>{{ $attendance->device_id ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Device Name</th>
                                <td>
                                    @if($attendance->device)
                                        <span class="badge badge-info">{{ $attendance->device->name }}</span>
                                    @else
                                        {{ $attendance->device_name ?? '-' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Device Serial</th>
                                <td>
                                    @if($attendance->device)
                                        {{ $attendance->device->serial_no ?? '-' }}
                                    @else
                                        {{ $attendance->device_serial ?? '-' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Record Created</th>
                                <td>{{ $attendance->created_at->format('Y-m-d H:i:s') }}</td>
                            </tr>
                            <tr>
                                <th>Last Updated</th>
                                <td>{{ $attendance->updated_at->format('Y-m-d H:i:s') }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <h5 class="mt-4">Raw Source Data</h5>
                    <div class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;">
                        <pre><code>{{ json_encode($attendance->source_data, JSON_PRETTY_PRINT) }}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


