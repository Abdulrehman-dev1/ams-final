@extends('layouts.master')

@push('styles')
<style>
  /* --- tiny helpers --- */
  .card-flat{border:1px solid #eef0f3;border-radius:.75rem}
  .shadow-sm-2{box-shadow:0 .125rem .6rem rgba(0,0,0,.06)}
  .mini{font-size:.82rem;color:#6c757d}
  .btn-icon i{margin-right:.35rem}
  .chip{display:inline-flex;gap:.4rem;align-items:center;background:#f7f8fa;border:1px solid #eef0f3;border-radius:999px;padding:.15rem .6rem;font-size:.8rem}
  .chip .dot{width:.45rem;height:.45rem;border-radius:50%}
  .table-sticky thead th{position:sticky;top:0;background:#f8f9fa;z-index:2}
  .table thead th{font-weight:600;letter-spacing:.2px}
  .table-hover tbody tr:hover{background:rgba(0,0,0,.03)}
  .nowrap{white-space:nowrap}
  .clip-1{max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .badge-soft{border:1px solid rgba(0,0,0,.08)}
</style>
@endpush

@section('content')
<div class="container-fluid py-4">

  {{-- Header + Filters --}}
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Attendance Records</h4>

    <form method="get" class="d-flex gap-2">
      {{-- keep date hidden per your code, but style-ready if you show it later --}}
      <input type="date" name="date" value="{{ $meta['filters']['date'] ?? '' }}" class="form-control form-control-sm d-none"/>
      <input type="text" name="person_code" value="{{ $meta['filters']['person_code'] ?? '' }}" class="mt-1 form-control form-control-lg" placeholder="Person Code">
      <button class="btn btn-lg btn-primary btn-icon mx-3">Filter</button>
      <a href="{{ route('admin.attendances.index') }}" class="btn btn-lg btn-outline-secondary">Reset</a>
    </form>
  </div>

  {{-- Filter summary chips --}}
  @php
    $chips = [];
    if(!empty($meta['filters']['date'])) $chips[] = ['k'=>'Date','v'=>$meta['filters']['date'],'c'=>'#6f42c1'];
    if(!empty($meta['filters']['person_code'])) $chips[] = ['k'=>'Code','v'=>$meta['filters']['person_code'],'c'=>'#20c997'];
  @endphp
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div class="d-flex flex-wrap gap-2">
      <span class="chip"><span class="dot" style="background:#0d6efd"></span>Total <strong>{{ $attendances->total() }}</strong></span>
      @foreach($chips as $c)
        <span class="chip"><span class="dot" style="background:{{ $c['c'] }}"></span>{{ $c['k'] }}: <strong>{{ $c['v'] }}</strong></span>
      @endforeach
    </div>
    {{-- right-space for future buttons (CSV, etc.) --}}
  </div>

  {{-- Table --}}
  <div class="card card-flat shadow-sm-2">
    <div class="card-body p-0">
      <div class="table-responsive" style="max-width:100%;overflow-x:auto;">
        <table class="table table-striped table-hover align-middle mb-0 table-sticky nftmax-table">
          <thead class="table-light">
            <tr>
              @foreach($columns as $col)
                <th class="text-nowrap">{{ $labels[$col] ?? Str::headline($col) }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @forelse($attendances as $row)
              <tr>
                @foreach($columns as $col)
                  @php
                    $val = data_get($row, $col);
                    $display = ($val === null || (is_string($val) && trim($val) === '')) ? '-' : $val;

                    $lower = strtolower($col);

                    // date-like
                    if ($display !== '-' && (Str::endsWith($lower, '_date') || in_array($lower, [
                      'date','attendance_date','check_in_date','check_out_date',
                      'clock_in_date','clock_out_date','created_at','updated_at'
                    ], true))) {
                      try { $display = \Illuminate\Support\Carbon::parse($val)->format('Y-m-d'); } catch (\Throwable $e) {}
                    }

                    // time-like
                    if ($display !== '-' && (Str::endsWith($lower, '_time') || in_array($lower, [
                      'time','attendance_time','check_in_time','check_out_time',
                      'clock_in_time','clock_out_time'
                    ], true))) {
                      try {
                        $t = is_string($val) ? $val : (string)$val;
                        $display = \Illuminate\Support\Str::of($t)
                          ->replaceMatches('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', '$1:$2');
                      } catch (\Throwable $e) {}
                    }

                    // soft badges for status-ish columns
                    $asBadge = false; $badgeClass = 'bg-secondary';
                    if (in_array($lower, ['status','attendance_status','attendance_status_code'])) {
                      $asBadge = true;
                      $valStr = strtolower((string)$display);
                      if (str_contains($valStr, 'present') || str_contains($valStr, 'in') || str_contains($valStr, 'ok')) $badgeClass = 'bg-success';
                      elseif (str_contains($valStr, 'late') || str_contains($valStr, 'early')) $badgeClass = 'bg-warning';
                      elseif (str_contains($valStr, 'absent') || str_contains($valStr, 'miss') ) $badgeClass = 'bg-danger';
                      elseif (str_contains($valStr, 'leave') || str_contains($valStr, 'off')) $badgeClass = 'bg-info';
                    }
                  @endphp

                  <td class="text-nowrap">
                    @if($asBadge && $display !== '-')
                      <span class="badge {{ $badgeClass }} badge-soft">{{ $display }}</span>
                    @else
                      <span class="clip-1" title="{{ is_scalar($display)? $display : '' }}">{{ $display }}</span>
                    @endif
                  </td>
                @endforeach
              </tr>
            @empty
              <tr>
                <td colspan="{{ count($columns) }}" class="text-center text-muted py-5">
                  No records found.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="p-3">
        {{ $attendances->links() }}
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
  {{-- bootstrap icons (optional – only if you’ll use icons) --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
@endpush
