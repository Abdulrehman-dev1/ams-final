<!-- App favicon -->
<link rel="shortcut icon" href="{{ URL::asset('assets/images/') }}">
<meta name="viewport" content="width=device-width, initial-scale=1">      
@yield('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
 <!-- App css -->
<link href="{{ URL::asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ URL::asset('assets/css/metismenu.min.css') }}" rel="stylesheet" type="text/css">
<link href="{{ URL::asset('assets/css/icons.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ URL::asset('assets/css/style.css') }}" rel="stylesheet" type="text/css" />

{{-- <link href="{{ URL::asset('plugins/sweet-alert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css"> --}}
<link href="{{ asset('plugins/sweetalert.min.css') }}" rel="stylesheet">
<!-- Table css -->
<link href="{{ URL::asset('plugins/RWD-Table-Patterns/dist/css/rwd-table.min.css') }}" rel="stylesheet" type="text/css" media="screen">
<!-- DataTables -->
<link href="{{ URL::asset('plugins/datatables/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ URL::asset('plugins/datatables/buttons.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<!-- Responsive datatable examples -->
<link href="{{ URL::asset('plugins/datatables/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<style>
  /* tiny UI helpers */
  .card-flat{border:1px solid #eef0f3;border-radius:.75rem}
  .shadow-sm-2{box-shadow:0 .125rem .5rem rgba(0,0,0,.06)}
  .mini{font-size:.8rem;color:#6c757d}
  .avatar-36{width:36px;height:36px;object-fit:cover;border-radius:50%}
  .initial-36{width:36px;height:36px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:600}
  .chip{display:inline-flex;gap:.35rem;align-items:center;background:#f7f8fa;border:1px solid #eef0f3;border-radius:999px;padding:.15rem .55rem;font-size:.8rem}
  .chip .dot{width:.45rem;height:.45rem;border-radius:50%}
  .badge-soft{border:1px solid rgba(0,0,0,.08)}
  .truncate-1{max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .table-sticky thead th{position:sticky;top:0;background:#f8f9fa;z-index:1}
  .btn-icon i{margin-right:.35rem}
</style>