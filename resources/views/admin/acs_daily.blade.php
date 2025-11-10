@extends('layouts.master')

@section('content')
<div class="container-fluid">

  {{-- Modern Header --}}
  <div class="modern-card mb-4">
    <div class="modern-card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h2 class="mb-1" style="font-weight: 700; color: var(--gray-900);">
            <i class="bi bi-calendar-day me-2" style="color: var(--primary);"></i>
            Daily Attendance
          </h2>
          <p class="text-muted mb-0" style="font-size: 0.875rem;">Manage and track daily attendance records</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          {{-- Quick Reload --}}
          <a href="{{ route('acs.daily.index', request()->query()) }}" class="btn-modern btn-modern-primary" style="background: var(--gray-600);">
            <i class="bi bi-arrow-clockwise"></i>
            <span>Reload</span>
          </a>
          {{-- Sync Now (API) --}}
          <form action="{{ route('acs.daily.syncNow', request()->query()) }}" method="POST"
                onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<span class=\'modern-loader\'></span> Syncing...';"
                class="d-inline">
            @csrf
            <button type="submit" class="btn-modern btn-modern-primary">
              <i class="bi bi-cloud-arrow-down"></i>
              <span>Sync Now</span>
            </button>
          </form>
          {{-- Sync Scraper (Python Playwright) --}}
          <form action="{{ route('acs.daily.syncScraper', request()->query()) }}" method="POST"
                onsubmit="
                  const btn = this.querySelector('button');
                  btn.disabled = true;
                  btn.innerHTML = '<span class=\"modern-loader\"></span> Starting...';
                  return true;
                "
                class="d-inline">
            @csrf
            <button type="submit" class="btn-modern btn-modern-warning" title="Run Python Playwright scraper to fetch attendance data">
              <i class="bi bi-robot"></i>
              <span>Sync Scraper</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- Modern Flash Alert --}}
  @php $flash = $flash ?? session('flash'); @endphp
  @if(!empty($flash))
    <div class="modern-alert modern-alert-{{ $flash['ok'] ? 'success' : 'warning' }} fade-in" id="syncAlert" role="alert">
      <div>
        <i class="bi {{ $flash['ok'] ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' }}" style="font-size: 1.5rem;"></i>
      </div>
      <div style="flex: 1;">
        <strong style="font-size: 1rem; display: block; margin-bottom: 0.5rem;">
          {{ $flash['ok'] ? ($flash['type'] === 'scraper' ? 'Scraper Sync Started' : 'Sync Successful') : 'Sync Failed' }}
        </strong>
        @if(!empty($flash['message']))
          <div style="font-size: 0.875rem; margin-bottom: 0.5rem;">{{ $flash['message'] }}</div>
        @endif
        @if(!empty($flash['stats']) && is_array($flash['stats']))
          <div style="font-size: 0.875rem; margin-top: 0.5rem;">
            @foreach($flash['stats'] as $k => $v)
              <span class="modern-badge modern-badge-primary me-2">
                {{ ucfirst(str_replace('_',' ',$k)) }}: {{ $v }}
              </span>
            @endforeach
          </div>
        @endif
        @if(!empty($flash['type']) && $flash['type'] === 'scraper' && $flash['ok'])
          <div style="font-size: 0.875rem; margin-top: 0.75rem; padding: 0.75rem; background: rgba(255,255,255,0.5); border-radius: var(--radius);">
            <i class="bi bi-info-circle me-2"></i>
            The scraper is running in the background.
            @if(!empty($flash['log_file']))
              Check <code style="background: rgba(0,0,0,0.1); padding: 0.2rem 0.4rem; border-radius: 0.25rem;">storage/logs/{{ $flash['log_file'] }}</code> for progress.
            @endif
            Refresh this page in 1-2 minutes to see updated data.
          </div>
        @endif
      </div>
      <button type="button" class="btn-close" onclick="this.parentElement.remove()" aria-label="Close"></button>
    </div>
  @endif

  {{-- Modern Filters --}}
  <div class="modern-card mb-4">
    <div class="modern-card-header">
      <h5 class="mb-0" style="font-weight: 600;">
        <i class="bi bi-funnel me-2" style="color: var(--primary);"></i>
        Filters
      </h5>
    </div>
    <form method="GET" action="{{ route('acs.daily.index') }}" class="modern-card-body">
    <div class="row g-2">
      <div class="col-md-2">
        <label class="modern-form-label">From</label>
        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="modern-form-input">
      </div>
      <div class="col-md-2">
        <label class="modern-form-label">To</label>
        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="modern-form-input">
      </div>

      <div class="col-md-3">
        <label class="modern-form-label">Name</label>
        <input type="text" name="name" value="{{ $filters['name'] ?? '' }}" class="modern-form-input" placeholder="e.g. Sohail">
      </div>
      <div class="col-md-2">
        <label class="modern-form-label">Person Code</label>
        <input type="text" name="person_code" value="{{ $filters['person_code'] ?? '' }}" class="modern-form-input">
      </div>

      {{-- Optional single-date (fallback if range empty) --}}
      <div class="col-md-2">
        <label class="modern-form-label">Date</label>
        <input type="date" name="date" value="{{ $filters['date'] ?? ($filters['date_from'] ?? $filters['date_to'] ?? now($tz)->toDateString()) }}" class="modern-form-input">
      </div>

      <div class="col-md-2">
        <label class="modern-form-label">Per Page</label>
        @php $pp = (int)($filters['perPage'] ?? 25); @endphp
        <select name="perPage" class="modern-form-input">
          @foreach([25,50,100,200] as $opt)
            <option value="{{ $opt }}" {{ $pp===$opt?'selected':'' }}>{{ $opt }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-4 d-flex align-items-end gap-2">
        <button class="btn-modern btn-modern-success">
          <i class="bi bi-funnel"></i>
          <span>Apply</span>
        </button>
        <a href="{{ route('acs.daily.index') }}" class="btn-modern" style="background: var(--gray-600); color: white;">
          <i class="bi bi-arrow-counterclockwise"></i>
          <span>Reset</span>
        </a>
      </div>
    </div>
    </form>
  </div>

  {{-- Filter summary chips --}}
  @php
    $chips = [];
    if(!empty($filters['name']))        $chips[] = ['label'=>'Name','val'=>$filters['name'],'dot'=>'#0d6efd'];
    if(!empty($filters['person_code'])) $chips[] = ['label'=>'Code','val'=>$filters['person_code'],'dot'=>'#20c997'];

    if(!empty($filters['date_from']) || !empty($filters['date_to'])) {
      $chips[] = ['label'=>'Range','val'=>($filters['date_from'] ?? '—').' → '.($filters['date_to'] ?? '—'),'dot'=>'#fd7e14'];
    } elseif(!empty($filters['date'])) {
      $chips[] = ['label'=>'Date','val'=>$filters['date'],'dot'=>'#6f42c1'];
    }

    if(!empty($filters['source']))      $chips[] = ['label'=>'Source','val'=>$filters['source'],'dot'=>'#0dcaf0'];
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

  {{-- Modern Table --}}
  <div class="modern-card">
    <div class="modern-card-header">
      <h5 class="mb-0" style="font-weight: 600;">
        <i class="bi bi-table me-2" style="color: var(--primary);"></i>
        Attendance Records
      </h5>
    </div>
    <div class="table-responsive">
      <table class="modern-table">
        <thead>
          <tr>
            <th style="width:60px">#</th>
            <th style="width:70px">Photo</th>
            <th>Name &amp; Code</th>
            <th>Person Code</th>
            <th>Group</th>
            <th>Date</th>
            <th>Check-in</th>
            <th>Check-out</th>
            <th>Source</th>
          </tr>
        </thead>
        <tbody>
        @forelse($page as $i => $row)
          @php
            $idx    = ($page->currentPage()-1)*$page->perPage() + $i + 1;
            $photo  = $row['photo_url'] ?? null;
            $name   = $row['full_name'] ?: trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''));
            $name   = $name ?: '—';
            $inT    = $row['first_event']->occur_time_pk->copy()->timezone($tz)->format('H:i');
            $outT   = $row['last_event']->occur_time_pk->copy()->timezone($tz)->format('H:i');
            $date   = $row['occur_date_pk'] ?? $row['first_event']->occur_time_pk->copy()->timezone($tz)->toDateString();
            $inSrc  = $row['in_source']  ?? '';
            $outSrc = $row['out_source'] ?? '';
          @endphp
          <tr>
            <td class="text-muted">{{ $idx }}</td>
            <td>
              @if($photo)
                <img src="{{ $photo }}" class="avatar-36" alt="photo">
              @else
                <div class="bg-secondary text-white initial-36">{{ strtoupper(substr($name,0,1)) }}</div>
              @endif
            </td>
            <td>
              <div class="fw-semibold">{{ $name }}</div>
            </td>
            <td><div class="fw-semibold">{{ $row['display_code'] }}</div></td>
            <td>{{ $row['group_name'] ?? '—' }}</td>
            <td>{{ $date }}</td>
            <td>
              <div class="fw-semibold">{{ $inT }}</div>
            </td>
            <td>
              <div class="fw-semibold">{{ $outT }}</div>
            </td>
            <td>
              <div class="d-flex flex-column gap-1">
                <span class="modern-badge {{ $inSrc==='Mobile'?'modern-badge-info':'modern-badge-primary' }}" data-bs-toggle="tooltip" title="First punch source">
                  IN: {{ $inSrc ?: '—' }}
                </span>
                <span class="modern-badge {{ $outSrc==='Mobile'?'modern-badge-info':'modern-badge-primary' }}" data-bs-toggle="tooltip" title="Last punch source">
                  OUT: {{ $outSrc ?: '—' }}
                </span>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="text-center text-muted py-5">
              <div class="mb-1">No records found</div>
              <div class="small">Try adjusting filters or date.</div>
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="modern-card-footer d-flex justify-content-between align-items-center">
      <div style="font-size: 0.875rem; color: var(--gray-600); font-weight: 500;">
        <i class="bi bi-list-ul me-2"></i>Total: <strong>{{ $page->total() }}</strong> records
      </div>
      <div>
        {{ $page->links() }}
      </div>
    </div>
  </div>
</div>

{{-- Timeline modal (optional; if you add a trigger button) --}}
<div class="modal fade" id="timelineModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="timelineTitle">Timeline</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="timelineBody"></div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // Auto-hide sync alert after 4.5s
  setTimeout(() => {
    const el = document.getElementById('syncAlert');
    if (!el) return;
    bootstrap.Alert.getOrCreateInstance(el).close();
  }, 4500);

  // Enable tooltips
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
  });

  // Range UX: if range filled, clear single date
  const from = document.querySelector('input[name="date_from"]');
  const to   = document.querySelector('input[name="date_to"]');
  const single = document.querySelector('input[name="date"]');
  if (from && to) {
    const syncBounds = () => {
      if (to.value && from.value && to.value < from.value) to.value = from.value;
      if (from.value) to.min = from.value;
      if (to.value)   from.max = to.value;
      if ((from.value || to.value) && single) single.value = '';
    };
    from.addEventListener('change', syncBounds);
    to.addEventListener('change', syncBounds);
  }

  function openTimeline(person_code, date) {
    const title = document.getElementById('timelineTitle');
    const body  = document.getElementById('timelineBody');
    title.textContent = `Timeline — ${person_code} — ${date}`;
    body.innerHTML = `
      <div class="py-5 text-center text-muted">
        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
        Loading…
      </div>`;

    fetch(`{{ route('acs.daily.timeline') }}?person_code=${encodeURIComponent(person_code)}&date=${encodeURIComponent(date)}`)
      .then(r => r.json())
      .then(j => {
        if (!j.ok) { body.innerHTML = '<div class="text-danger">Failed to load.</div>'; return; }
        if (!j.events || j.events.length === 0) { body.innerHTML = '<div class="text-muted">No events.</div>'; return; }
        let html = `
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>Time</th><th>Device / Reader</th><th>Dir</th><th>Auth</th><th>Event</th><th>Card</th><th style="width:220px">GUID</th>
              </tr>
            </thead>
            <tbody>`;
        j.events.forEach(ev => {
          const t = new Date(ev.occur_time_pk).toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit'});
          html += `<tr>
            <td class="fw-semibold">${t}</td>
            <td>${(ev.device_name||'')+' / '+(ev.card_reader_name||'')}</td>
            <td>${ev.direction ?? ''}</td>
            <td>${ev.swipe_auth_result ?? ''}</td>
            <td>${ev.event_type ?? ''}</td>
            <td>${ev.card_number ?? ''}</td>
            <td class="truncate-1" title="${ev.record_guid||''}">${ev.record_guid||''}</td>
          </tr>`;
        });
        html += '</tbody></table>';
        body.innerHTML = html;
      })
      .catch(() => { body.innerHTML = '<div class="text-danger">Failed to load.</div>'; });

    const m = new bootstrap.Modal(document.getElementById('timelineModal'));
    m.show();
  }
</script>
@endpush
