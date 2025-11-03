@extends('layouts.master')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">HCC - Backfill Attendance Data</h4>
            </div>
        </div>
    </div>

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

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">Import Historical Attendance Data</h4>

                    <div class="alert alert-info">
                        <i class="mdi mdi-information"></i>
                        <strong>Note:</strong> This will import attendance records from HikCentral Connect for the specified date range.
                        The process may take several minutes for large date ranges. The system will fetch data day by day.
                    </div>

                    <form action="{{ route('admin.hcc.backfill.process') }}" method="POST">
                        @csrf

                        <div class="form-group">
                            <label for="date_from">From Date <span class="text-danger">*</span></label>
                            <input type="date"
                                   name="date_from"
                                   id="date_from"
                                   class="form-control @error('date_from') is-invalid @enderror"
                                   value="{{ old('date_from') }}"
                                   required>
                            @error('date_from')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="date_to">To Date <span class="text-danger">*</span></label>
                            <input type="date"
                                   name="date_to"
                                   id="date_to"
                                   class="form-control @error('date_to') is-invalid @enderror"
                                   value="{{ old('date_to') }}"
                                   required>
                            @error('date_to')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-warning">
                            <i class="mdi mdi-alert"></i>
                            <strong>Warning:</strong>
                            <ul class="mb-0">
                                <li>Large date ranges may take significant time to process</li>
                                <li>Duplicate records will be automatically skipped</li>
                                <li>Make sure your HCC authentication credentials are valid</li>
                                <li>This operation will run synchronously - do not close this page</li>
                            </ul>
                        </div>

                        <div class="form-group mb-0">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-calendar-import"></i> Start Backfill
                            </button>
                            <a href="{{ route('admin.hcc.attendance.index') }}" class="btn btn-secondary">
                                <i class="mdi mdi-arrow-left"></i> Back to Attendance
                            </a>
                        </div>
                    </form>

                    <hr class="my-4">

                    <h5>Quick Commands (Alternative)</h5>
                    <p class="text-muted">You can also run backfill via command line for better performance:</p>
                    <div class="bg-light p-3 rounded">
                        <code>php artisan hcc:ingest:range --from=2025-10-01 --to=2025-10-31</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


