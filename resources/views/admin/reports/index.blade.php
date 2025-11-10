@extends('layouts.master')

@section('content')
<div class="container-fluid">
    <div class="modern-card mb-4">
        <div class="modern-card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h2 class="mb-1" style="font-weight: 700; color: var(--gray-900);">
                    <i class="bi bi-graph-up-arrow me-2" style="color: var(--primary);"></i>
                    Attendance Reports
                </h2>
                <p class="text-muted mb-0" style="font-size: 0.875rem;">Insights across weekly, monthly, quarterly, or custom date ranges</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <span class="modern-badge modern-badge-primary" style="font-size: 0.9rem;">
                    <i class="bi bi-calendar-week me-1"></i>{{ $range_label }}
                </span>
                <span class="modern-badge" style="background: rgba(34,197,94,0.1); color: var(--success); font-size: 0.9rem;">
                    <i class="bi bi-people-fill me-1"></i>{{ $totals['employees'] }} Employees
                </span>
            </div>
        </div>
    </div>

    <div class="modern-card mb-4">
        <div class="modern-card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0" style="font-weight: 600;"><i class="bi bi-funnel me-2" style="color: var(--primary);"></i>Filters</h5>
            <a href="{{ route('admin.reports.index') }}" class="btn-modern" style="background: var(--gray-600); color: #fff;">
                <i class="bi bi-arrow-clockwise"></i>
                <span>Reset</span>
            </a>
        </div>
        <div class="modern-card-body">
            <form method="GET" action="{{ route('admin.reports.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="modern-form-label">Range Type</label>
                    <select name="range_type" id="range_type" class="modern-form-input">
                        <option value="weekly" {{ $range_type === 'weekly' ? 'selected' : '' }}>Weekly</option>
                        <option value="monthly" {{ $range_type === 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="quarterly" {{ $range_type === 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                        <option value="custom" {{ $range_type === 'custom' ? 'selected' : '' }}>Custom</option>
                        <option value="dates" {{ $selected_dates->isNotEmpty() ? 'selected' : '' }}>Selected Dates</option>
                    </select>
                </div>
                <div class="col-md-3 range-field range-custom">
                    <label class="modern-form-label">Start Date</label>
                    <input type="date" name="start_date" value="{{ old('start_date', $start_date) }}" class="modern-form-input">
                </div>
                <div class="col-md-3 range-field range-custom">
                    <label class="modern-form-label">End Date</label>
                    <input type="date" name="end_date" value="{{ old('end_date', $end_date) }}" class="modern-form-input">
                </div>
                <div class="col-md-3 range-field range-dates">
                    <label class="modern-form-label d-flex align-items-center justify-content-between">
                        Selected Dates
                        <button type="button" class="btn btn-sm btn-outline-primary" id="add-date-input">
                            <i class="bi bi-plus-circle"></i> Add
                        </button>
                    </label>
                    <div id="dates-wrapper">
                        @forelse($selected_dates as $date)
                            <input type="date" name="dates[]" value="{{ $date }}" class="modern-form-input mb-2">
                        @empty
                            <input type="date" name="dates[]" value="" class="modern-form-input mb-2">
                        @endforelse
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                    <button type="submit" class="btn-modern btn-modern-success">
                        <i class="bi bi-search"></i>
                        <span>Apply Filters</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-sm-6">
            <div class="modern-widget slide-up">
                <div class="modern-widget-icon modern-widget-icon-primary"><i class="bi bi-people"></i></div>
                <div class="modern-widget-label">Employees Covered</div>
                <div class="modern-widget-value">{{ number_format($totals['employees']) }}</div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="modern-widget slide-up" style="animation-delay:0.1s;">
                <div class="modern-widget-icon modern-widget-icon-info"><i class="bi bi-upc-scan"></i></div>
                <div class="modern-widget-label">Total Punches</div>
                <div class="modern-widget-value">{{ number_format($totals['events']) }}</div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="modern-widget slide-up" style="animation-delay:0.2s;">
                <div class="modern-widget-icon modern-widget-icon-warning"><i class="bi bi-alarm"></i></div>
                <div class="modern-widget-label">Late Employees</div>
                <div class="modern-widget-value">{{ number_format($totals['late']) }}</div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="modern-widget slide-up" style="animation-delay:0.3s;">
                <div class="modern-widget-icon modern-widget-icon-success"><i class="bi bi-hourglass-split"></i></div>
                <div class="modern-widget-label">Avg. Work Hours</div>
                <div class="modern-widget-value">
                    @php
                        $avgMinutes = $totals['avg_work_minutes'];
                        $hours = floor($avgMinutes / 60);
                        $minutes = $avgMinutes % 60;
                    @endphp
                    {{ sprintf('%02d:%02d', $hours, $minutes) }} hrs
                </div>
            </div>
        </div>
    </div>

    <div class="modern-card">
        <div class="modern-card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0" style="font-weight: 600;">
                <i class="bi bi-table me-2" style="color: var(--primary);"></i>
                Employee Attendance Report
            </h5>
            <div class="mini">Showing {{ $range_label }}</div>
        </div>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Person Code</th>
                        <th>Team</th>
                        <th>First Check-In</th>
                        <th>Expected In</th>
                        <th>Last Check-Out</th>
                        <th>Expected Out</th>
                        <th>Late</th>
                        <th>Duration</th>
                        <th>Mobile</th>
                        <th>Device</th>
                        <th>Total Punches</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $idx => $row)
                        @php
                            $durationHours = floor($row['worked_minutes'] / 60);
                            $durationMinutes = $row['worked_minutes'] % 60;
                        @endphp
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $row['name'] }}</div>
                                <div class="mini">{{ $row['group'] }}</div>
                            </td>
                            <td>
                                <span class="modern-badge modern-badge-primary" style="font-size: 0.75rem;">{{ $row['person_code'] }}</span>
                            </td>
                            <td>{{ $row['group'] }}</td>
                            <td>{{ $row['first_in']->format('Y-m-d h:i A') }}</td>
                            <td>{{ $row['expected_in']->format('h:i A') }}</td>
                            <td>{{ $row['last_out']->format('Y-m-d h:i A') }}</td>
                            <td>{{ $row['expected_out']->format('h:i A') }}</td>
                            <td>
                                @if($row['is_late'])
                                    <span class="modern-badge modern-badge-danger" style="font-size: 0.75rem;">
                                        <i class="bi bi-exclamation-triangle me-1"></i>{{ $row['late_minutes'] }} min late
                                    </span>
                                @else
                                    <span class="modern-badge modern-badge-success" style="font-size: 0.75rem;">
                                        <i class="bi bi-check2-circle me-1"></i>On Time
                                    </span>
                                @endif
                            </td>
                            <td>{{ sprintf('%02d:%02d hrs', $durationHours, $durationMinutes) }}</td>
                            <td>{{ $row['mobile_punches'] }}</td>
                            <td>{{ $row['device_punches'] }}</td>
                            <td>{{ $row['total_events'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center text-muted py-5">
                                <div class="mb-2"><i class="bi bi-clipboard2-x" style="font-size: 2rem;"></i></div>
                                <div class="fw-semibold">No records found for selected range.</div>
                                <div class="mini">Try adjusting the filters above.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const rangeFieldToggle = () => {
        const type = document.getElementById('range_type').value;
        document.querySelectorAll('.range-field').forEach(el => el.classList.add('d-none'));
        if(type === 'custom'){
            document.querySelectorAll('.range-custom').forEach(el => el.classList.remove('d-none'));
        } else if(type === 'dates'){
            document.querySelectorAll('.range-dates').forEach(el => el.classList.remove('d-none'));
        }
    };

    document.getElementById('range_type').addEventListener('change', rangeFieldToggle);
    rangeFieldToggle();

    document.getElementById('add-date-input').addEventListener('click', function(){
        const wrapper = document.getElementById('dates-wrapper');
        const input = document.createElement('input');
        input.type = 'date';
        input.name = 'dates[]';
        input.className = 'modern-form-input mb-2';
        wrapper.appendChild(input);
    });
})();
</script>
@endpush
