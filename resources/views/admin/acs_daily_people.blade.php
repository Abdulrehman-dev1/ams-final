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
