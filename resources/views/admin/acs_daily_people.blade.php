@extends('layouts.master')

@section('content')
<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Employees List</h4>
    <div class="d-flex gap-2">
      {{-- Quick Reload --}}
    <a href="{{ route('acs.people.index') }}" class="btn btn-outline-secondary btn-icon">
  <i class="bi bi-arrow-clockwise"></i> Reload
</a>
<form action="{{ route('acs.people.syncNow') }}" method="POST"
      onsubmit="this.querySelector('button').disabled=true;">
  @csrf
  <button type="submit" class="ml-3 btn btn-primary btn-icon">
    <i class="bi bi-cloud-arrow-down"></i> Sync Now
  </button>
</form>

    </div>
  </div>

  {{-- Flash --}}
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
                <span class="me-2"><span class="text-dark">{{ ucfirst(str_replace('_',' ',$k)) }}:</span> {{ is_scalar($v) ? $v : json_encode($v) }}</span>
              @endforeach
            </div>
          @endif
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif
{{-- Filters: POST to session (no nested forms) --}}
<form method="POST" action="{{ route('acs.people.filter') }}" class="card card-body mb-2 card-flat shadow-sm-2">
  @csrf
  <div class="row g-2">
    <div class="col-md-4">
      <label class="form-label mini">Name</label>
      <input type="text" name="name" value="{{ $filters['name'] ?? '' }}" class="form-control" placeholder="">
    </div>
    <div class="col-md-3">
      <label class="form-label mini">Person Code</label>
      <input type="text" name="person_code" value="{{ $filters['person_code'] ?? '' }}" class="form-control">
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
      {{-- Apply submits to acs.daily.filter (this form's action) --}}
      <button class="btn btn-success btn-icon mr-3"><i class="bi bi-funnel"></i> Apply</button>
  <button type="submit" class="btn btn-outline-secondary"
          formaction="{{ route('acs.people.filterReset') }}" formmethod="POST">
    Reset
  </button>
    </div>
  </div>
</form>


  {{-- Chips --}}
  @php
    $chips = [];
    if(!empty($filters['name']))        $chips[] = ['label'=>'Name','val'=>$filters['name'],'dot'=>'#0d6efd'];
    if(!empty($filters['person_code'])) $chips[] = ['label'=>'Code','val'=>$filters['person_code'],'dot'=>'#20c997'];
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
            <th>Name</th>
            <th>Phone</th>
            <th>Person Code</th>
            <th>Start Date</th>
            <th>End Date</th>
          </tr>
        </thead>
        <tbody>
        @forelse($page as $i => $row)
         @php
  $idx   = ($page->currentPage()-1)*$page->perPage() + $i + 1;
  $photo = $row['photo_url'] ?? null;

  // build a display name from full/first+last
  $nameRaw = trim(($row['full_name'] ?? '')
              ?: trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')));

  // first letter (supports Urdu/Arabic etc.)
  $initial = $nameRaw !== ''
      ? mb_strtoupper(mb_substr($nameRaw, 0, 1, 'UTF-8'), 'UTF-8')
      : ( !empty($row['person_code'])
          ? mb_strtoupper(mb_substr($row['person_code'], 0, 1, 'UTF-8'), 'UTF-8')
          : '—'
        );

  $name = $nameRaw !== '' ? $nameRaw : '—';
@endphp

          <tr>
            <td class="text-muted">{{ $idx }}</td>
            <td>
  @if(!empty($photo))
    <img src="{{ $photo }}" alt="photo"
         style="width:48px;height:48px;object-fit:cover;border-radius:8px;"
         onerror="this.onerror=null;this.src='https://via.placeholder.com/48x48?text=%20';">
  @else
    <div class="bg-secondary text-white d-inline-flex align-items-center justify-content-center"
         style="width:48px;height:48px;border-radius:8px;font-weight:600;">
      {{ $initial }}
    </div>
  @endif
</td>

            <td class="fw-semibold">{{ $name }}</td>
            <td>
              @if(!empty($row['phone']))
                <a href="tel:{{ $row['phone'] }}">{{ $row['phone'] }}</a>
              @else
                —
              @endif
            </td>
            <td>{{ $row['person_code'] ?? '—' }}</td>
            <td>{{ $row['start_date'] ?? '—' }}</td>
            <td>{{ $row['end_date'] ?? '—' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-center text-muted py-5">
              <div class="mb-1">No records found</div>
              <div class="small">Try adjusting filters.</div>
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
    if (window.bootstrap && bootstrap.Alert) {
      bootstrap.Alert.getOrCreateInstance(el).close();
    } else {
      el.remove();
    }
  }, 4500);
</script>
@endpush
