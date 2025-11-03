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
<div class="col-sm-6 text-left" >
     <h4 class="page-title">Dashboard</h4>
     <ol class="breadcrumb">
         <li class="breadcrumb-item active">Welcome to Attendance Management System</li>
     </ol>
</div>
<div class="col-sm-6">
    <div class="float-right">
        @if($more['is_today'])
            <span class="badge badge-success badge-pill focus-date-badge">
                <i class="mdi mdi-calendar-today"></i> Today: {{ $more['focus_date'] }}
            </span>
        @else
            <span class="badge badge-warning badge-pill focus-date-badge">
                <i class="mdi mdi-calendar"></i> Viewing: {{ $more['focus_date'] }}
            </span>
        @endif
    </div>
</div>
@endsection

@section('content')
                   <!-- Sync Status Widget -->
                   <div class="row mb-3">
                       <div class="col-12">
                           <div class="card sync-status-widget {{ $more['sync_healthy'] ? 'sync-healthy' : 'sync-warning' }}">
                               <div class="card-body py-2">
                                   <div class="row align-items-center">
                                       <div class="col-md-4">
                                           <i class="mdi mdi-sync {{ $more['sync_healthy'] ? 'text-success' : 'text-warning' }} mr-2"></i>
                                           <strong>Last Sync:</strong> {{ $more['last_sync'] }}
                                           @if(!$more['sync_healthy'])
                                               <span class="badge badge-warning ml-2">Sync may be delayed</span>
                                           @endif
                                       </div>
                                       <div class="col-md-4 text-center">
                                           <i class="mdi mdi-calendar-check mr-2"></i>
                                           <strong>Showing Data For:</strong>
                                           <span class="badge badge-{{ $more['is_today'] ? 'success' : 'info' }} badge-lg">
                                               {{ $more['focus_date'] }}
                                           </span>
                                           <br>
                                           <small class="text-muted" style="font-size: 10px;">Page loaded: {{ now()->format('H:i:s') }}</small>
                                       </div>
                                       <div class="col-md-4 text-right">
                                           <small class="text-muted">
                                               On-time: <strong>{{ $more['config']['on_time_cutoff'] }}</strong> |
                                               Absent: <strong>{{ $more['config']['absent_cutoff'] }}</strong> |
                                               End: <strong>{{ $more['config']['shift_end_time'] }}</strong>
                                           </small>
                                       </div>
                                   </div>
                               </div>
                           </div>
                       </div>
                   </div>

                   <!-- Row 1: Core Metrics -->
                   <div class="row">
                            <div class="col-xl-3 col-md-6">
                                <div class="card mini-stat bg-primary text-white">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="float-left mini-stat-img mr-3">
                                                <span class="ti-id-badge" style="font-size: 20px"></span>
                                            </div>
                                            <h5 class="font-16 text-uppercase mt-0 text-white-50">Total <br> Employees</h5>
                                            <h4 class="font-500">{{$data[0]}} </h4>
                                        </div>
                                        <div class="pt-2">
                                            <div class="float-right">
                                                <a href="javascript:void(0);" class="text-white-50 widget-more-info" data-widget="total-employees">
                                                    <i class="mdi mdi-arrow-right h5"></i>
                                                </a>
                                            </div>
                                            <p class="text-white-50 mb-0 widget-more-info" data-widget="total-employees">More info</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card mini-stat bg-primary text-white">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="float-left mini-stat-img mr-3">
                                                <i class="ti-alarm-clock" style="font-size: 20px"></i>
                                            </div>
                                            <h6  class="font-16 text-uppercase mt-0 text-white-50" >On Time <br> Percentage</h6>
                                            <h4 class="font-500">{{$data[3]}} %<i class="text-danger ml-2"></i></h4>
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
                                <div class="card mini-stat bg-primary text-white">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="float-left mini-stat-img mr-3">
                                                <i class=" ti-check-box " style="font-size: 20px"></i>
                                            </div>
                                            <h5 class="font-16 text-uppercase mt-0 text-white-50">On Time <br> Today</h5>
                                            <h4 class="font-500">{{$data[1]}} <i class=" text-success ml-2"></i></h4>
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
                                <div class="card mini-stat bg-primary text-white">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="float-left mini-stat-img mr-3">
                                                <i class="ti-alert" style="font-size: 20px"></i>
                                            </div>
                                            <h5 class="font-16 text-uppercase mt-0 text-white-50">Late <br> Today</h5>
                                            <h4 class="font-500">{{$data[2]}}<i class=" text-success ml-2"></i></h4>
                                        </div>
                                        <div class="pt-2">
                                            <div class="float-right">
                                                <a href="javascript:void(0);" class="text-white-50 widget-more-info" data-widget="late">
                                                    <i class="mdi mdi-arrow-right h5"></i>
                                                </a>
                                            </div>
                                            <p class="text-white-50 mb-0 widget-more-info" data-widget="late">More info</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                   <!-- Row 2: Detailed Metrics -->
                   <div class="row">
                            <div class="col-xl-3 col-md-6">
                                <div class="card mini-stat bg-primary text-white">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="float-left mini-stat-img mr-3">
                                                <span class="ti-mobile" style="font-size: 20px"></span>
                                            </div>
                                            <h5 class="font-16 text-uppercase mt-0 text-white-50">Mobile CheckIn <br> Today</h5>
                                           <h4 class="font-500 ">{{ $more['mobile_checkins_today'] }}</h4>
                                        </div>
                                        <div class="pt-2">
                                            <div class="float-right">
                                                <a href="javascript:void(0);" class="text-white-50 widget-more-info" data-widget="mobile-checkins">
                                                    <i class="mdi mdi-arrow-right h5"></i>
                                                </a>
                                            </div>
                                            <p class="text-white-50 mb-0 widget-more-info" data-widget="mobile-checkins">More info</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card mini-stat bg-primary text-white">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="float-left mini-stat-img mr-3">
                                                <i class="ti-save-alt" style="font-size: 20px"></i>
                                            </div>
                                            <h6  class="font-16 text-uppercase mt-0 text-white-50" >Device CheckIn <br> Today</h6>
                                            <h4 class="font-500 ">{{ $more['device_checkins_today'] }}<i class="text-danger ml-2"></i></h4>
                                        </div>
                                        <div class="pt-2">
                                            <div class="float-right">
                                                <a href="javascript:void(0);" class="text-white-50 widget-more-info" data-widget="device-checkins">
                                                    <i class="mdi mdi-arrow-right h5"></i>
                                                </a>
                                            </div>
                                            <p class="text-white-50 mb-0 widget-more-info" data-widget="device-checkins">More info</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card mini-stat bg-primary text-white">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="float-left mini-stat-img mr-3">
                                                <i class=" ti-receipt " style="font-size: 20px"></i>
                                            </div>
                                            <h5 class="font-16 text-uppercase mt-0 text-white-50">Early Leave <br> Today</h5>
                                            <h4 class="font-500">{{ $more['early_leave_today'] }} <i class=" text-success ml-2"></i></h4>
                                        </div>
                                        <div class="pt-2">
                                            <div class="float-right">
                                                <a href="javascript:void(0);" class="text-white-50 widget-more-info" data-widget="early-leave">
                                                    <i class="mdi mdi-arrow-right h5"></i>
                                                </a>
                                            </div>
                                            <p class="text-white-50 mb-0 widget-more-info" data-widget="early-leave">More info</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card mini-stat bg-primary text-white">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="float-left mini-stat-img mr-3">
                                                <i class="ti-na" style="font-size: 20px"></i>
                                            </div>
                                            <h5 class="font-16 text-uppercase mt-0 text-white-50">Absent <br> Today</h5>
                                            <h4 class="font-500">{{ $more['absent_today'] }}<i class=" text-success ml-2"></i></h4>
                                        </div>
                                        <div class="pt-2">
                                            <div class="float-right">
                                                <a href="javascript:void(0);" class="text-white-50 widget-more-info" data-widget="absent">
                                                    <i class="mdi mdi-arrow-right h5"></i>
                                                </a>
                                            </div>
                                            <p class="text-white-50 mb-0 widget-more-info" data-widget="absent">More info</p>
                                        </div>
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
