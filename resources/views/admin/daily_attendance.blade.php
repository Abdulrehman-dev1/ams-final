@extends('layouts.master')

@section('css')
  <link href="https://cdn.datatables.net/v/bs5/dt-2.1.5/b-3.1.1/r-3.0.3/datatables.min.css" rel="stylesheet"/>
  <style>
    .avatar { width:48px; height:48px; object-fit:cover; border-radius:8px; }
  </style>
@endsection

@section('breadcrumb')
<div class="col-sm-6">
  <h4 class="page-title text-left">Daily Attendance (Persons)</h4>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="javascript:void(0);">Home</a></li>
    <li class="breadcrumb-item"><a href="javascript:void(0);">Attendance</a></li>
    <li class="breadcrumb-item active">Daily Attendance</li>
  </ol>
</div>
@endsection

@section('button')
  <form action="{{ route('admin.hik.persons.sync') }}" method="POST" class="d-inline"
        onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='Syncing…';">
    @csrf
    <input type="hidden" name="pageSize" value="100">
    <input type="hidden" name="maxPages" value="50">
    <button type="submit" class="btn btn-primary btn-sm btn-flat">
      <i class="mdi mdi-cloud-download mr-2"></i> Sync Now
    </button>
  </form>
@endsection

@section('content')
@include('includes.flash')

@if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">

        <table id="datatable-buttons" class="table table-striped table-bordered dt-responsive nowrap"
               style="border-collapse: collapse; border-spacing: 0; width: 100%;">
          <thead>
            <tr>
              <th>Profile</th>
              <th>Full Name</th>
              <th>Phone</th>
              <th>Person Code</th>
              <th>Start Date</th>
              <th>End Date</th>
            </tr>
          </thead>
          <tbody>
            @foreach($rows as $r)
              @php
                $name = $r->full_name ?: trim(($r->first_name ?? '').' '.($r->last_name ?? ''));
              @endphp
              <tr>
                <td>
                  @if(!empty($r->head_pic_url))
                    <img src="{{ $r->head_pic_url }}" class="avatar"
                         onerror="this.onerror=null;this.src='https://via.placeholder.com/48x48?text=%20';" alt="pic">
                  @else
                    <img src="https://via.placeholder.com/48x48?text=%20" class="avatar" alt="pic">
                  @endif
                </td>
                <td>{{ $name ?: '—' }}</td>
                <td>
                  @if($r->phone)
                    <a href="tel:{{ $r->phone }}">{{ $r->phone }}</a>
                  @else
                    —
                  @endif
                </td>
                <td>{{ $r->person_code ?: '—' }}</td>
                <td>{{ optional($r->start_date)->format('Y-m-d') ?: '—' }}</td>
                <td>{{ optional($r->end_date)->format('Y-m-d') ?: '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>

      </div>
    </div>
  </div>
</div>
@endsection

@section('script')
  <script src="https://cdn.datatables.net/v/bs5/dt-2.1.5/b-3.1.1/r-3.0.3/datatables.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      new DataTable('#datatable-buttons', {
        responsive: true,
        pageLength: 25,
        lengthMenu: [[10,25,50,100,-1],[10,25,50,100,'All']],
        order: [[1,'asc']], // by Full Name
        language: {
          search: "Search:",
          lengthMenu: "Show _MENU_",
          info: "Showing _START_ to _END_ of _TOTAL_ entries"
        }
      });
    });
  </script>
@endsection
