@extends('layouts.master')

@section('css')
<!--Chartist Chart CSS -->
<link rel="stylesheet" href="{{ URL::asset('plugins/chartist/css/chartist.min.css') }}">
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="{{ URL::asset('plugins/sweet-alert2/sweetalert2.min.css') }}">
<style>
.widget-more-info {
    cursor: pointer;
    transition: all 0.3s;
}
.widget-more-info:hover {
    text-decoration: underline;
}
.focus-date-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10;
}
.sync-status-widget {
    border-left: 4px solid;
}
.sync-healthy {
    border-color: #28a745 !important;
}
.sync-warning {
    border-color: #ffc107 !important;
}
.sync-danger {
    border-color: #dc3545 !important;
}
</style>
@endsection

@section('breadcrumb')
<div class="d-flex align-items-center justify-content-between w-100">
    <div>
        <h2 class="mb-1" style="font-weight: 700; color: var(--gray-900);">
            <i class="bi bi-speedometer2 me-2" style="color: var(--primary);"></i>
            Dashboard
        </h2>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Welcome to Attendance Management System</p>
    </div>
    <div>
        @if($more['is_today'])
            <span class="modern-badge modern-badge-success" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                <i class="bi bi-calendar-today me-1"></i> Today: {{ $more['focus_date'] }}
            </span>
        @else
            <span class="modern-badge modern-badge-warning" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                <i class="bi bi-calendar me-1"></i> Viewing: {{ $more['focus_date'] }}
            </span>
        @endif
    </div>
</div>
@endsection

@section('content')
                   <!-- Modern Sync Status Widget -->
                   <div class="row mb-4">
                       <div class="col-12">
                           <div class="modern-card" style="border-left: 4px solid {{ $more['sync_healthy'] ? 'var(--success)' : 'var(--warning)' }};">
                               <div class="modern-card-body">
                                   <div class="row align-items-center">
                                       <div class="col-md-4">
                                           <div class="d-flex align-items-center gap-2">
                                               <i class="bi bi-arrow-repeat {{ $more['sync_healthy'] ? 'text-success' : 'text-warning' }}" style="font-size: 1.25rem;"></i>
                                               <div>
                                                   <div style="font-weight: 600; color: var(--gray-900);">Last Sync</div>
                                                   <div style="font-size: 0.875rem; color: var(--gray-600);">{{ $more['last_sync'] }}</div>
                                                   @if(!$more['sync_healthy'])
                                                       <span class="modern-badge modern-badge-warning mt-1">Sync may be delayed</span>
                                                   @endif
                                               </div>
                                           </div>
                                       </div>
                                       <div class="col-md-4 text-center">
                                           <div class="d-flex flex-column align-items-center">
                                               <div style="font-weight: 600; color: var(--gray-900); margin-bottom: 0.5rem;">Showing Data For</div>
                                               <span class="modern-badge {{ $more['is_today'] ? 'modern-badge-success' : 'modern-badge-info' }}" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                                   <i class="bi bi-calendar3 me-1"></i>{{ $more['focus_date'] }}
                                               </span>
                                               <small class="text-muted mt-2" style="font-size: 0.75rem;">Page loaded: {{ now()->format('H:i:s') }}</small>
                                           </div>
                                       </div>
                                       <div class="col-md-4 text-end">
                                           <div style="font-weight: 600; color: var(--gray-900); margin-bottom: 0.5rem;">Settings</div>
                                           <div style="font-size: 0.875rem; color: var(--gray-600);">
                                               <div>On-time: <strong>{{ $more['config']['on_time_cutoff'] }}</strong></div>
                                               <div>Absent: <strong>{{ $more['config']['absent_cutoff'] }}</strong></div>
                                               <div>End: <strong>{{ $more['config']['shift_end_time'] }}</strong></div>
                                           </div>
                                       </div>
                                   </div>
                               </div>
                           </div>
                       </div>
                   </div>

                   <!-- Row 1: Core Metrics - Modern Widgets -->
                   <div class="row g-4 mb-4">
                            <div class="col-xl-3 col-md-6">
                                <div class="modern-widget slide-up">
                                    <div class="modern-widget-icon modern-widget-icon-primary">
                                        <i class="bi bi-people-fill"></i>
                                    </div>
                                    <div class="modern-widget-label">Total Employees</div>
                                    <div class="modern-widget-value">{{$data[0]}}</div>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <a href="javascript:void(0);" class="text-decoration-none widget-more-info" data-widget="total-employees" style="color: var(--primary); font-size: 0.875rem;">
                                            More info <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="modern-widget slide-up" style="animation-delay: 0.1s;">
                                    <div class="modern-widget-icon modern-widget-icon-success">
                                        <i class="bi bi-clock-history"></i>
                                    </div>
                                    <div class="modern-widget-label">On Time Percentage</div>
                                    <div class="modern-widget-value">{{$data[3]}}%</div>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <a href="javascript:void(0);" class="text-decoration-none widget-more-info" data-widget="on-time" style="color: var(--success); font-size: 0.875rem;">
                                            More info <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="modern-widget slide-up" style="animation-delay: 0.2s;">
                                    <div class="modern-widget-icon modern-widget-icon-success">
                                        <i class="bi bi-check-circle-fill"></i>
                                    </div>
                                    <div class="modern-widget-label">On Time Today</div>
                                    <div class="modern-widget-value">{{$data[1]}}</div>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <a href="javascript:void(0);" class="text-decoration-none widget-more-info" data-widget="on-time" style="color: var(--success); font-size: 0.875rem;">
                                            More info <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="modern-widget slide-up" style="animation-delay: 0.3s;">
                                    <div class="modern-widget-icon modern-widget-icon-warning">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                    </div>
                                    <div class="modern-widget-label">Late Today</div>
                                    <div class="modern-widget-value">{{$data[2]}}</div>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <a href="javascript:void(0);" class="text-decoration-none widget-more-info" data-widget="late" style="color: var(--warning); font-size: 0.875rem;">
                                            More info <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                   <!-- Row 2: Detailed Metrics - Modern Widgets -->
                   <div class="row g-4 mb-4">
                            <div class="col-xl-3 col-md-6">
                                <div class="modern-widget slide-up" style="animation-delay: 0.4s;">
                                    <div class="modern-widget-icon modern-widget-icon-info">
                                        <i class="bi bi-phone-fill"></i>
                                    </div>
                                    <div class="modern-widget-label">Mobile CheckIn Today</div>
                                    <div class="modern-widget-value">{{ $more['mobile_checkins_today'] }}</div>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <a href="javascript:void(0);" class="text-decoration-none widget-more-info" data-widget="mobile-checkins" style="color: var(--info); font-size: 0.875rem;">
                                            More info <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="modern-widget slide-up" style="animation-delay: 0.5s;">
                                    <div class="modern-widget-icon modern-widget-icon-primary">
                                        <i class="bi bi-device-hdd-fill"></i>
                                    </div>
                                    <div class="modern-widget-label">Device CheckIn Today</div>
                                    <div class="modern-widget-value">{{ $more['device_checkins_today'] }}</div>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <a href="javascript:void(0);" class="text-decoration-none widget-more-info" data-widget="device-checkins" style="color: var(--primary); font-size: 0.875rem;">
                                            More info <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="modern-widget slide-up" style="animation-delay: 0.6s;">
                                    <div class="modern-widget-icon modern-widget-icon-warning">
                                        <i class="bi bi-arrow-left-circle-fill"></i>
                                    </div>
                                    <div class="modern-widget-label">Early Leave Today</div>
                                    <div class="modern-widget-value">{{ $more['early_leave_today'] }}</div>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <a href="javascript:void(0);" class="text-decoration-none widget-more-info" data-widget="early-leave" style="color: var(--warning); font-size: 0.875rem;">
                                            More info <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="modern-widget slide-up" style="animation-delay: 0.7s;">
                                    <div class="modern-widget-icon modern-widget-icon-danger">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </div>
                                    <div class="modern-widget-label">Absent Today</div>
                                    <div class="modern-widget-value">{{ $more['absent_today'] }}</div>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <a href="javascript:void(0);" class="text-decoration-none widget-more-info" data-widget="absent" style="color: var(--danger); font-size: 0.875rem;">
                                            More info <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: New Metrics -->
                        <div class="row">
                            <div class="col-xl-3 col-md-6">
                                <div class="card mini-stat bg-success text-white">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="float-left mini-stat-img mr-3">
                                                <i class="ti-time" style="font-size: 20px"></i>
                                            </div>
                                            <h5 class="font-16 text-uppercase mt-0 text-white-50">Average <br> Check-in Time</h5>
                                            <h4 class="font-500">{{ $more['avg_checkin_time'] ?? 'N/A' }}</h4>
                                        </div>
                                        <div class="pt-2">
                                            <div class="float-right">
                                                <a href="javascript:void(0);" class="text-white-50 widget-more-info" data-widget="on-time">
                                                    <i class="mdi mdi-arrow-right h5"></i>
                                                </a>
                                            </div>
                                            <p class="text-white-50 mb-0 widget-more-info" data-widget="on-time">More info</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card mini-stat bg-warning text-white">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="float-left mini-stat-img mr-3">
                                                <i class="ti-alarm" style="font-size: 20px"></i>
                                            </div>
                                            <h5 class="font-16 text-uppercase mt-0 text-white-50">Overtime <br> Count</h5>
                                            <h4 class="font-500">{{ $more['overtime_count'] }}</h4>
                                        </div>
                                        <div class="pt-2">
                                            <div class="float-right">
                                                <a href="javascript:void(0);" class="text-white-50 widget-more-info" data-widget="overtime">
                                                    <i class="mdi mdi-arrow-right h5"></i>
                                                </a>
                                            </div>
                                            <p class="text-white-50 mb-0 widget-more-info" data-widget="overtime">More info</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card mini-stat bg-info text-white">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="float-left mini-stat-img mr-3">
                                                <i class="ti-calendar" style="font-size: 20px"></i>
                                            </div>
                                            <h5 class="font-16 text-uppercase mt-0 text-white-50">Pending <br> Leaves</h5>
                                            <h4 class="font-500">{{ $more['pending_leaves'] }}</h4>
                                        </div>
                                        <div class="pt-2">
                                            <div class="float-right">
                                                <a href="javascript:void(0);" class="text-white-50 widget-more-info" data-widget="pending-leaves">
                                                    <i class="mdi mdi-arrow-right h5"></i>
                                                </a>
                                            </div>
                                            <p class="text-white-50 mb-0 widget-more-info" data-widget="pending-leaves">More info</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card mini-stat bg-dark text-white">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="float-left mini-stat-img mr-3">
                                                <i class="ti-harddrives" style="font-size: 20px"></i>
                                            </div>
                                            <h5 class="font-16 text-uppercase mt-0 text-white-50">Device <br> Status</h5>
                                            <h4 class="font-500">{{ $more['active_devices'] }}/{{ $more['total_devices'] }}</h4>
                                        </div>
                                        <div class="pt-2">
                                            <div class="float-right">
                                                <a href="javascript:void(0);" class="text-white-50 widget-more-info" data-widget="device-status">
                                                    <i class="mdi mdi-arrow-right h5"></i>
                                                </a>
                                            </div>
                                            <p class="text-white-50 mb-0 widget-more-info" data-widget="device-status">More info</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- end row -->
@endsection

@section('script')
<!--Chartist Chart-->
<script src="{{ URL::asset('plugins/chartist/js/chartist.min.js') }}"></script>
<script src="{{ URL::asset('plugins/chartist/js/chartist-plugin-tooltip.min.js') }}"></script>
<!-- peity JS -->
<script src="{{ URL::asset('plugins/peity-chart/jquery.peity.min.js') }}"></script>
<script src="{{ URL::asset('assets/pages/dashboard.js') }}"></script>

<!-- SweetAlert2 -->
<script src="{{ URL::asset('plugins/sweet-alert2/sweetalert2.all.min.js') }}"></script>

<script>
    // Focus date for API calls
    const FOCUS_DATE = '{{ $more['focus_date'] }}';
    const BASE_URL = '{{ url('/') }}';
    const IS_TODAY = {{ $more['is_today'] ? 'true' : 'false' }};

    // Debug: Show what date we're using
    console.log('Dashboard Focus Date:', FOCUS_DATE, '| Is Today:', IS_TODAY);

    // Widget drill-down handlers
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Widget handlers initialized. Focus Date:', FOCUS_DATE);

        document.querySelectorAll('.widget-more-info').forEach(function(element) {
            element.addEventListener('click', function(e) {
                e.preventDefault();
                const widget = this.getAttribute('data-widget');
                console.log('Widget clicked:', widget, 'Date:', FOCUS_DATE);
                showWidgetDetails(widget);
            });
        });
    });

    function showWidgetDetails(widget) {
        // Show loading
        Swal.fire({
            title: 'Loading...',
            text: 'Fetching data...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Fetch data from API
        const apiUrl = `${BASE_URL}/api/dashboard/widgets/${widget}?date=${FOCUS_DATE}`;
        console.log('Fetching from:', apiUrl);

        fetch(apiUrl)
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Data received:', data);
                if (data.ok) {
                    displayWidgetData(widget, data);
                } else {
                    Swal.fire('Error', data.message || 'Failed to load data', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', `Failed to fetch widget data: ${error.message}`, 'error');
            });
    }

    function displayWidgetData(widget, data) {
        let html = '';
        let title = '';

        switch(widget) {
            case 'total-employees':
                title = `Total Employees (${data.total})`;
                if (data.employees && data.employees.length > 0) {
                    html = '<div class="table-responsive"><table class="table table-sm table-striped">';
                    html += '<thead><tr><th>Code</th><th>Name</th><th>Group</th><th>Contact</th></tr></thead><tbody>';
                    data.employees.forEach(emp => {
                        html += `<tr>
                            <td>${emp.person_code || 'N/A'}</td>
                            <td>${emp.name || 'N/A'}</td>
                            <td>${emp.group || 'N/A'}</td>
                            <td>${emp.phone || emp.email || 'N/A'}</td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                    if (data.total > 100) {
                        html += `<p class="text-muted mt-2"><small>Showing first 100 of ${data.total} employees</small></p>`;
                    }
                } else {
                    html = '<p class="text-center text-muted">No employees found</p>';
                }
                break;

            case 'on-time':
                title = `On-Time Check-ins (${data.count})`;
                html = `<p class="mb-3"><strong>Cutoff Time:</strong> ${data.cutoff_time} | <strong>Date:</strong> ${data.date}</p>`;
                if (data.employees && data.employees.length > 0) {
                    html += '<div class="table-responsive"><table class="table table-sm table-striped">';
                    html += '<thead><tr><th>Code</th><th>Name</th><th>Time</th><th>Source</th></tr></thead><tbody>';
                    data.employees.forEach(emp => {
                        html += `<tr>
                            <td>${emp.person_code || 'N/A'}</td>
                            <td>${emp.name}</td>
                            <td><span class="badge badge-success">${emp.check_in_time}</span></td>
                            <td>${emp.source}</td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p class="text-center text-muted">No on-time check-ins found</p>';
                }
                break;

            case 'late':
                title = `Late Arrivals (${data.count})`;
                html = `<p class="mb-3"><strong>Cutoff Time:</strong> ${data.cutoff_time} | <strong>Date:</strong> ${data.date}</p>`;
                if (data.employees && data.employees.length > 0) {
                    html += '<div class="table-responsive"><table class="table table-sm table-striped">';
                    html += '<thead><tr><th>Code</th><th>Name</th><th>Time</th><th>Late By</th><th>Source</th></tr></thead><tbody>';
                    data.employees.forEach(emp => {
                        html += `<tr>
                            <td>${emp.person_code || 'N/A'}</td>
                            <td>${emp.name}</td>
                            <td><span class="badge badge-danger">${emp.check_in_time}</span></td>
                            <td><span class="badge badge-warning">${emp.late_by}</span></td>
                            <td>${emp.source}</td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p class="text-center text-muted">No late arrivals found</p>';
                }
                break;

            case 'mobile-checkins':
                title = `Mobile Check-ins (${data.count})`;
                html = `<p class="mb-3"><strong>Date:</strong> ${data.date}</p>`;
                if (data.employees && data.employees.length > 0) {
                    html += '<div class="table-responsive"><table class="table table-sm table-striped">';
                    html += '<thead><tr><th>Code</th><th>Name</th><th>Time</th><th>Device</th></tr></thead><tbody>';
                    data.employees.forEach(emp => {
                        html += `<tr>
                            <td>${emp.person_code || 'N/A'}</td>
                            <td>${emp.name}</td>
                            <td>${emp.check_in_time}</td>
                            <td><i class="ti-mobile text-primary"></i> ${emp.device}</td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p class="text-center text-muted">No mobile check-ins found</p>';
                }
                break;

            case 'device-checkins':
                title = `Device Check-ins (${data.count})`;
                html = `<p class="mb-3"><strong>Date:</strong> ${data.date}</p>`;
                if (data.employees && data.employees.length > 0) {
                    html += '<div class="table-responsive"><table class="table table-sm table-striped">';
                    html += '<thead><tr><th>Code</th><th>Name</th><th>Time</th><th>Device</th></tr></thead><tbody>';
                    data.employees.forEach(emp => {
                        html += `<tr>
                            <td>${emp.person_code || 'N/A'}</td>
                            <td>${emp.name}</td>
                            <td>${emp.check_in_time}</td>
                            <td><i class="ti-harddrives text-info"></i> ${emp.device}</td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p class="text-center text-muted">No device check-ins found</p>';
                }
                break;

            case 'early-leave':
                title = `Early Departures (${data.count})`;
                html = `<p class="mb-3"><strong>Shift End:</strong> ${data.shift_end_time} | <strong>Date:</strong> ${data.date}</p>`;
                if (data.employees && data.employees.length > 0) {
                    html += '<div class="table-responsive"><table class="table table-sm table-striped">';
                    html += '<thead><tr><th>Code</th><th>Name</th><th>Time</th><th>Early By</th><th>Source</th></tr></thead><tbody>';
                    data.employees.forEach(emp => {
                        html += `<tr>
                            <td>${emp.person_code || 'N/A'}</td>
                            <td>${emp.name}</td>
                            <td>${emp.check_out_time}</td>
                            <td><span class="badge badge-warning">${emp.early_by}</span></td>
                            <td>${emp.source}</td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p class="text-center text-muted">No early departures found</p>';
                }
                break;

            case 'absent':
                title = `Absent Employees (${data.count})`;
                html = `<p class="mb-3"><strong>Cutoff:</strong> ${data.absent_cutoff} | <strong>Date:</strong> ${data.date}</p>`;
                if (data.employees && data.employees.length > 0) {
                    html += '<div class="table-responsive"><table class="table table-sm table-striped">';
                    html += '<thead><tr><th>Code</th><th>Name</th><th>Group</th><th>Status</th></tr></thead><tbody>';
                    data.employees.forEach(emp => {
                        html += `<tr>
                            <td>${emp.person_code || 'N/A'}</td>
                            <td>${emp.name}</td>
                            <td>${emp.group || 'N/A'}</td>
                            <td><span class="badge badge-danger">${emp.status}</span></td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p class="text-center text-success">✓ No absences! All employees present.</p>';
                }
                break;

            case 'overtime':
                title = `Overtime (${data.count})`;
                html = `<p class="mb-3"><strong>Shift End:</strong> ${data.shift_end_time} | <strong>Date:</strong> ${data.date}</p>`;
                if (data.employees && data.employees.length > 0) {
                    html += '<div class="table-responsive"><table class="table table-sm table-striped">';
                    html += '<thead><tr><th>Code</th><th>Name</th><th>Check-out</th><th>Overtime</th></tr></thead><tbody>';
                    data.employees.forEach(emp => {
                        html += `<tr>
                            <td>${emp.person_code || 'N/A'}</td>
                            <td>${emp.name}</td>
                            <td>${emp.check_out_time}</td>
                            <td><span class="badge badge-info">${emp.overtime}</span></td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p class="text-center text-muted">No overtime found</p>';
                }
                break;

            case 'pending-leaves':
                title = `Pending Leave Requests (${data.count})`;
                if (data.leaves && data.leaves.length > 0) {
                    html = '<div class="table-responsive"><table class="table table-sm table-striped">';
                    html += '<thead><tr><th>ID</th><th>Employee</th><th>Date</th><th>Time</th><th>Type</th></tr></thead><tbody>';
                    data.leaves.forEach(leave => {
                        html += `<tr>
                            <td>#${leave.id}</td>
                            <td>${leave.employee_name}</td>
                            <td>${leave.leave_date}</td>
                            <td>${leave.leave_time || 'N/A'}</td>
                            <td><span class="badge badge-warning">${leave.type}</span></td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                } else {
                    html = '<p class="text-center text-muted">No pending leave requests</p>';
                }
                break;

            case 'device-status':
                title = `Device Status (${data.active}/${data.total} Active)`;
                if (data.devices && data.devices.length > 0) {
                    html = '<div class="table-responsive"><table class="table table-sm table-striped">';
                    html += '<thead><tr><th>ID</th><th>Name</th><th>IP Address</th><th>Serial</th><th>Status</th></tr></thead><tbody>';
                    data.devices.forEach(device => {
                        html += `<tr>
                            <td>${device.id}</td>
                            <td>${device.name}</td>
                            <td><code>${device.ip}</code></td>
                            <td>${device.serialNumber || 'N/A'}</td>
                            <td><span class="badge badge-success">${device.status}</span></td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                } else {
                    html = '<p class="text-center text-muted">No devices registered</p>';
                }
                break;

            default:
                html = '<p>No data available</p>';
        }

        Swal.fire({
            title: title,
            html: html,
            width: '800px',
            showCloseButton: true,
            showConfirmButton: false,
            customClass: {
                popup: 'swal-wide'
            }
        });
    }
</script>

<style>
.swal-wide {
    max-width: 90% !important;
}
.swal2-html-container {
    max-height: 500px;
    overflow-y: auto;
}
</style>
@endsection
