@extends('layouts.master')
@section('content')
<div class="container-fluid">

  {{-- Flash (sync results) --}}
  @if(!empty($flash))
    <div class="alert alert-{{ $flash['ok'] ? 'success' : 'warning' }} mb-3">
      <strong>Sync Summary:</strong>
      <pre class="mb-0" style="white-space:pre-wrap">{{ json_encode($flash, JSON_PRETTY_PRINT) }}</pre>
    </div>
  @endif

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Daily Attendance</h4>
    <form action="{{ route('rollup.syncAll') }}" method="POST" id="syncForm">
      @csrf
      <button type="submit" class="btn btn-primary" id="syncBtn">
        <span class="spinner-border spinner-border-sm d-none" id="syncSpin" role="status"></span>
        <span id="syncText">Sync Now (T-1 Attendance → Today ACS → Today Roll-up)</span>
      </button>
    </form>
  </div>

  {{-- Filters --}}
  <form method="GET" action="{{ route('rollup.index') }}" class="card card-body mb-3">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">Name</label>
        <input type="text" name="name" value="{{ $filters['name'] ?? '' }}" class="form-control" placeholder="e.g. Sohail">
      </div>
      <div class="col-md-2">
        <label class="form-label">Person Code</label>
        <input type="text" name="person_code" value="{{ $filters['person_code'] ?? '' }}" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">Date</label>
        <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">Month</label>
        <input type="month" name="month" value="{{ $filters['month'] ?? '' }}" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">Source</label>
        <select name="source" class="form-select">
          @php $src = $filters['source'] ?? ''; @endphp
          <option value="">Any</option>
          <option value="Device"   {{ $src==='Device'?'selected':'' }}>Device</option>
          <option value="Mobile"   {{ $src==='Mobile'?'selected':'' }}>Mobile</option>
          <option value="Unknown"  {{ $src==='Unknown'?'selected':'' }}>Unknown</option>
        </select>
      </div>
      <div class="col-md-1 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="provisional" value="1" id="prov" {{ !empty($filters['provisional'])?'checked':'' }}>
          <label class="form-check-label" for="prov">Provisional</label>
        </div>
      </div>
      <div class="col-md-2">
        <label class="form-label">Has</label>
        @php $has = $filters['has'] ?? ''; @endphp
        <select name="has" class="form-select">
          <option value="">--</option>
          <option value="late"  {{ $has==='late'?'selected':'' }}>Late</option>
          <option value="early" {{ $has==='early'?'selected':'' }}>Early Leave</option>
          <option value="ot"    {{ $has==='ot'?'selected':'' }}>Overtime</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Per Page</label>
        <select name="perPage" class="form-select">
          @php $pp = (int)($filters['perPage'] ?? 25); @endphp
          @foreach([25,50,100,200] as $opt)
            <option value="{{ $opt }}" {{ $pp===$opt?'selected':'' }}>{{ $opt }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3 d-flex align-items-end gap-2">
        <button class="btn btn-success">Apply</button>
        <a href="{{ route('rollup.index') }}" class="btn btn-outline-secondary">Reset</a>
      </div>
    </div>
  </form>

  {{-- Table --}}
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Photo</th>
            <th>Name & Code</th>
            <th>Group</th>
            <th>Date</th>
            <th>Expected</th>
            <th>In (Actual)</th>
            <th>Out (Actual)</th>
            <th>Source</th>
            <th class="text-center">Late</th>
            <th class="text-center">Early</th>
            <th class="text-center">OT</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        @forelse($page as $i => $row)
          @php
            $idx = ($page->currentPage()-1)*$page->perPage() + $i + 1;
            $photo = $row->photo_url;
            $name  = $row->full_name ?: trim(($row->first_name ?? '').' '.($row->last_name ?? ''));
            $name  = $name ?: '—';
            $expected = trim(($row->expected_in ?? '—').' / '.($row->expected_out ?? '—'));
            // Display times in app timezone
            $tz = config('app.timezone','Asia/Karachi');
            $inA  = $row->in_actual  ? \Illuminate\Support\Carbon::parse($row->in_actual)->setTimezone($tz)->format('H:i') : '—';
            $outA = $row->out_actual ? \Illuminate\Support\Carbon::parse($row->out_actual)->setTimezone($tz)->format('H:i') : '—';
          @endphp
          <tr>
            <td>{{ $idx }}</td>
            <td>
              @if($photo)
                <img src="{{ $photo }}" style="width:36px;height:36px;object-fit:cover;border-radius:50%;">
              @else
                <div class="bg-secondary text-white d-inline-flex justify-content-center align-items-center" style="width:36px;height:36px;border-radius:50%">{{ strtoupper(substr($name,0,1)) }}</div>
              @endif
            </td>
            <td>
              <div class="fw-semibold">{{ $name }}</div>
              <div class="text-muted small">#{{ $row->person_code }}</div>
            </td>
            <td>{{ $row->group_name ?? '—' }}</td>
            <td>{{ $row->date }}</td>
            <td class="text-muted">{{ $expected }}</td>
            <td>
              {{ $inA }}
              @if($row->in_source_provisional)
                <span class="badge text-bg-secondary">Provisional</span>
              @endif
            </td>
            <td>
              {{ $outA }}
              @if($row->out_source_provisional)
                <span class="badge text-bg-secondary">Provisional</span>
              @endif
            </td>
            <td>
              <div class="small">
                <span class="badge {{ $row->in_source==='Mobile'?'text-bg-info':($row->in_source==='Device'?'text-bg-primary':'text-bg-light') }}">IN: {{ $row->in_source ?? '—' }}</span>
                <span class="badge {{ $row->out_source==='Mobile'?'text-bg-info':($row->out_source==='Device'?'text-bg-primary':'text-bg-light') }}">OUT: {{ $row->out_source ?? '—' }}</span>
              </div>
            </td>
            <td class="text-center">
              {!! $row->late_minutes>0 ? '<span class="badge text-bg-danger">'.$row->late_minutes.'m</span>' : '—' !!}
            </td>
            <td class="text-center">
              {!! $row->early_leave_minutes>0 ? '<span class="badge text-bg-warning">'.$row->early_leave_minutes.'m</span>' : '—' !!}
            </td>
            <td class="text-center">
              {!! $row->overtime_minutes>0 ? '<span class="badge text-bg-success">'.$row->overtime_minutes.'m</span>' : '—' !!}
            </td>
            <td>
              <button class="btn btn-sm btn-outline-secondary" 
                      onclick="openTimeline('{{ $row->person_code }}','{{ $row->date }}')">
                Timeline
              </button>
            </td>
          </tr>
        @empty
          <tr><td colspan="13" class="text-center text-muted py-4">No records.</td></tr>
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

{{-- Timeline Modal --}}
<div class="modal fade" id="timelineModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="timelineTitle">Timeline</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="timelineBody" class="table-responsive">
          <div class="text-center text-muted py-4">Loading…</div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.getElementById('syncForm')?.addEventListener('submit', function(){
    document.getElementById('syncBtn').disabled = true;
    document.getElementById('syncSpin').classList.remove('d-none');
    document.getElementById('syncText').textContent = 'Syncing…';
  });

  function openTimeline(person_code, date) {
    const title = document.getElementById('timelineTitle');
    const body  = document.getElementById('timelineBody');
    title.textContent = `Timeline — ${person_code} — ${date}`;
    body.innerHTML = '<div class="text-center text-muted py-4">Loading…</div>';

    fetch(`{{ route('rollup.timeline') }}?person_code=${encodeURIComponent(person_code)}&date=${encodeURIComponent(date)}`)
      .then(r => r.json())
      .then(j => {
        if (!j.ok) { body.innerHTML = '<div class="text-danger">Failed to load.</div>'; return; }
        if (!j.events || j.events.length === 0) { body.innerHTML = '<div class="text-muted">No events.</div>'; return; }
        let html = '<table class="table table-sm"><thead><tr><th>Time</th><th>Device / Reader</th><th>Dir</th><th>Auth</th><th>Event</th><th>Card</th><th>GUID</th></tr></thead><tbody>';
        j.events.forEach(ev => {
          const t = new Date(ev.occur_time_pk).toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit'});
          html += `<tr>
            <td>${t}</td>
            <td>${(ev.device_name||'')+' / '+(ev.card_reader_name||'')}</td>
            <td>${ev.direction ?? ''}</td>
            <td>${ev.swipe_auth_result ?? ''}</td>
            <td>${ev.event_type ?? ''}</td>
            <td>${ev.card_number ?? ''}</td>
            <td style="max-width:220px; overflow:hidden; text-overflow:ellipsis;">${ev.record_guid}</td>
          </tr>`;
        });
        html += '</tbody></table>';
        body.innerHTML = html;
      });
    const m = new bootstrap.Modal(document.getElementById('timelineModal'));
    m.show();
  }
</script>
@endpush
