@extends('layouts.master')



@section('content')
<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Daily Attendance</h4>
    <div class="d-flex gap-2">
      {{-- Quick Reload (no server sync) --}}
      <a href="{{ route('acs.daily.index', request()->query()) }}" class="btn btn-outline-secondary btn-icon">
        <i class="bi bi-arrow-clockwise"></i> Reload
      </a>
      {{-- Sync Now --}}
      <form action="{{ route('acs.daily.syncNow', request()->query()) }}" method="POST"
            onsubmit="this.querySelector('button').disabled=true;">
        @csrf
        <button type="submit" class="ml-3 btn btn-primary btn-icon">
          <i class="bi bi-cloud-arrow-down"></i> Sync Now
        </button>
      </form>
    </div>
  </div>

  {{-- Flash (sync results) - reads $flash OR session("flash") --}}
  @php $flash = $flash ?? session('flash'); @endphp
  @if(!empty($flash))
    <div class="alert alert-{{ $flash['ok'] ? 'success' : 'warning' }} alert-dismissible fade show shadow-sm-2 card-flat" id="syncAlert" role="alert">
      <div class="d-flex align-items-start">
        <div class="me-2 mt-1">{!! $flash['ok'] ? '&#9989;' : '&#9888;&#65039;' !!}</div>
        <div>
          <strong>{{ $flash['ok'] ? 'Sync Successful' : 'Sync Completed (Warnings)' }}</strong>
          @if(!empty($flash['message']))
            <div class="small">{{ $flash['message'] }}</div>
          @endif
          @if(!empty($flash['stats']) && is_array($flash['stats']))
            <div class="small text-muted mt-1">
              @foreach($flash['stats'] as $k => $v)
                <span class="me-2"><span class="text-dark">{{ ucfirst(str_replace('_',' ',$k)) }}:</span> {{ $v }}</span>
              @endforeach
            </div>
          @endif
          @if(empty($flash['message']) && empty($flash['stats']))
            <details class="small mt-1"><summary>Details</summary>
              <pre class="mb-0" style="white-space:pre-wrap">{{ json_encode($flash, JSON_PRETTY_PRINT) }}</pre>
            </details>
          @endif
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- Filters --}}
  <form method="GET" action="{{ route('acs.daily.index') }}" class="card card-body mb-2 card-flat shadow-sm-2">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label mini">Name</label>
        <input type="text" name="name" value="{{ $filters['name'] ?? '' }}" class="form-control" placeholder="e.g. Sohail">
      </div>
      <div class="col-md-2">
        <label class="form-label mini">Person Code</label>
        <input type="text" name="person_code" value="{{ $filters['person_code'] ?? '' }}" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label mini">Date</label>
        <input type="date" name="date" value="{{ $filters['date'] ?? now($tz)->toDateString() }}" class="form-control">
      </div>
    
      <div class="col-md-2">
        <label class="form-label mini">Per Page</label>
        @php $pp = (int)($filters['perPage'] ?? 25); @endphp
        <select name="perPage" class="form-control">
          @foreach([25,50,100,200] as $opt)
            <option value="{{ $opt }}" {{ $pp===$opt?'selected':'' }}>{{ $opt }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3 d-flex align-items-end gap-2">
        <button class="btn btn-success btn-icon mr-3"><i class="bi bi-funnel"></i> Apply</button>
        <a href="{{ route('acs.daily.index') }}" class="btn btn-outline-secondary">Reset</a>
      </div>
    </div>
  </form>

  {{-- Filter summary chips --}}
  @php
    $chips = [];
    if(!empty($filters['name'])) $chips[] = ['label'=>'Name','val'=>$filters['name'],'dot'=>'#0d6efd'];
    if(!empty($filters['person_code'])) $chips[] = ['label'=>'Code','val'=>$filters['person_code'],'dot'=>'#20c997'];
    if(!empty($filters['date'])) $chips[] = ['label'=>'Date','val'=>$filters['date'],'dot'=>'#6f42c1'];
    if(!empty($filters['source'])) $chips[] = ['label'=>'Source','val'=>$filters['source'],'dot'=>'#0dcaf0'];
  @endphp
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div class="d-flex flex-wrap gap-2">
      <span class="chip"><span class="dot" style="background:#0d6efd"></span> Total <strong>{{ $page->total() }}</strong></span>
      @foreach($chips as $c)
        <span class="chip"><span class="dot" style="background:{{ $c['dot'] }}"></span> {{ $c['label'] }}: <strong>{{ $c['val'] }}</strong></span>
      @endforeach
    </div>
  </div>

  {{-- Table --}}
  <div class="card card-flat shadow-sm-2">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0 table-sticky">
        <thead class="table-light">
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
            <td>
              <div class="fw-semibold">{{ $row['display_code'] }}</div>
              </td>
            <td>{{ $row['group_name'] ?? '—' }}</td>
            <td>{{ $date }}</td>
            <td>
              <div class="fw-semibold">{{ $inT }}</div>
              <!--@if($row['in_device_name'])-->
              <!--  <div class="small text-muted">{{ $row['in_device_name'] }}</div>-->
              <!--@endif-->
            </td>
            <td>
              <div class="fw-semibold">{{ $outT }}</div>
              <!--@if($row['out_device_name'])-->
              <!--  <div class="small text-muted">{{ $row['out_device_name'] }}</div>-->
              <!--@endif-->
            </td>
            <td>
              <div class="small d-flex flex-column gap-1">
                <span class="badge {{ $inSrc==='Mobile'?'text-bg-info':'text-bg-primary' }} badge-soft" data-bs-toggle="tooltip" title="First punch source">IN: {{ $inSrc ?: '—' }}</span>
                <span class="badge {{ $outSrc==='Mobile'?'text-bg-info':'text-bg-primary' }} badge-soft" data-bs-toggle="tooltip" title="Last punch source">OUT: {{ $outSrc ?: '—' }}</span>
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
    <div class="card-footer d-flex justify-content-between align-items-center">
      <div class="small text-muted">Total: {{ $page->total() }}</div>
      {{ $page->links() }}
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
