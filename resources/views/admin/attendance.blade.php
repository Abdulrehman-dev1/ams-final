@extends('layouts.master')




@section('breadcrumb')
  <div class="col-sm-6">
    <h4 class="page-title text-left">Attendance</h4>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="javascript:void(0);">Home</a></li>
      <li class="breadcrumb-item active">Attendance</li>
    </ol>
  </div>
@endsection

@section('button')
  <a href="{{ url('attendance/assign') }}" class="btn btn-primary btn-sm btn-flat btn-icon">
    <i class="bi bi-plus-lg"></i> Add New
  </a>
@endsection

@section('content')
  @include('includes.flash')

  <div class="row">
    <div class="col-12">
      <div class="card card-flat shadow-sm-2">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="mini">Showing <strong>{{ count($attendances) }}</strong> records</div>
          </div>

          <div class="table-rep-plugin">
            <div class="table-responsive mb-0" data-pattern="priority-columns">
              <table id="datatable-buttons"
                     class="table table-striped table-hover align-middle dt-responsive nowrap table-sticky"
                     style="border-collapse:collapse;border-spacing:0;width:100%;">
                <thead class="table-light">
                  <tr>
                    <th data-priority="1" class="nowrap">Date</th>
                    <th data-priority="2" class="nowrap">Employee ID</th>
                    <th data-priority="3">Name</th>
                    <th data-priority="4">Attendance</th>
                    <th data-priority="6" class="nowrap">Time In</th>
                    <th data-priority="7" class="nowrap">Time Out</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($attendances as $attendance)
                    @php
                      $schedule = optional(optional($attendance->employee)->schedules)->first();
                      $timeIn  = $schedule->time_in  ?? '—';
                      $timeOut = $schedule->time_out ?? '—';
                      $onTime  = ($attendance->status == 1);
                    @endphp
                    <tr>
                      <td class="nowrap">{{ $attendance->attendance_date }}</td>
                      <td class="nowrap">{{ $attendance->emp_id }}</td>
                      <td>{{ optional($attendance->employee)->name ?? '—' }}</td>

                      <td>
                        {{ $attendance->attendance_time ?? '—' }}
                        @if($onTime)
                          <span class="badge bg-success badge-soft float-end">On Time</span>
                        @else
                          <span class="badge bg-danger badge-soft float-end">Late</span>
                        @endif
                      </td>

                      <td class="nowrap">{{ $timeIn }}</td>
                      <td class="nowrap">{{ $timeOut }}</td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="6" class="text-center text-muted py-5">
                        <div class="mb-1">No attendance found</div>
                        <div class="mini">Try changing the date or add new records.</div>
                      </td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>
    </div> <!-- end col -->
  </div> <!-- end row -->
@endsection

@section('script')
  <!-- Responsive-table -->
  <script src="{{ URL::asset('plugins/RWD-Table-Patterns/dist/js/rwd-table.min.js') }}"></script>
  <script>
    (function(){
      // init RWD plugin if present
      if (window.jQuery && jQuery.fn.responsiveTable) {
        jQuery('.table-responsive').responsiveTable({ addDisplayAllBtn: 'btn btn-secondary btn-sm' });
      }
      // enable Bootstrap tooltips if you add any later
      if (window.bootstrap && document.querySelector('[data-bs-toggle="tooltip"]')) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=> new bootstrap.Tooltip(el));
      }
    })();
  </script>
@endsection
