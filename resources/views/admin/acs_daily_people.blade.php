@extends('layouts.master')

@section('content')
@push('styles')
<style>
    .equal-widget {
        display: flex;
        flex-direction: column;
        height: 100%;
        padding: 1rem;
        margin-top: 1rem;
        border-radius: 22px;
    }

    .avatar-wrapper {
        width: 44px;
        height: 44px;
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .avatar-wrapper img {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        display: block;
    }

    .letter-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #eef2ff;
        color: #4c1d95;
        font-weight: 600;
        display: none;
        align-items: center;
        justify-content: center;
        text-transform: uppercase;
    }

    .avatar-wrapper.avatar-missing .letter-avatar {
        display: inline-flex;
    }

    .employee-detail {
        display: flex;
        flex-direction: column;
        padding: 0.5rem;
        text-align: left;
    }

    .employee-detail small {
        display: block;
    }

    .stats-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: 180px 1fr 1fr;
        grid-template-areas:
            "media card1 card2"
            "name  name  name"
            "code  card3 card4"
            "department department department";
    }

    .stats-media {
        grid-area: media;
        background: #f8f9fb;
        border-radius: 16px;
        padding: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 180px;
    }

    .stats-avatar {
        width: 100%;
        max-width: 120px;
        aspect-ratio: 1;
        border-radius: 16px;
        background: #eef2ff;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .stats-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .stats-avatar .letter-avatar {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: #4c1d95;
        background: transparent;
    }

    .stats-name {
        grid-area: name;
        padding: 0.75rem 1rem;
        background: #f8f9fb;
        border-radius: 16px;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .stats-code {
        grid-area: code;
        padding: 0.75rem 1rem;
        background: #f8f9fb;
        border-radius: 16px;
        font-weight: 600;
    }

    .stats-department {
        grid-area: department;
        padding: 0.75rem 1rem;
        background: #f8f9fb;
        border-radius: 16px;
        font-size: 0.95rem;
    }

    .stats-card {
        grid-area: auto;
        background: #fff;
        border-radius: 18px;
        padding: 1rem;
        box-shadow: 0 6px 20px rgba(15, 23, 42, 0.08);
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .stats-card .label {
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .stats-card .value {
        font-size: 1.8rem;
        font-weight: 700;
    }

    .stats-card.accent-primary .label,
    .stats-card.accent-primary .value { color: #4f46e5; }
    .stats-card.accent-warning .label,
    .stats-card.accent-warning .value { color: #f59e0b; }
    .stats-card.accent-danger .label,
    .stats-card.accent-danger .value { color: #ef4444; }
    .stats-card.accent-success .label,
    .stats-card.accent-success .value { color: #10b981; }

    .stats-card.present { grid-area: card1; }
    .stats-card.absent { grid-area: card2; }
    .stats-card.overtime { grid-area: card3; }
    .stats-card.work { grid-area: card4; }

    .token-tab-list {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .token-tab {
        padding: 0.4rem 1rem;
        border-radius: 999px;
        border: 1px solid rgba(79, 70, 229, 0.2);
        background: rgba(79, 70, 229, 0.08);
        color: #4f46e5;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .token-output {
        background: #0f172a;
        color: #e2e8f0;
        padding: 1rem;
        border-radius: 12px;
        font-size: 0.85rem;
        white-space: pre-wrap;
        max-height: 320px;
        overflow-y: auto;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
            grid-template-areas:
                "media"
                "card1"
                "card2"
                "name"
                "code"
                "card3"
                "card4"
                "department";
        }
        .stats-media {
            justify-content: center;
        }
        .stats-card.present,
        .stats-card.absent,
        .stats-card.overtime,
        .stats-card.work {
            width: 100%;
        }
    }

    .table-sticky {
        overflow-x: auto !important;
        overflow-y: visible !important;
        width: 100% !important;
        max-width: 100% !important;
        display: block !important;
        -webkit-overflow-scrolling: touch;
        position: relative;
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    
    .modern-card .table-sticky {
        margin: 0;
        padding: 0;
    }
    
    /* DataTables wrapper override */
    .table-sticky .dataTables_wrapper {
        overflow-x: visible !important;
        overflow-y: visible !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    
    .table-sticky .dataTables_scroll {
        overflow-x: visible !important;
        overflow-y: visible !important;
    }
    
    .table-sticky .dataTables_scrollBody {
        overflow-x: visible !important;
        overflow-y: visible !important;
    }

    .table-sticky table {
        min-width: 1200px;
        width: 100%;
        margin: 0;
        display: table;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .table-sticky table th,
    .table-sticky table td {
        white-space: nowrap;
    }
    
    .table-sticky table th:nth-child(1),
    .table-sticky table td:nth-child(1) {
        width: 70px;
    }
    
    .table-sticky table th:nth-child(6),
    .table-sticky table td:nth-child(6) {
        min-width: 120px;
        white-space: normal;
    }
    
    .table-sticky::-webkit-scrollbar {
        height: 8px;
        display: block;
    }
    
    .table-sticky::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
        margin: 0 1rem;
    }
    
    .table-sticky::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }
    
    .table-sticky::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    
    /* SweetAlert Modal Styling - Higher specificity */
    body .swal2-container .swal2-popup.swal2-modal {
        border-radius: 20px !important;
        padding: 2rem !important;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
        max-width: 800px !important;
    }
    
    body .swal2-container .swal2-popup .swal2-title {
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        color: #1f2937 !important;
        margin-bottom: 1.5rem !important;
        padding-bottom: 1rem !important;
        border-bottom: 2px solid #e5e7eb !important;
    }
    
    body .swal2-container .swal2-popup .swal2-html-container {
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
    }
    #swal2-html-container{
        overflow: hidden;
    }
    .employee-stats-modal {
        padding: 0.5rem 0;
    }
    
    .employee-stats-modal .stats-form {
        background: #f8f9fb;
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1rem;
    }
    
    .employee-stats-modal .modern-form-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--gray-700, #374151);
        margin-bottom: 0.5rem;
        display: block;
    }
    .employee-stats-modal{
        overflow: hidden;
    }
    
    .employee-stats-modal .modern-form-input {
        width: 100%;
        padding: 0.625rem 0.875rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.875rem;
        transition: all 0.2s;
        background: white;
    }
    
    .employee-stats-modal .modern-form-input:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .employee-stats-modal .stats-refresh {
        width: 100%;
        padding: 0.75rem 1rem;
        background: #1f2937;
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.2s;
        margin-bottom: 1rem;
    }
    
    .employee-stats-modal .stats-refresh:hover {
        background: #111827;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .employee-stats-modal .stats-content {
        min-height: 200px;
    }
    
    .swal2-confirm,
    .swal2-confirm-custom {
        background: #1f2937 !important;
        border-radius: 10px !important;
        padding: 0.75rem 2rem !important;
        font-weight: 600 !important;
        font-size: 0.875rem !important;
        transition: all 0.2s !important;
        border: none !important;
        color: white !important;
    }
    
    .swal2-confirm:hover,
    .swal2-confirm-custom:hover {
        background: #111827 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
    }
    
    .swal2-actions {
        margin-top: 1.5rem !important;
        gap: 0.75rem !important;
    }
    
    /* Stats Grid improvements for modal */
    .swal2-popup .stats-grid {
        gap: 0.75rem;
    }
    
    .swal2-popup .stats-card {
        padding: 0.875rem;
        border-radius: 12px;
    }
    
    .swal2-popup .stats-card .label {
        font-size: 0.75rem;
        margin-bottom: 0.25rem;
    }
    
    .swal2-popup .stats-card .value {
        font-size: 1.5rem;
    }
    
    .swal2-popup .stats-name {
        font-size: 1rem;
        padding: 0.625rem 0.875rem;
    }
    
    .swal2-popup .stats-code,
    .swal2-popup .stats-department {
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
    }
    
    .swal2-popup .stats-media {
        min-height: 150px;
        padding: 0.75rem;
    }
    
    .swal2-popup .stats-avatar {
        max-width: 100px;
    }
    
    /* Employee Stats Container */
    .employee-stats-container {
        padding: 0;
    }
    
    .employee-info-box {
        background: #f8f9fb;
        padding: 1.5rem;
        border-radius: 12px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        text-align: left;
    }
    
    .employee-avatar-wrapper {
        display: flex;
        justify-content: flex-start;
    }
    
    .employee-initial-avatar {
        display: flex;
    }
    
    .employee-photo-avatar {
        border: 2px solid #e5e7eb;
    }
    
    .swal2-popup .modern-widget {
        margin: 0;
    }
    
    .stats-widget-fixed {
        height: 140px;
        display: flex;
        flex-direction: column;
    }
</style>
@endpush
@php
    $viewMode = $viewMode ?? request()->query('view', 'list');
    $reportRangeDays = $reportRangeDays ?? 30;
    $reportRangeLabel = $reportRangeLabel ?? '';
@endphp

<div class="container-fluid">
    @if($viewMode === 'reports')
        <div class="modern-card mb-4">
            <div class="modern-card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h2 class="mb-1" style="font-weight: 700; color: var(--gray-900);">
                        <i class="bi bi-bar-chart-line me-2" style="color: var(--primary);"></i>
                        Employee Attendance Reports
                    </h2>
                    <p class="text-muted mb-0" style="font-size: 0.875rem;">
                        Performance snapshot across {{ strtolower($reportRangeLabel) }}. Filter employees in the directory to focus this view.
                    </p>
                </div>
                <form class="d-flex align-items-center gap-2 flex-wrap" method="GET" action="{{ route('acs.people.index') }}">
                    <input type="hidden" name="view" value="reports">
                    <label class="modern-form-label mb-0">Range</label>
                    <select name="report_range" class="modern-form-input" style="width:auto;">
                        <option value="7" {{ $reportRangeDays == 7 ? 'selected' : '' }}>Last 7 days</option>
                        <option value="14" {{ $reportRangeDays == 14 ? 'selected' : '' }}>Last 14 days</option>
                        <option value="30" {{ $reportRangeDays == 30 ? 'selected' : '' }}>Last 30 days</option>
                        <option value="60" {{ $reportRangeDays == 60 ? 'selected' : '' }}>Last 60 days</option>
                        <option value="90" {{ $reportRangeDays == 90 ? 'selected' : '' }}>Last 90 days</option>
                    </select>
                    <button type="submit" class="btn-modern btn-modern-success">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Update</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6">
                <div class="modern-widget slide-up">
                    <div class="modern-widget-icon modern-widget-icon-primary">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="modern-widget-label">Employees Analysed</div>
                    <div class="modern-widget-value">{{ number_format($reportTotals['employees']) }}</div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="modern-widget slide-up" style="animation-delay:0.1s;">
                    <div class="modern-widget-icon modern-widget-icon-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="modern-widget-label">On-Time Rate</div>
                    <div class="modern-widget-value">
                        {{ $reportTotals['on_time_rate'] !== null ? number_format($reportTotals['on_time_rate'], 1).'%' : '—' }}
                    </div>
                    <div class="mini text-muted">Late days: {{ number_format($reportTotals['late_days']) }}</div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="modern-widget slide-up" style="animation-delay:0.2s;">
                    <div class="modern-widget-icon modern-widget-icon-info">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="modern-widget-label">Avg Workday</div>
                    <div class="modern-widget-value">
                        {{ $reportTotals['avg_work_formatted'] ?? '00:00' }} hrs
                    </div>
                    <div class="mini text-muted">Working days tracked: {{ number_format($reportTotals['working_days']) }}</div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="modern-widget slide-up" style="animation-delay:0.3s;">
                    <div class="modern-widget-icon modern-widget-icon-warning">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                    <div class="modern-widget-label">Overtime (Total)</div>
                    <div class="modern-widget-value">{{ $reportTotals['overtime_formatted'] ?? '00:00' }} hrs</div>
                    <div class="mini text-muted">Early leave: {{ $reportTotals['early_leave_formatted'] ?? '00:00' }} hrs</div>
                </div>
            </div>
        </div>

        <div class="modern-card">
            <div class="modern-card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0" style="font-weight: 600;">
                    <i class="bi bi-table me-2" style="color: var(--primary);"></i>
                    Team Attendance Breakdown
                </h5>
                <div class="mini text-muted">{{ $reportRangeLabel }}</div>
            </div>
            <div class="table-responsive">
                <table class="modern-table js-datatable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee</th>
                            <th>Person Code</th>
                            <th>Team</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>On-Time %</th>
                            <th>Late Days</th>
                            <th>Avg Time In</th>
                            <th>Avg Time Out</th>
                            <th>Avg Workday</th>
                            <th>Overtime</th>
                            <th>Early Leave</th>
                            <th>Mobile</th>
                            <th>Device</th>
                            <th>Total Punches</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportRows as $index => $row)
                            @php
                                $onTimeRate = $row['on_time_rate'] !== null ? number_format($row['on_time_rate'], 1).'%' : '—';
                                $avgWork = $row['avg_work_formatted'] ?? '00:00';
                                $overtimeFormatted = sprintf('%02d:%02d', intdiv(max($row['overtime_minutes'], 0), 60), max($row['overtime_minutes'], 0) % 60);
                                $earlyLeaveFormatted = sprintf('%02d:%02d', intdiv(max($row['early_leave_minutes'], 0), 60), max($row['early_leave_minutes'], 0) % 60);
                                $lateMinutesFormatted = sprintf('%02d:%02d', intdiv(max($row['late_minutes'], 0), 60), max($row['late_minutes'], 0) % 60);
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <a href="{{ $row['profile_url'] }}" class="fw-semibold text-decoration-none">
                                        {{ $row['name'] }}
                                    </a>
                                </td>
                                <td>{{ $row['person_code'] }}</td>
                                <td>{{ $row['group'] }}</td>
                                <td>{{ number_format($row['days_present']) }}</td>
                                <td>{{ number_format($row['absent_days']) }}</td>
                                <td>{{ $onTimeRate }}</td>
                                <td>
                                    <span class="modern-badge {{ $row['late_days'] > 0 ? 'modern-badge-danger' : 'modern-badge-success' }}" style="font-size:0.75rem;">
                                        {{ number_format($row['late_days']) }} / {{ $lateMinutesFormatted }}
                                    </span>
                                </td>
                                <td>{{ $row['avg_check_in'] }}</td>
                                <td>{{ $row['avg_check_out'] }}</td>
                                <td>{{ $avgWork }} hrs</td>
                                <td>{{ $overtimeFormatted }} hrs</td>
                                <td>{{ $earlyLeaveFormatted }} hrs</td>
                                <td>{{ number_format($row['mobile_punches']) }}</td>
                                <td>{{ number_format($row['device_punches']) }}</td>
                                <td>{{ number_format($row['total_punches']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="16" class="text-center text-muted py-5">
                                    <div class="mb-2"><i class="bi bi-clipboard-data" style="font-size:2rem;"></i></div>
                                    <div class="fw-semibold">No attendance data in the selected range.</div>
                                    <div class="mini">Try adjusting the date range or syncing recent attendance.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @else
        <div class="modern-card mb-4">
            <div class="modern-card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-6 col-md-6">
                        <h2 class="mb-1" style="font-weight: 700; color: var(--gray-900);">
                            <i class="bi bi-people me-2" style="color: var(--primary);"></i>
                            Employees List
                        </h2>
                        <p class="text-muted mb-0" style="font-size: 0.875rem;">Manage employees synced from HikCentral Connect</p>
                    </div>
                    <div class="col-lg-6 col-md-6">
                        <div class="d-flex justify-content-end flex-nowrap">
                            <a href="{{ route('acs.people.index') }}" class="btn-modern" style="background: var(--gray-600); color: white; white-space: nowrap;">
                                <i class="bi bi-arrow-clockwise"></i>
                                <span>Reload</span>
                            </a>
                            <form action="{{ route('acs.people.syncNow') }}" method="POST"
                                  class="d-inline js-submit-loading"
                                  style="margin-left: 12px;"
                                  data-loading-text='<span class="modern-loader"></span> Syncing...'>
                                @csrf
                                <button type="submit" class="btn-modern btn-modern-primary" style="white-space: nowrap;">
                                    <i class="bi bi-cloud-arrow-down"></i>
                                    <span>Sync Now</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @if($page->total() === 0)
            <div class="modern-alert modern-alert-info fade-in mb-4">
                <div>
                    <i class="bi bi-info-circle-fill" style="font-size: 1.5rem;"></i>
                </div>
                <div style="flex: 1;">
                    <strong style="font-size: 1rem; display: block; margin-bottom: 0.5rem;">No Employees Found</strong>
                    <div style="font-size: 0.875rem; margin-bottom: 0.5rem;">
                        The employee list is empty. Click "Sync Now" to fetch employees from HikCentral Connect API.
                    </div>
                    <div style="font-size: 0.875rem; padding: 0.75rem; background: rgba(255,255,255,0.5); border-radius: var(--radius); margin-top: 0.5rem;">
                        <strong>Requirements:</strong>
                        <ul class="mb-0 mt-2" style="padding-left: 1.5rem;">
                            <li>HIK_TOKEN must be configured in <code>.env</code></li>
                            <li>HIK_BASE_URL should point to your HikCentral Connect API</li>
                            <li>Network access to the HikCentral server</li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div class="modern-card mb-4">
            <div class="modern-card-header">
                <h5 class="mb-0" style="font-weight: 600;">
                    <i class="bi bi-funnel me-2" style="color: var(--primary);"></i>
                    Filters
                </h5>
            </div>
            <form method="POST" action="{{ route('acs.people.filter') }}" class="modern-card-body">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="modern-form-label">Name</label>
                        <input type="text" name="name" value="{{ $filters['name'] ?? '' }}" class="modern-form-input" placeholder="Search by name">
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="modern-form-label">Person Code</label>
                        <input type="text" name="person_code" value="{{ $filters['person_code'] ?? '' }}" class="modern-form-input" placeholder="Search by code">
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="modern-form-label">Status</label>
                        @php $statusFilter = $filters['status'] ?? ''; @endphp
                        <select name="status" class="modern-form-input">
                            <option value="">All</option>
                            <option value="enabled" {{ $statusFilter === 'enabled' ? 'selected' : '' }}>Enabled</option>
                            <option value="disabled" {{ $statusFilter === 'disabled' ? 'selected' : '' }}>Disabled</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="modern-form-label">Per Page</label>
                        @php $pp = (int)($filters['perPage'] ?? 10); @endphp
                        <select name="perPage" class="modern-form-input">
                            @foreach([10,25,50,100,200] as $opt)
                                <option value="{{ $opt }}" {{ $pp === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-12 d-flex justify-content-end" style="margin-top: 12px;">
                        <button type="submit" class="btn-modern btn-modern-success">
                            <i class="bi bi-funnel"></i>
                            <span>Apply</span>
                        </button>
                        <button type="submit"
                                class="btn-modern"
                                style="background: var(--gray-600); color: white; margin-left: 12px;"
                                formaction="{{ route('acs.people.filterReset') }}"
                                formmethod="POST">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            <span>Reset</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

  @php
    $chips = [];
    if(!empty($filters['name']))        $chips[] = ['label'=>'Name','val'=>$filters['name'],'dot'=>'#0d6efd'];
    if(!empty($filters['person_code'])) $chips[] = ['label'=>'Code','val'=>$filters['person_code'],'dot'=>'#20c997'];
            if(!empty($filters['status']))      $chips[] = ['label'=>'Status','val'=>ucfirst($filters['status']),'dot'=>$filters['status']==='enabled'?'#10b981':'#ef4444'];
  @endphp
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div class="d-flex flex-wrap gap-2">
                <span class="modern-badge modern-badge-primary" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                    <i class="bi bi-list-ul me-1"></i> Total <strong>{{ $page->total() }}</strong>
                </span>
      @foreach($chips as $c)
                    <span class="modern-badge" style="background: rgba(99, 102, 241, 0.1); color: var(--primary); font-size: 0.875rem; padding: 0.5rem 1rem;">
                        <span class="dot" style="background:{{ $c['dot'] }}; width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 0.5rem;"></span>
                        {{ $c['label'] }}: <strong>{{ $c['val'] }}</strong>
                    </span>
      @endforeach
    </div>
  </div>

        <div class="modern-card">
            <div class="modern-card-header">
                <h5 class="mb-0" style="font-weight: 600;">
                    <i class="bi bi-table me-2" style="color: var(--primary);"></i>
                    Employees
                </h5>
            </div>
            <div class="table-sticky modern-table-wrapper">
                <table class="modern-table js-datatable" data-page-length="25" data-ordering="false" data-info="false" data-paging="false" data-responsive="false">
                    <thead>
                        <tr>
                            <th style="width:70px">Photo</th>
                            <th style="min-width:200px">Full Name</th>
                            <th style="min-width:160px">Person Code</th>
                            <th class="text-nowrap" style="min-width:180px">Time In</th>
                            <th class="text-nowrap" style="min-width:150px">Time Out</th>
                            <th style="min-width:120px">Status</th>
                            <th style="width:200px; text-align: center;">Actions</th>
                        </tr>
        </thead>
        <tbody>
        @forelse($page as $i => $row)
         @php
  $photo = $row['photo_url'] ?? null;
                                $nameRaw = trim(($row['full_name'] ?? '') ?: trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')));
  $initial = $nameRaw !== ''
      ? mb_strtoupper(mb_substr($nameRaw, 0, 1, 'UTF-8'), 'UTF-8')
                                    : (!empty($row['person_code'])
          ? mb_strtoupper(mb_substr($row['person_code'], 0, 1, 'UTF-8'), 'UTF-8')
                                        : '—');
                                $name = $nameRaw !== '' ? $nameRaw : '—';
                                $timeInValue = $row['time_in'] ?? '09:00:00';
                                if (strlen($timeInValue) === 5) { $timeInValue .= ':00'; }
                                $timeOutValue = $row['time_out'] ?? '19:00:00';
                                if (strlen($timeOutValue) === 5) { $timeOutValue .= ':00'; }
                                $timeInFormatted = \Carbon\Carbon::createFromFormat('H:i:s', $timeInValue)->format('h:i A');
                                $timeOutFormatted = \Carbon\Carbon::createFromFormat('H:i:s', $timeOutValue)->format('h:i A');
@endphp
                            <tr style="{{ !($row['is_enabled'] ?? true) ? 'opacity: 0.7;' : '' }}">
            <td>
                <div class="position-relative" style="width:48px;height:48px;">
                    <div class="d-inline-flex align-items-center justify-content-center w-100 h-100 {{ ($row['is_enabled'] ?? true) ? 'bg-primary' : 'bg-secondary' }} text-white"
                         style="border-radius:8px;font-weight:600;">
                        {{ $initial }}
                    </div>
  @if(!empty($photo))
                    <img src="{{ $photo }}"
                         alt="photo"
                         style="position:absolute;top:0;left:0;width:48px;height:48px;object-fit:cover;border-radius:8px;display:none;{{ !($row['is_enabled'] ?? true) ? 'filter: grayscale(100%);' : '' }}"
                         onload="this.style.display='block';this.previousElementSibling.classList.add('d-none');"
                         onerror="this.onerror=null;this.remove();">
  @endif
                </div>
</td>
                                <td class="align-middle">
                                    <div class="fw-semibold">{{ $name }}</div>
                                    @if(!($row['is_enabled'] ?? true))
                                        <small class="text-muted" style="font-size: 0.75rem;">
                                            <i class="bi bi-eye-slash"></i> Disabled
                                        </small>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    <span class="modern-badge modern-badge-primary" style="font-size: 0.75rem;">
                                        {{ $row['person_code'] ?? '—' }}
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <span class="modern-badge modern-badge-primary" style="font-size: 0.75rem;">
                                        <i class="bi bi-clock me-1"></i>{{ $timeInFormatted }}
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <span class="modern-badge modern-badge-info" style="font-size: 0.75rem;">
                                        <i class="bi bi-clock-history me-1"></i>{{ $timeOutFormatted }}
                                    </span>
                                </td>
                                <td>
                                    @if($row['is_enabled'] ?? true)
                                        <span class="modern-badge modern-badge-success">
                                            <i class="bi bi-check-circle me-1"></i>Enabled
                                        </span>
                                    @else
                                        <span class="modern-badge modern-badge-danger">
                                            <i class="bi bi-x-circle me-1"></i>Disabled
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <a href="{{ route('acs.people.edit', $row['id']) }}"
                                           class="btn-modern btn-modern-primary"
                                           style="padding: 0.375rem 0.75rem; font-size: 0.875rem;"
                                           title="Edit Employee">
                                            <i class="bi bi-pencil"></i>
                                            <span>Edit</span>
                                        </a>
                                        <a href="#"
                                           class="btn-modern btn-modern-info js-view-employee"
                                           style="padding: 0.375rem 0.75rem; font-size: 0.875rem;"
                                           data-stats-url="{{ route('acs.people.stats', $row['id']) }}"
                                           title="View Employee Stats">
                                            <i class="bi bi-eye"></i>
                                            <span>View</span>
                                        </a>
                                        <form action="{{ route('acs.people.toggleStatus', $row['id']) }}"
                                              method="POST"
                                              class="d-inline"
                                              onsubmit="return confirm('Are you sure you want to {{ ($row['is_enabled'] ?? true) ? 'disable' : 'enable' }} this employee?');">
                                            @csrf
                                            <button type="submit"
                                                    class="btn-modern {{ ($row['is_enabled'] ?? true) ? 'btn-modern-warning' : 'btn-modern-success' }}"
                                                    style="padding: 0.375rem 0.75rem; font-size: 0.875rem;"
                                                    title="{{ ($row['is_enabled'] ?? true) ? 'Disable' : 'Enable' }} Employee">
                                                <i class="bi bi-{{ ($row['is_enabled'] ?? true) ? 'toggle-on' : 'toggle-off' }}"></i>
                                                <span>{{ ($row['is_enabled'] ?? true) ? 'Disable' : 'Enable' }}</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
          </tr>
        @empty
          <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <div class="mb-3">
                                        <i class="bi bi-inbox" style="font-size: 3rem; color: #d1d5db;"></i>
                                    </div>
                                    <div class="mb-2" style="font-size: 1.1rem; font-weight: 600;">No employees found</div>
                                    <div class="small mb-3">The employee list is empty. You need to sync employees from HikCentral Connect.</div>
                                    <div class="d-flex justify-content-center gap-2">
                                        <form action="{{ route('acs.people.syncNow') }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-cloud-arrow-down me-2"></i>Sync Employees from HikCentral
                                            </button>
                                        </form>
                                        <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary">
                                            <i class="bi bi-people me-2"></i>View Legacy Employees
                                        </a>
                                    </div>
                                    <div class="mt-3 small text-muted">
                                        <strong>Note:</strong> Make sure HIK_TOKEN is configured in your .env file
                                    </div>
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
            @if(method_exists($page, 'total'))
            <div class="modern-card-footer d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div style="font-size: 0.875rem; color: var(--gray-600); font-weight: 500;">
                    <i class="bi bi-list-ul me-2"></i>Total: <strong>{{ $page->total() }}</strong> employees
                </div>
                <div class="modern-pagination">
                    {{ $page->links() }}
                </div>
            </div>
            @endif
        </div>
    @endif
</div>
@endsection

@section('script-bottom')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  @if(session('alert_type'))
    Swal.fire({
      icon: '{{ session("alert_type") }}',
      title: '{{ session("alert_title") }}',
      html: '<div style="text-align: left;">' +
            '<p>{{ session("alert_message") }}</p>' +
            @if(session('alert_stats'))
            '<hr style="margin: 10px 0;">' +
            '<small class="text-muted">{{ session("alert_stats") }}</small>' +
            @endif
            '</div>',
      showConfirmButton: true,
      confirmButtonText: 'OK',
      timer: 5000,
      timerProgressBar: true,
      customClass: {
        confirmButton: 'btn btn-primary'
      }
    });
  @endif

  document.addEventListener('DOMContentLoaded', function () {
      // Fix DataTables overflow issue for table-sticky containers
      function fixTableStickyOverflow() {
          document.querySelectorAll('.table-sticky').forEach(function(container) {
              // Ensure container maintains overflow
              container.style.overflowX = 'auto';
              container.style.overflowY = 'visible';
              container.style.width = '100%';
              container.style.maxWidth = '100%';
              
              // Fix DataTables wrapper if it exists
              var wrapper = container.querySelector('.dataTables_wrapper');
              if (wrapper) {
                  wrapper.style.overflowX = 'visible';
                  wrapper.style.overflowY = 'visible';
                  wrapper.style.width = '100%';
                  wrapper.style.maxWidth = '100%';
              }
              
              // Fix DataTables scroll containers
              var scrollBody = container.querySelector('.dataTables_scrollBody');
              if (scrollBody) {
                  scrollBody.style.overflowX = 'visible';
                  scrollBody.style.overflowY = 'visible';
              }
              
              // Ensure table has minimum width to show all columns
              var table = container.querySelector('table');
              if (table) {
                  table.style.minWidth = '1200px';
                  table.style.width = '100%';
                  
                  // Remove collapsed class if present
                  table.classList.remove('collapsed', 'dtr-inline');
                  
                  // Ensure all columns are visible
                  var ths = table.querySelectorAll('thead th');
                  var tds = table.querySelectorAll('tbody td');
                  ths.forEach(function(th, index) {
                      if (th.style.display === 'none') {
                          th.style.display = '';
                      }
                      // Remove data-priority that causes responsive collapsing
                      th.removeAttribute('data-priority');
                  });
                  tds.forEach(function(td, index) {
                      if (td.style.display === 'none') {
                          td.style.display = '';
                      }
                      // Remove data-priority
                      td.removeAttribute('data-priority');
                  });
                  
                  // Ensure all rows are visible
                  var rows = table.querySelectorAll('tbody tr');
                  rows.forEach(function(row) {
                      row.style.display = '';
                      row.classList.remove('dtr-inline', 'collapsed');
                      // Show all child elements
                      var rowTds = row.querySelectorAll('td');
                      rowTds.forEach(function(td) {
                          td.style.display = '';
                      });
                  });
              }
          });
      }
      
      // Disable DataTables responsive mode for table-sticky tables
      function disableDataTablesResponsive() {
          document.querySelectorAll('.table-sticky table.js-datatable').forEach(function(table) {
              if ($.fn.DataTable && $.fn.DataTable.isDataTable(table)) {
                  var dt = $(table).DataTable();
                  if (dt.responsive) {
                      dt.responsive.disable();
                  }
              }
          });
      }
      
      // Run immediately
      fixTableStickyOverflow();
      disableDataTablesResponsive();
      
      // Run after a short delay to catch DataTables initialization
      setTimeout(function() {
          fixTableStickyOverflow();
          disableDataTablesResponsive();
      }, 100);
      setTimeout(function() {
          fixTableStickyOverflow();
          disableDataTablesResponsive();
      }, 500);
      setTimeout(function() {
          fixTableStickyOverflow();
          disableDataTablesResponsive();
      }, 1000);
      
      document.querySelectorAll('.js-submit-loading').forEach(function (form) {
          form.addEventListener('submit', function () {
              const btn = form.querySelector('button[type="submit"]');
              if (!btn) {
                  return;
              }
              const loadingHtml = form.getAttribute('data-loading-text') || 'Processing...';
              btn.disabled = true;
              btn.innerHTML = loadingHtml;
          });
      });

      document.body.addEventListener('click', function (event) {
          const trigger = event.target.closest('.js-view-employee');
          if (!trigger) {
              return;
          }
          event.preventDefault();
          openEmployeeStatsModal(trigger);
      });
  });

  function openEmployeeStatsModal(button) {
      if (typeof Swal === 'undefined') {
          return;
      }
      const statsUrl = button.getAttribute('data-stats-url');
      if (!statsUrl) {
          return;
      }

      const defaults = getDefaultStatsDates();
      const modalHtml = `
          <div class="employee-stats-modal" data-stats-url="${statsUrl}">
              <div class="row g-2 stats-form mb-3">
                  <div class="col-md-4">
                      <label class="modern-form-label">From</label>
                      <input type="date" name="start_date" class="modern-form-input" value="${defaults.start}">
                  </div>
                  <div class="col-md-4">
                      <label class="modern-form-label">To</label>
                      <input type="date" name="end_date" class="modern-form-input" value="${defaults.end}">
                  </div>
                  <div class="col-md-4 d-flex align-items-end">
                      <button type="button" class="stats-refresh w-100">
                          Apply Filter
                      </button>
                  </div>
              </div>
              <div class="stats-content mt-3">
                  ${buildStatsLoader()}
              </div>
          </div>
      `;

      Swal.fire({
          title: '<i class="bi bi-person-badge me-2" style="color: #4f46e5;"></i>Employee Snapshot',
          html: modalHtml,
          width: 800,
          showConfirmButton: true,
          confirmButtonText: 'Close',
          focusConfirm: false,
          customClass: {
              popup: 'employee-stats-popup',
              confirmButton: 'swal2-confirm-custom'
          },
          buttonsStyling: false,
          didOpen: (modalEl) => {
              // Apply inline styles to ensure they override SweetAlert defaults
              const popup = modalEl.closest('.swal2-popup');
              if (popup) {
                  popup.style.borderRadius = '20px';
                  popup.style.padding = '2rem';
                  popup.style.boxShadow = '0 20px 60px rgba(0, 0, 0, 0.3)';
              }
              
              const title = modalEl.querySelector('.swal2-title');
              if (title) {
                  title.style.fontSize = '1.5rem';
                  title.style.fontWeight = '700';
                  title.style.color = '#1f2937';
                  title.style.marginBottom = '1.5rem';
                  title.style.paddingBottom = '1rem';
                  title.style.borderBottom = '2px solid #e5e7eb';
              }
              
              const htmlContainer = modalEl.querySelector('.swal2-html-container');
              if (htmlContainer) {
                  htmlContainer.style.margin = '0';
                  htmlContainer.style.padding = '0';
                  htmlContainer.style.overflow = 'hidden';
              }
              
              const confirmBtn = modalEl.querySelector('.swal2-confirm');
              if (confirmBtn) {
                  confirmBtn.style.background = '#1f2937';
                  confirmBtn.style.borderRadius = '10px';
                  confirmBtn.style.padding = '0.75rem 2rem';
                  confirmBtn.style.fontWeight = '600';
                  confirmBtn.style.fontSize = '0.875rem';
                  confirmBtn.style.border = 'none';
                  confirmBtn.style.color = 'white';
              }
              
              const container = modalEl.querySelector('.employee-stats-modal');
              const content = container.querySelector('.stats-content');
              const refreshBtn = container.querySelector('.stats-refresh');
              const startInput = container.querySelector('input[name="start_date"]');
              const endInput = container.querySelector('input[name="end_date"]');
              
              // Style form inputs
              if (startInput) {
                  startInput.style.width = '100%';
                  startInput.style.padding = '0.625rem 0.875rem';
                  startInput.style.border = '1px solid #d1d5db';
                  startInput.style.borderRadius = '8px';
                  startInput.style.fontSize = '0.875rem';
                  startInput.style.background = 'white';
              }
              
              if (endInput) {
                  endInput.style.width = '100%';
                  endInput.style.padding = '0.625rem 0.875rem';
                  endInput.style.border = '1px solid #d1d5db';
                  endInput.style.borderRadius = '8px';
                  endInput.style.fontSize = '0.875rem';
                  endInput.style.background = 'white';
              }
              
              // Style refresh button
              if (refreshBtn) {
                  refreshBtn.style.padding = '0.625rem 1rem';
                  refreshBtn.style.background = '#1f2937';
                  refreshBtn.style.color = 'white';
                  refreshBtn.style.border = 'none';
                  refreshBtn.style.borderRadius = '8px';
                  refreshBtn.style.fontWeight = '600';
                  refreshBtn.style.fontSize = '0.875rem';
                  refreshBtn.style.cursor = 'pointer';
              }
              
              // Style form container
              const statsForm = container.querySelector('.stats-form');
              if (statsForm) {
                  statsForm.style.background = '#f8f9fb';
                  statsForm.style.padding = '1rem';
                  statsForm.style.borderRadius = '12px';
                  statsForm.style.marginBottom = '1rem';
              }
              
              // Apply styles after a small delay to ensure they override SweetAlert
              setTimeout(() => {
                  if (popup) {
                      popup.style.borderRadius = '20px';
                      popup.style.padding = '2rem';
                      popup.style.boxShadow = '0 20px 60px rgba(0, 0, 0, 0.3)';
                  }
                  if (confirmBtn) {
                      confirmBtn.style.background = '#1f2937';
                      confirmBtn.style.borderRadius = '10px';
                      confirmBtn.style.padding = '0.75rem 2rem';
                      confirmBtn.style.fontWeight = '600';
                      confirmBtn.style.fontSize = '0.875rem';
                      confirmBtn.style.border = 'none';
                      confirmBtn.style.color = 'white';
                  }
              }, 50);

              const loadStats = () => {
                  content.innerHTML = buildStatsLoader();
                  const url = new URL(statsUrl, window.location.origin);
                  if (startInput.value) {
                      url.searchParams.set('start_date', startInput.value);
                  }
                  if (endInput.value) {
                      url.searchParams.set('end_date', endInput.value);
                  }

                  fetch(url.toString(), {
                      headers: {
                          'Accept': 'application/json'
                      },
                      credentials: 'same-origin'
                  })
                      .then((resp) => {
                          if (!resp.ok) {
                              throw new Error('Unable to load stats');
                          }
                          return resp.json();
                      })
                      .then((data) => {
                          if (!data.success) {
                              throw new Error(data.message || 'Unable to load stats');
                          }
                          content.innerHTML = buildStatsContent(data);
                      })
                      .catch((error) => {
                          content.innerHTML = `
                              <div class="text-center text-danger py-4">
                                  <i class="bi bi-exclamation-triangle" style="font-size:2rem;"></i>
                                  <p class="mt-2 mb-0">${error.message}</p>
                              </div>
                          `;
                      });
              };

              refreshBtn.addEventListener('click', loadStats);
              loadStats();
          }
      });
  }

  function buildStatsLoader() {
      return `
          <div class="text-center py-5">
              <div class="spinner-border text-primary" role="status">
              </div>
          </div>
      `;
  }

  function buildStatsContent(payload) {
      const employee = payload.employee || {};
      const stats = payload.stats || {};
      const range = payload.range || {};
      
      const photo = employee.photo_url || employee.head_pic_url || null;
      const nameRaw = (employee.full_name || employee.name || '').trim();
      const initial = nameRaw !== '' 
          ? nameRaw.charAt(0).toUpperCase() 
          : ((employee.person_code || '').charAt(0) || '—').toUpperCase();
      const name = nameRaw !== '' ? nameRaw : '—';

      return `
          <div class="employee-stats-container">
              <div class="row g-3">
                  <div class="col-xl-3 col-lg-4">
                      <div class="employee-info-box">
                          <div class="employee-avatar-wrapper mb-3">
                              <div class="position-relative" style="width:80px;height:80px;">
                                  <div class="d-inline-flex align-items-center justify-content-center w-100 h-100 bg-primary text-white employee-initial-avatar"
                                       style="border-radius:12px;font-weight:600;font-size:2rem;">
                                      ${initial}
                                  </div>
                                  ${photo ? `
                                  <img src="${photo}" alt="photo" class="employee-photo-avatar"
                                       style="position:absolute;top:0;left:0;width:80px;height:80px;object-fit:cover;border-radius:12px;display:none;"
                                       onload="this.style.display='block';this.previousElementSibling.classList.add('d-none');"
                                       onerror="this.onerror=null;this.remove();">
                                  ` : ''}
                              </div>
                          </div>
                          <div class="text-left">
                              <div class="mb-2" style="text-align: left;"><strong style="font-size:1.1rem;">${name}</strong></div>
                              <div class="small text-muted" style="text-align: left;">Code: ${employee.person_code || '—'}</div>
                          </div>
                      </div>
                  </div>
                  <div class="col-xl-9 col-lg-8">
                      <div class="row g-3">
                          <div class="col-xl-6 col-sm-6">
                              <div class="modern-widget stats-widget-fixed">
                                  <div class="modern-widget-icon modern-widget-icon-primary">
                                      <i class="bi bi-check-circle"></i>
                                  </div>
                                  <div class="modern-widget-label">Present Days</div>
                                  <div class="modern-widget-value">${stats.present_days ?? 0}</div>
                              </div>
                          </div>
                          <div class="col-xl-6 col-sm-6">
                              <div class="modern-widget stats-widget-fixed">
                                  <div class="modern-widget-icon modern-widget-icon-warning">
                                      <i class="bi bi-x-circle"></i>
                                  </div>
                                  <div class="modern-widget-label">Absent Days</div>
                                  <div class="modern-widget-value">${stats.absent_days ?? 0}</div>
                              </div>
                          </div>
                          <div class="col-xl-6 col-sm-6 mt-3">
                              <div class="modern-widget stats-widget-fixed">
                                  <div class="modern-widget-icon modern-widget-icon-danger">
                                      <i class="bi bi-clock"></i>
                                  </div>
                                  <div class="modern-widget-label">Overtime</div>
                                  <div class="modern-widget-value">${stats.overtime_formatted ?? '00:00'}</div>
                              </div>
                          </div>
                          <div class="col-xl-6 col-sm-6 mt-3">
                              <div class="modern-widget stats-widget-fixed">
                                  <div class="modern-widget-icon modern-widget-icon-success">
                                      <i class="bi bi-hourglass-split"></i>
                                  </div>
                                  <div class="modern-widget-label">Work Hours</div>
                                  <div class="modern-widget-value">${stats.work_formatted ?? '00:00'}</div>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      `;
  }

  function getDefaultStatsDates() {
      const end = new Date();
      const start = new Date();
      start.setDate(start.getDate() - 6);
      return {
          start: formatDateInput(start),
          end: formatDateInput(end)
      };
  }

  function formatDateInput(date) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
  }
</script>
@endsection
