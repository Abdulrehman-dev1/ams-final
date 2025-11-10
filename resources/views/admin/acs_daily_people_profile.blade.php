@extends('layouts.master')

@section('content')
@php
    $formatMinutes = function (?int $minutes) {
        $minutes = max((int) $minutes, 0);
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    };
    $photo = $summary['photo_url'] ?? null;
    $lateCutoff = \Carbon\Carbon::createFromFormat('H:i:s', $summary['time_in'])->addMinutes(15)->format('h:i A');
@endphp

<div class="container-fluid">
    <div class="modern-card mb-4">
        <div class="modern-card-body d-flex flex-wrap gap-4 align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                @if(!empty($photo))
                    <img src="{{ $photo }}" alt="{{ $summary['name'] }}"
                         style="width:96px;height:96px;border-radius:16px;object-fit:cover;"
                         onerror="this.onerror=null;this.src='https://via.placeholder.com/96x96?text=%20';">
                @else
                    @php
                        $initial = mb_strtoupper(mb_substr($summary['name'], 0, 1, 'UTF-8'), 'UTF-8');
                    @endphp
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white"
                         style="width:96px;height:96px;border-radius:16px;font-size:2.5rem;font-weight:700;">
                        {{ $initial }}
                    </div>
                @endif
                <div>
                    <h2 class="mb-1" style="font-weight: 700; color: var(--gray-900);">
                        {{ $summary['name'] }}
                    </h2>
                    <div class="d-flex flex-wrap gap-2 align-items-center text-muted" style="font-size: 0.9rem;">
                        <span class="modern-badge modern-badge-primary" style="font-size:0.75rem;">
                            <i class="bi bi-person-badge me-1"></i>{{ $summary['person_code'] }}
                        </span>
                        <span class="modern-badge" style="background: rgba(59,130,246,0.12); color:#2563eb; font-size:0.75rem;">
                            <i class="bi bi-diagram-3 me-1"></i>{{ $summary['group'] }}
                        </span>
                        <span class="modern-badge" style="background: rgba(16,185,129,0.12); color:#059669; font-size:0.75rem;">
                            <i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::createFromFormat('H:i:s', $summary['time_in'])->format('h:i A') }} – {{ \Carbon\Carbon::createFromFormat('H:i:s', $summary['time_out'])->format('h:i A') }}
                        </span>
                        <span class="modern-badge" style="background: rgba(234,179,8,0.12); color:#b45309; font-size:0.75rem;">
                            <i class="bi bi-exclamation-triangle me-1"></i>Late after {{ $lateCutoff }}
                        </span>
                    </div>
                    @if(!empty($summary['latitude']) && !empty($summary['longitude']))
                        <div class="mt-2">
                            <a href="https://www.google.com/maps?q={{ $summary['latitude'] }},{{ $summary['longitude'] }}" target="_blank" class="text-decoration-none mini">
                                <i class="bi bi-geo-alt"></i> {{ number_format($summary['latitude'], 6) }}, {{ number_format($summary['longitude'], 6) }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="{{ route('acs.people.index') }}" class="btn-modern" style="background: var(--gray-600); color:#fff;">
                    <i class="bi bi-arrow-left"></i>
                    <span>Back to Directory</span>
                </a>
                <form method="GET" action="{{ route('acs.people.profile', $employee->id) }}" class="d-flex align-items-center gap-2">
                    <label class="modern-form-label mb-0" for="profileRange">Range</label>
                    <select name="range" id="profileRange" class="modern-form-input" style="width:auto;">
                        @foreach([7=>'Last 7 days',14=>'Last 14 days',30=>'Last 30 days',60=>'Last 60 days',90=>'Last 90 days'] as $days => $label)
                            <option value="{{ $days }}" {{ $rangeDays == $days ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn-modern btn-modern-success">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Apply</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="modern-widget slide-up">
                <div class="modern-widget-icon modern-widget-icon-success"><i class="bi bi-calendar-check"></i></div>
                <div class="modern-widget-label">Days Present</div>
                <div class="modern-widget-value">{{ number_format($summary['days_present']) }}</div>
                <div class="mini text-muted">Working days: {{ number_format($summary['working_days']) }}</div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="modern-widget slide-up" style="animation-delay:0.1s;">
                <div class="modern-widget-icon modern-widget-icon-warning"><i class="bi bi-alarm"></i></div>
                <div class="modern-widget-label">Late Arrivals</div>
                <div class="modern-widget-value">{{ number_format($summary['late_days']) }}</div>
                <div class="mini text-muted">Late minutes: {{ $formatMinutes($summary['late_minutes']) }}</div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="modern-widget slide-up" style="animation-delay:0.2s;">
                <div class="modern-widget-icon modern-widget-icon-info"><i class="bi bi-hourglass-split"></i></div>
                <div class="modern-widget-label">Avg Workday</div>
                <div class="modern-widget-value">{{ $summary['avg_work_formatted'] }} hrs</div>
                <div class="mini text-muted">On-time rate: {{ $summary['on_time_rate'] !== null ? number_format($summary['on_time_rate'], 1).'%' : '—' }}</div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="modern-widget slide-up" style="animation-delay:0.3s;">
                <div class="modern-widget-icon modern-widget-icon-danger"><i class="bi bi-lightning-charge"></i></div>
                <div class="modern-widget-label">Overtime (total)</div>
                <div class="modern-widget-value">{{ $formatMinutes($summary['overtime_minutes']) }} hrs</div>
                <div class="mini text-muted">Early leave: {{ $formatMinutes($summary['early_leave_minutes']) }} hrs</div>
            </div>
        </div>
    </div>

    <div class="modern-card mb-4">
        <div class="modern-card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0" style="font-weight: 600;">
                <i class="bi bi-lightbulb me-2" style="color: var(--primary);"></i>
                Insights & Suggestions
            </h5>
        </div>
        <div class="modern-card-body">
            <ul class="mb-0" style="list-style: none; padding-left:0;">
                @foreach($suggestions as $tip)
                    <li class="d-flex align-items-start gap-2 mb-2">
                        <i class="bi bi-check-circle-fill" style="color:#10b981;"></i>
                        <span>{{ $tip }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="modern-card mb-4">
        <div class="modern-card-header">
            <div class="d-flex flex-wrap gap-2">
                <button class="btn-modern btn-modern-primary profile-tab-trigger" data-target="#profile-overview">Overview</button>
                <button class="btn-modern profile-tab-trigger" data-target="#profile-daily">Daily Report</button>
                <button class="btn-modern profile-tab-trigger" data-target="#profile-salary">Salary Sheet</button>
            </div>
        </div>
        <div class="modern-card-body">
            <div id="profile-overview" class="profile-tab-pane active">
                <div class="row g-3">
                    <div class="col-xl-6">
                        <div class="modern-card" style="background: rgba(79, 70, 229, 0.05);">
                            <div class="modern-card-header">
                                <h6 class="mb-0" style="font-weight:600;">
                                    <i class="bi bi-calendar-week me-2" style="color:var(--primary);"></i>
                                    Attendance Summary ({{ $rangeLabel }})
                                </h6>
                            </div>
                            <div class="modern-card-body">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="mini text-muted">On-time Days</div>
                                        <div class="fw-semibold">{{ number_format($summary['on_time_days']) }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mini text-muted">Absent Days</div>
                                        <div class="fw-semibold">{{ number_format($summary['absent_days']) }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mini text-muted">Mobile Punches</div>
                                        <div class="fw-semibold">{{ number_format($summary['mobile_punches']) }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mini text-muted">Device Punches</div>
                                        <div class="fw-semibold">{{ number_format($summary['device_punches']) }}</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="mini text-muted">Total Punches</div>
                                        <div class="fw-semibold">{{ number_format($summary['total_punches']) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="modern-card" style="background: rgba(16, 185, 129, 0.08);">
                            <div class="modern-card-header">
                                <h6 class="mb-0" style="font-weight:600;">
                                    <i class="bi bi-geo me-2" style="color:#0f766e;"></i>
                                    Schedule & Location
                                </h6>
                            </div>
                            <div class="modern-card-body">
                                <div class="d-flex flex-column gap-2">
                                    <div>
                                        <div class="mini text-muted">Shift Window</div>
                                        <div class="fw-semibold">{{ \Carbon\Carbon::createFromFormat('H:i:s', $summary['time_in'])->format('h:i A') }} – {{ \Carbon\Carbon::createFromFormat('H:i:s', $summary['time_out'])->format('h:i A') }}</div>
                                    </div>
                                    <div>
                                        <div class="mini text-muted">Late Threshold</div>
                                        <div class="fw-semibold">{{ $lateCutoff }}</div>
                                    </div>
                                    @if(!empty($summary['latitude']) && !empty($summary['longitude']))
                                        <div>
                                            <div class="mini text-muted">Quick Map</div>
                                            <a href="https://www.google.com/maps?q={{ $summary['latitude'] }},{{ $summary['longitude'] }}" target="_blank" class="fw-semibold text-decoration-none">
                                                <i class="bi bi-map"></i> Open Google Maps
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="profile-daily" class="profile-tab-pane">
                <div class="table-responsive table-sticky">
                    <table class="modern-table js-datatable" data-no-export="true" data-page-length="25">
                        <thead>
                            <tr>
                                <th style="min-width:120px">Date</th>
                                <th class="text-nowrap">First Check-In</th>
                                <th class="text-nowrap">Expected In</th>
                                <th class="text-nowrap">Late</th>
                                <th class="text-nowrap">Last Check-Out</th>
                                <th class="text-nowrap">Expected Out</th>
                                <th class="text-nowrap">Overtime</th>
                                <th class="text-nowrap">Early Leave</th>
                                <th class="text-nowrap">Work Duration</th>
                                <th>Mobile</th>
                                <th>Device</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($summary['daily'] as $day)
                                <tr>
                                    <td>{{ $day['date']->format('Y-m-d (D)') }}</td>
                                    <td class="text-nowrap">{{ $day['first_in']->format('h:i A') }}</td>
                                    <td class="text-nowrap">{{ $day['expected_in']->format('h:i A') }}</td>
                                    <td class="text-nowrap">
                                        @if($day['late_minutes'] > 0)
                                            <span class="modern-badge modern-badge-danger" style="font-size:0.75rem;">
                                                {{ $formatMinutes($day['late_minutes']) }}
                                            </span>
                                        @else
                                            <span class="modern-badge modern-badge-success" style="font-size:0.75rem;">On Time</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{{ $day['last_out']->format('h:i A') }}</td>
                                    <td class="text-nowrap">{{ $day['expected_out']->format('h:i A') }}</td>
                                    <td class="text-nowrap">{{ $formatMinutes($day['overtime_minutes']) }}</td>
                                    <td class="text-nowrap">{{ $formatMinutes($day['early_leave_minutes']) }}</td>
                                    <td class="text-nowrap">{{ $formatMinutes($day['work_minutes']) }}</td>
                                    <td class="text-end">{{ $day['mobile_punches'] }}</td>
                                    <td class="text-end">{{ $day['device_punches'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="profile-salary" class="profile-tab-pane">
                <div class="row g-3 mb-3">
                    <div class="col-xl-3 col-sm-6">
                        <div class="modern-card">
                            <div class="modern-card-body">
                                <div class="mini text-muted">Base Salary</div>
                                <div class="fw-bold" style="font-size:1.25rem;">
                                    {{ $salarySheet['currency'] }} {{ number_format($salarySheet['base_salary'], 2) }}
                                </div>
                                <div class="mini text-muted">Daily Rate: {{ $salarySheet['currency'] }} {{ number_format($salarySheet['daily_rate'], 2) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="modern-card">
                            <div class="modern-card-body">
                                <div class="mini text-muted">Overtime Pay</div>
                                <div class="fw-bold" style="font-size:1.25rem;">
                                    {{ $salarySheet['currency'] }} {{ number_format($salarySheet['overtime_pay'], 2) }}
                                </div>
                                <div class="mini text-muted">Rate: {{ $salarySheet['currency'] }} {{ number_format($salarySheet['overtime_rate_per_hour'], 2) }}/hr</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="modern-card">
                            <div class="modern-card-body">
                                <div class="mini text-muted">Total Deductions</div>
                                <div class="fw-bold" style="font-size:1.25rem;">
                                    {{ $salarySheet['currency'] }} {{ number_format($salarySheet['total_deductions'], 2) }}
                                </div>
                                <div class="mini text-muted">Late: {{ $salarySheet['currency'] }} {{ number_format($salarySheet['late_deduction'], 2) }} • Early: {{ $salarySheet['currency'] }} {{ number_format($salarySheet['early_leave_deduction'], 2) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="modern-card" style="background: rgba(16, 185, 129, 0.08);">
                            <div class="modern-card-body">
                                <div class="mini text-muted">Net Salary</div>
                                <div class="fw-bold" style="font-size:1.5rem; color: var(--success);">
                                    {{ $salarySheet['currency'] }} {{ number_format($salarySheet['net_salary'], 2) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modern-card">
                    <div class="modern-card-header">
                        <h6 class="mb-0" style="font-weight:600;">
                            <i class="bi bi-receipt me-2" style="color:var(--primary);"></i>
                            Salary Breakdown
                        </h6>
                    </div>
                    <div class="modern-card-body">
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Metric</th>
                                        <th>Value</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Working Days</td>
                                        <td>{{ $salarySheet['working_days'] }}</td>
                                        <td>Configured month/period working days</td>
                                    </tr>
                                    <tr>
                                        <td>Days Present</td>
                                        <td>{{ $salarySheet['days_present'] }}</td>
                                        <td>Based on punches</td>
                                    </tr>
                                    <tr>
                                        <td>Absent Days</td>
                                        <td>{{ $salarySheet['absent_days'] }}</td>
                                        <td>Deducts at daily rate</td>
                                    </tr>
                                    <tr>
                                        <td>Late Minutes</td>
                                        <td>{{ $salarySheet['late_minutes'] }} min</td>
                                        <td>Penalty {{ $salarySheet['currency'] }} {{ number_format($salarySheet['late_penalty_per_minute'], 2) }}/min</td>
                                    </tr>
                                    <tr>
                                        <td>Early Leave Minutes</td>
                                        <td>{{ $salarySheet['early_leave_minutes'] }} min</td>
                                        <td>Penalty {{ $salarySheet['currency'] }} {{ number_format($salarySheet['early_leave_penalty_per_minute'], 2) }}/min</td>
                                    </tr>
                                    <tr>
                                        <td>Overtime Minutes</td>
                                        <td>{{ $salarySheet['overtime_minutes'] }} min</td>
                                        <td>Rate {{ $salarySheet['currency'] }} {{ number_format($salarySheet['overtime_rate_per_hour'], 2) }}/hr</td>
                                    </tr>
                                    <tr>
                                        <td>Absent Deduction</td>
                                        <td>{{ $salarySheet['currency'] }} {{ number_format($salarySheet['absent_deduction'], 2) }}</td>
                                        <td>Absent days × daily rate</td>
                                    </tr>
                                    <tr>
                                        <td>Late Deduction</td>
                                        <td>{{ $salarySheet['currency'] }} {{ number_format($salarySheet['late_deduction'], 2) }}</td>
                                        <td>Late minutes × per-minute penalty</td>
                                    </tr>
                                    <tr>
                                        <td>Early Leave Deduction</td>
                                        <td>{{ $salarySheet['currency'] }} {{ number_format($salarySheet['early_leave_deduction'], 2) }}</td>
                                        <td>Early leave minutes × per-minute penalty</td>
                                    </tr>
                                    <tr>
                                        <td>Overtime Pay</td>
                                        <td>{{ $salarySheet['currency'] }} {{ number_format($salarySheet['overtime_pay'], 2) }}</td>
                                        <td>Overtime minutes × hourly rate</td>
                                    </tr>
                                    <tr>
                                        <td>Gross Salary</td>
                                        <td><strong>{{ $salarySheet['currency'] }} {{ number_format($salarySheet['gross'], 2) }}</strong></td>
                                        <td>Base prior to deductions and overtime</td>
                                    </tr>
                                    <tr>
                                        <td>Total Deductions</td>
                                        <td><strong>{{ $salarySheet['currency'] }} {{ number_format($salarySheet['total_deductions'], 2) }}</strong></td>
                                        <td>Absent + Late + Early Leave</td>
                                    </tr>
                                    <tr>
                                        <td>Net Salary</td>
                                        <td><strong>{{ $salarySheet['currency'] }} {{ number_format($salarySheet['net_salary'], 2) }}</strong></td>
                                        <td>Gross - Deductions + Overtime</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mini text-muted mt-2">
                            Adjust trial values with query params: <code>?base=60000&amp;cur=PKR</code>. Rates can be configured in <code>config/attendance.php</code> as <code>late_penalty_per_minute</code>, <code>overtime_rate_per_hour</code>, and <code>early_leave_penalty_per_minute</code>.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modern-card">
        <div class="modern-card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0" style="font-weight:600;">
                <i class="bi bi-clock-history me-2" style="color: var(--primary);"></i>
                Recent Activity
            </h5>
            <div class="mini text-muted">Most recent 25 punches</div>
        </div>
        <div class="table-responsive table-sticky">
            <table class="modern-table js-datatable" data-no-export="true" data-page-length="25">
                <thead>
                    <tr>
                        <th>#</th>
                        <th style="min-width:160px">Date &amp; Time</th>
                        <th>Source</th>
                        <th style="min-width:220px">Device</th>
                        <th style="min-width:220px">Reader</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentEvents as $index => $event)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="text-nowrap">{{ $event['timestamp']->timezone($tz)->format('Y-m-d h:i A') }}</td>
                            <td>
                                <span class="modern-badge {{ $event['source'] === 'Mobile' ? 'modern-badge-info' : 'modern-badge-primary' }}" style="font-size:0.75rem;">
                                    {{ $event['source'] }}
                                </span>
                            </td>
                            <td>{{ $event['device_name'] ?: '—' }}</td>
                            <td>{{ $event['card_reader_name'] ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const triggers = document.querySelectorAll('.profile-tab-trigger');
    const panes = document.querySelectorAll('.profile-tab-pane');

    triggers.forEach(trigger => {
        trigger.addEventListener('click', () => {
            triggers.forEach(btn => btn.classList.remove('btn-modern-primary'));
            panes.forEach(pane => pane.classList.remove('active'));
            trigger.classList.add('btn-modern-primary');
            const targetSelector = trigger.dataset.target;
            const target = document.querySelector(targetSelector);
            if (target) {
                target.classList.add('active');
            }
        });
    });
})();
</script>
<style>
    .profile-tab-pane { display: none; }
    .profile-tab-pane.active { display: block; }
</style>
@endpush

