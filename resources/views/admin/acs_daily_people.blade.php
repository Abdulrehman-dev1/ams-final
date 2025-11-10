@extends('layouts.master')

@section('content')
@php
    $viewMode = $viewMode ?? request()->query('view', 'list');
    $reportRangeDays = $reportRangeDays ?? 30;
    $reportRangeLabel = $reportRangeLabel ?? '';
@endphp

<div class="container-fluid">
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('acs.people.index') }}"
           class="btn-modern {{ $viewMode === 'list' ? 'btn-modern-primary' : '' }}">
            <i class="bi bi-people-fill"></i>
            <span>Employee Directory</span>
        </a>
        <a href="{{ route('acs.people.index', ['view' => 'reports']) }}"
           class="btn-modern {{ $viewMode === 'reports' ? 'btn-modern-primary' : '' }}">
            <i class="bi bi-graph-up"></i>
            <span>Employee Reports</span>
        </a>
    </div>

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
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h2 class="mb-1" style="font-weight: 700; color: var(--gray-900);">
                            <i class="bi bi-people me-2" style="color: var(--primary);"></i>
                            Employees List
                        </h2>
                        <p class="text-muted mb-0" style="font-size: 0.875rem;">Manage employees synced from HikCentral Connect</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('acs.people.index') }}" class="btn-modern" style="background: var(--gray-600); color: white;">
                            <i class="bi bi-arrow-clockwise"></i>
                            <span>Reload</span>
</a>
<form action="{{ route('acs.people.syncNow') }}" method="POST"
                              onsubmit="
                                const btn = this.querySelector('button');
                                btn.disabled = true;
                                btn.innerHTML = '<span class="modern-loader"></span> Syncing...';
                                return true;
                              "
                              class="d-inline">
  @csrf
                            <button type="submit" class="btn-modern btn-modern-primary">
                                <i class="bi bi-cloud-arrow-down"></i>
                                <span>Sync Now</span>
  </button>
</form>
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
  <div class="row g-2">
                    <div class="col-md-3">
                        <label class="modern-form-label">Name</label>
                        <input type="text" name="name" value="{{ $filters['name'] ?? '' }}" class="modern-form-input" placeholder="Search by name">
                    </div>
                    <div class="col-md-2">
                        <label class="modern-form-label">Person Code</label>
                        <input type="text" name="person_code" value="{{ $filters['person_code'] ?? '' }}" class="modern-form-input" placeholder="Search by code">
    </div>
                    <div class="col-md-2">
                        <label class="modern-form-label">Status</label>
                        @php $statusFilter = $filters['status'] ?? ''; @endphp
                        <select name="status" class="modern-form-input">
                            <option value="">All</option>
                            <option value="enabled" {{ $statusFilter === 'enabled' ? 'selected' : '' }}>Enabled</option>
                            <option value="disabled" {{ $statusFilter === 'disabled' ? 'selected' : '' }}>Disabled</option>
                        </select>
    </div>
    <div class="col-md-2">
                        <label class="modern-form-label">Per Page</label>
      @php $pp = (int)($filters['perPage'] ?? 25); @endphp
                        <select name="perPage" class="modern-form-input">
        @foreach([25,50,100,200] as $opt)
                                <option value="{{ $opt }}" {{ $pp === $opt ? 'selected' : '' }}>{{ $opt }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn-modern btn-modern-success">
                            <i class="bi bi-funnel"></i>
                            <span>Apply</span>
                        </button>
                        <button type="submit"
                                class="btn-modern"
                                style="background: var(--gray-600); color: white;"
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
            <div class="table-responsive table-sticky">
                <table class="modern-table js-datatable" data-page-length="25">
                    <thead>
          <tr>
            <th style="width:60px">#</th>
            <th style="width:70px">Photo</th>
                            <th style="min-width:180px">Name</th>
            <th>Phone</th>
            <th>Person Code</th>
                            <th style="min-width:160px">Group</th>
                            <th class="text-nowrap">Time In</th>
                            <th class="text-nowrap">Time Out</th>
                            <th style="min-width:180px">Location</th>
                            <th>Status</th>
                            <th style="width:210px; text-align: center;">Actions</th>
          </tr>
        </thead>
        <tbody>
        @forelse($page as $i => $row)
         @php
  $idx   = ($page->currentPage()-1)*$page->perPage() + $i + 1;
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
            <td class="text-muted">{{ $idx }}</td>
            <td>
  @if(!empty($photo))
                                        <img src="{{ $photo }}"
                                             alt="photo"
                                             style="width:48px;height:48px;object-fit:cover;border-radius:8px;{{ !($row['is_enabled'] ?? true) ? 'filter: grayscale(100%);' : '' }}"
         onerror="this.onerror=null;this.src='https://via.placeholder.com/48x48?text=%20';">
  @else
                                        <div class="d-inline-flex align-items-center justify-content-center {{ ($row['is_enabled'] ?? true) ? 'bg-primary' : 'bg-secondary' }} text-white"
         style="width:48px;height:48px;border-radius:8px;font-weight:600;">
      {{ $initial }}
    </div>
  @endif
</td>
                                <td>
                                    <div class="fw-semibold">
                                        <a href="{{ route('acs.people.profile', $row['id']) }}" class="text-decoration-none">
                                            {{ $name }}
                                        </a>
                                    </div>
                                    @if(!($row['is_enabled'] ?? true))
                                        <small class="text-muted" style="font-size: 0.75rem;">
                                            <i class="bi bi-eye-slash"></i> Disabled
                                        </small>
                                    @endif
                                </td>
            <td>
              @if(!empty($row['phone']))
                <a href="tel:{{ $row['phone'] }}">{{ $row['phone'] }}</a>
              @else
                —
              @endif
            </td>
                                <td>
                                    <span class="modern-badge modern-badge-primary" style="font-size: 0.75rem;">
                                        {{ $row['person_code'] ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    @if(!empty($row['group_name']))
                                        <span class="modern-badge" style="background: rgba(6, 182, 212, 0.1); color: var(--info); font-size: 0.75rem;">
                                            {{ $row['group_name'] }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <span class="modern-badge modern-badge-primary" style="font-size: 0.75rem;">
                                        <i class="bi bi-clock me-1"></i>{{ $timeInFormatted }}
                                    </span>
                                    <br>
                                    <small class="text-muted" style="font-size: 0.7rem;">
                                        Late after {{ \Carbon\Carbon::createFromFormat('H:i:s', $timeInValue)->addMinutes(15)->format('h:i A') }}
                                    </small>
                                </td>
                                <td class="text-nowrap">
                                    <span class="modern-badge modern-badge-info" style="font-size: 0.75rem;">
                                        <i class="bi bi-clock-history me-1"></i>{{ $timeOutFormatted }}
                                    </span>
                                </td>
                                <td>
                                    @if(!empty($row['latitude']) && !empty($row['longitude']))
                                        <a href="https://www.google.com/maps?q={{ $row['latitude'] }},{{ $row['longitude'] }}"
                                           target="_blank"
                                           class="text-decoration-none"
                                           title="View on Google Maps">
                                            <span class="modern-badge" style="background: rgba(34, 197, 94, 0.1); color: var(--success); font-size: 0.75rem;">
                                                <i class="bi bi-geo-alt-fill me-1"></i>
                                                {{ number_format($row['latitude'], 6) }}, {{ number_format($row['longitude'], 6) }}
                                            </span>
                                        </a>
                                    @else
                                        <span class="text-muted" style="font-size: 0.75rem;">—</span>
                                    @endif
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
                                        <a href="{{ route('acs.people.profile', $row['id']) }}"
                                           class="btn-modern"
                                           style="padding: 0.375rem 0.75rem; font-size: 0.875rem; background: var(--gray-700); color: #fff;"
                                           title="View profile and reports">
                                            <i class="bi bi-person-lines-fill"></i>
                                            <span>Profile</span>
                                        </a>
                                        <a href="{{ route('acs.people.edit', $row['id']) }}"
                                           class="btn-modern btn-modern-primary"
                                           style="padding: 0.375rem 0.75rem; font-size: 0.875rem;"
                                           title="Edit Employee">
                                            <i class="bi bi-pencil"></i>
                                            <span>Edit</span>
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
                                <td colspan="11" class="text-center text-muted py-5">
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
            <div class="modern-card-footer d-flex justify-content-between align-items-center">
                <div style="font-size: 0.875rem; color: var(--gray-600); font-weight: 500;">
                    <i class="bi bi-list-ul me-2"></i>Total: <strong>{{ $page->total() }}</strong> employees
                </div>
                <div>
      {{ $page->links() }}
    </div>
  </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
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
</script>
@endpush
