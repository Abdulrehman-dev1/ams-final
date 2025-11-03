@extends('layouts.master')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">HikCentral Connect - Devices</h4>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">Total Devices</h6>
                    <h3 class="mb-0">{{ number_format($stats['total_devices']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">Last Synced</h6>
                    <h3 class="mb-0">
                        @if($stats['latest_sync'])
                            {{ \Carbon\Carbon::parse($stats['latest_sync'])->diffForHumans() }}
                        @else
                            Never
                        @endif
                    </h3>
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
                        <h4 class="card-title">Devices List</h4>
                        <div>
                            <a href="{{ route('admin.hcc.attendance.index') }}" class="btn btn-secondary btn-sm mr-2">
                                <i class="mdi mdi-arrow-left"></i> Back to Attendance
                            </a>
                            <form action="{{ route('admin.hcc.sync.devices') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="mdi mdi-sync"></i> Sync Devices
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Device ID</th>
                                    <th>Name</th>
                                    <th>Serial Number</th>
                                    <th>Category</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($devices as $device)
                                    <tr>
                                        <td><code>{{ $device->device_id }}</code></td>
                                        <td>
                                            <strong>{{ $device->name ?? '-' }}</strong>
                                        </td>
                                        <td>{{ $device->serial_no ?? '-' }}</td>
                                        <td>
                                            @if($device->category)
                                                <span class="badge badge-secondary">{{ $device->category }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $device->updated_at->diffForHumans() }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            <p class="my-3">No devices found.</p>
                                            <form action="{{ route('admin.hcc.sync.devices') }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="mdi mdi-sync"></i> Sync Now
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
                        {{ $devices->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


