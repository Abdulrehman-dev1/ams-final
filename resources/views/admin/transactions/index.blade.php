@extends('layouts.master')

@section('breadcrumb')
@endsection

@section('css')
<style>
    /* Override any DataTables or RWD-Table interference */
    .transactions-wrapper .table-responsive {
        overflow-x: auto !important;
        overflow-y: visible !important;
        -webkit-overflow-scrolling: touch;
    }
    
    .transactions-wrapper .modern-table {
        width: 100% !important;
        table-layout: auto;
    }
    
    /* Ensure table cells stay on one line with balanced spacing */
    .transactions-wrapper .modern-table th,
    .transactions-wrapper .modern-table td {
        white-space: nowrap;
        padding: 0.875rem 1rem !important;
        vertical-align: middle;
    }
    .transactions-wrapper .modern-table th {
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
        line-height: 1.2;
        font-size: 0.8rem;
    }
    
    /* Allow longer text columns to shrink naturally while clipping overflow */
    .transactions-wrapper .modern-table td:nth-child(3),
    .transactions-wrapper .modern-table td:nth-child(4),
    .transactions-wrapper .modern-table td:nth-child(12),
    .transactions-wrapper .modern-table td:nth-child(13) {
        max-width: 220px;
        text-overflow: ellipsis;
        overflow: hidden;
    }
    
    .transactions-wrapper .modern-table th:nth-child(9),
    .transactions-wrapper .modern-table td:nth-child(9),
    .transactions-wrapper .modern-table th:nth-child(10),
    .transactions-wrapper .modern-table td:nth-child(10) {
        text-align: center;
    }
    
    /* Better font size for readability */
    .transactions-wrapper .modern-table td {
        font-size: 0.875rem;
        line-height: 1.2;
    }
    
    .transactions-wrapper .modern-table th {
        font-size: 0.8125rem;
    }
    
    /* Mobile optimizations */
    @media (max-width: 767px) {
        .transactions-wrapper .modern-table th,
        .transactions-wrapper .modern-table td {
            padding: 0.625rem 0.75rem !important;
            font-size: 0.8125rem;
        }
        
        .transactions-wrapper .modern-table th {
            font-size: 0.7rem;
            white-space: nowrap;
        }
    }
    
    /* Better scrollbar styling */
    .transactions-wrapper .table-responsive::-webkit-scrollbar {
        height: 8px;
    }
    
    .transactions-wrapper .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .transactions-wrapper .table-responsive::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }
    
    .transactions-wrapper .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    
    .transactions-wrapper .transaction-location {
        font-size: 0.75rem;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .transactions-wrapper .location-label {
        font-size: 0.75rem;
        margin-top: 0.25rem;
        max-width: 220px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .map-preview iframe {
        width: 435px !important;
        height: 350px !important;
        border: 0;
        border-radius: 0.75rem;
    }
    
    .swal-map-modal {
        border-radius: 1rem !important;
        padding: 1.5rem !important;
    }
    
    .swal-map-modal .swal2-html-container {
        margin: 0;
        padding: 0;
    }
    
    .map-modal {
        text-align: left;
    }
    
    .map-modal__header {
        margin-bottom: 1.25rem;
    }
    
    .map-modal__title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #111d4a;
        margin-bottom: 0.25rem;
    }
    
    .map-modal__subtitle {
        font-size: 0.9rem;
        color: #5a6174;
    }
    
    .map-modal__footer {
        margin-top: 1.25rem;
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .map-modal__footer .btn {
        border-radius: 999px;
        padding: 0.45rem 1.25rem;
        font-size: 0.85rem;
    }
    
    .map-modal__footer .btn-outline-primary {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .map-modal__footer .btn-outline-primary:hover {
        background: var(--primary);
        color: #fff;
    }
    
    .transactions-wrapper .map-meta {
        margin-top: 0.75rem;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    
    /* Legacy SweetAlert button styling */
    .swal-button.swal-button--confirm {
        background: var(--primary);
        border-radius: 999px;
        padding: 0.45rem 1.5rem;
        font-weight: 600;
        box-shadow: none;
        transition: background 0.2s ease, transform 0.2s ease;
    }
    
    .swal-button.swal-button--confirm:hover {
        background: var(--primary-dark, #0b5ed7);
        transform: translateY(-1px);
    }
    
    .swal-button.swal-button--cancel {
        border-radius: 999px;
        padding: 0.45rem 1.25rem;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    @if(session('transaction_flash'))
        @php($flash = session('transaction_flash'))
        <div class="alert alert-{{ $flash['type'] ?? 'info' }} d-flex flex-column gap-2">
            <div class="fw-semibold">{{ $flash['title'] ?? 'Status' }}</div>
            <div>{{ $flash['message'] ?? '' }}</div>
            @if(!empty($flash['output']))
                <pre class="mb-0 bg-light p-2 rounded" style="white-space: pre-wrap;">{{ $flash['output'] }}</pre>
            @endif
        </div>
    @endif
    <div class="modern-card mb-4">
        <div class="modern-card-header">
            <h5 class="mb-0">
                <i class="bi bi-funnel me-2" style="color: var(--primary);"></i>
                Filters
            </h5>
        </div>
        <form method="GET" action="{{ route('admin.transactions.index') }}" class="modern-card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="modern-form-label">Person Code</label>
                    <input type="text" name="person_code" value="{{ $filters['person_code'] }}"
                           class="modern-form-input" placeholder="Search by code">
                </div>
                <div class="col-md-3">
                    <label class="modern-form-label">Name</label>
                    <input type="text" name="name" value="{{ $filters['name'] }}"
                           class="modern-form-input" placeholder="Search by name">
                </div>
                <div class="col-md-3">
                    <label class="modern-form-label">Date</label>
                    <input type="date" name="date" value="{{ $filters['date'] }}"
                           class="modern-form-input">
                </div>
                <div class="col-md-3">
                    <label class="modern-form-label">Per Page</label>
                    <select name="perPage" class="modern-form-input">
                        @foreach([10, 25, 50, 100, 200] as $option)
                            <option value="{{ $option }}" {{ $option == $filters['perPage'] ? 'selected' : '' }}>
                                {{ $option }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex align-items-center justify-content-end " style="gap: 8px; margin-top: 1rem;">
                    <button type="submit" class="btn-modern btn-modern-success">
                        <i class="bi bi-funnel"></i>
                        <span>Apply</span>
                    </button>
                    <a href="{{ route('admin.transactions.index') }}"
                       class="btn-modern" style="background: var(--gray-600); color: white;">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span>Reset</span>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="modern-card transactions-wrapper">
        <div class="modern-card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 0.75rem;">
            <h5 class="mb-0" style="font-weight: 600;">
                <i class="bi bi-table me-2" style="color: var(--primary);"></i>
                Transaction Records
            </h5>
            <div class="d-flex align-items-center flex-wrap" style="gap: 0.75rem;">
                <form action="{{ route('admin.transactions.build') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="btn-modern btn-modern-warning"
                            title="Run php artisan transactions:build to pull the latest transactions">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        <span class="d-none d-sm-inline">Sync Transactions</span>
                        <span class="d-inline d-sm-none">Sync</span>
                    </button>
                </form>
                <span class="modern-badge modern-badge-primary">
                    Total: <strong>{{ $transactions->total() }}</strong>
                </span>
            </div>
        </div>

        <!-- Table View (All Devices) -->
        <div class="table-responsive" style="overflow-x: auto !important; overflow-y: visible !important;">
            <table class="modern-table table-striped" data-no-datatable="true">
                <thead>
                <tr class="no-child">
                    <th>Name</th>
                    <th>Department</th>
                    <th>Person Code</th>
                    <th>Date</th>
                    <th>Expected In</th>
                    <th>Check In</th>
                    <th>Expected Out</th>
                    <th>Check Out</th>
                    <th>Late (min)</th>
                    <th>Overtime (min)</th>
                    <th>Location</th>
                    <th>Data Source</th>
                    <th>Device</th>
                </tr>
                </thead>
                <tbody>
                @forelse($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->name }}</td>
                        <td>{{ $transaction->department }}</td>
                        <td>{{ $transaction->person_code }}</td>
                        <td>{{ $transaction->date?->format('Y-m-d') }}</td>
                        <td>
                            {{ $transaction->expected_in ? \Carbon\Carbon::parse($transaction->expected_in)->format('H:i') : '—' }}
                        </td>
                        <td>
                            {{ $transaction->check_in ? \Carbon\Carbon::parse($transaction->check_in)->format('H:i') : '—' }}
                        </td>
                        <td>
                            {{ $transaction->expected_out ? \Carbon\Carbon::parse($transaction->expected_out)->format('H:i') : '—' }}
                        </td>
                        <td>
                            {{ $transaction->check_out ? \Carbon\Carbon::parse($transaction->check_out)->format('H:i') : '—' }}
                        </td>
                        <td>{{ $transaction->late_minutes }}</td>
                        <td>{{ $transaction->overtime_minutes }}</td>
                        <td>
                            @if($transaction->latitude && $transaction->longitude)
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary transaction-location"
                                        data-lat="{{ $transaction->latitude }}"
                                        data-lng="{{ $transaction->longitude }}"
                                        data-location="{{ $transaction->location }}"
                                        data-name="{{ $transaction->name }}"
                                        data-mode="coords">
                                    <i class="bi bi-geo-alt-fill me-1"></i>
                                    View Map
                                </button>
                                @if($transaction->location)
                                    <div class="text-muted location-label">
                                        {{ \Illuminate\Support\Str::limit($transaction->location, 40) }}
                                    </div>
                                @endif
                            @elseif($transaction->location)
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary transaction-location"
                                        data-location="{{ $transaction->location }}"
                                        data-name="{{ $transaction->name }}"
                                        data-mode="address">
                                    <i class="bi bi-geo-alt me-1"></i>
                                    {{ \Illuminate\Support\Str::limit($transaction->location, 30) }}
                                </button>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $transaction->data_source }}</td>
                        <td>
                            @if($transaction->device_name)
                                <div>{{ $transaction->device_name }}</div>
                            @endif
                            @if($transaction->device_serial)
                                <div class="text-muted" style="font-size: 0.75rem;">{{ $transaction->device_serial }}</div>
                            @endif
                            @if($transaction->device_id)
                                <div class="text-muted" style="font-size: 0.75rem;">ID: {{ $transaction->device_id }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="text-center text-muted py-5">
                            <div class="mb-2"><i class="bi bi-clipboard-data" style="font-size: 2rem;"></i></div>
                            <div class="fw-semibold">No transactions found.</div>
                            <div class="mini">Adjust filters or run the sync command to populate data.</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="modern-card-footer d-flex flex-column flex-sm-row justify-content-between align-items-center" style="gap: 1rem;">
            <div style="font-size: 0.875rem; color: var(--gray-600); font-weight: 500;">
                Showing {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} of {{ $transactions->total() }}
            </div>
            <div>
                {{ $transactions->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@section('script-bottom')
<script>
$(document).ready(function() {
    // Prevent RWD-Table and DataTables from interfering with transactions table
    $('.transactions-wrapper .table-responsive').removeAttr('data-pattern');
    
    // Ensure overflow is maintained
    $('.transactions-wrapper .table-responsive').css({
        'overflow-x': 'auto',
        'overflow-y': 'visible',
        '-webkit-overflow-scrolling': 'touch'
    });
    
    // Remove any DataTable initialization if it happened
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('.transactions-wrapper .modern-table')) {
        $('.transactions-wrapper .modern-table').DataTable().destroy();
    }
});

const buildMapMarkup = ({ title, subtitle, embedUrl }) => `
    <div class="map-modal">
        <div class="map-modal__header">
            <div class="map-modal__title">${title}</div>
            <div class="map-modal__subtitle">${subtitle}</div>
        </div>
        <div class="map-preview">
            <iframe src="${embedUrl}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </div>
`;

const openMapModal = ({ title, subtitle, embedUrl, mapsUrl }) => {
    const mapMarkup = buildMapMarkup({ title, subtitle, embedUrl });

    if (window.Swal && typeof window.Swal.fire === 'function') {
        Swal.fire({
            html: mapMarkup,
            showCancelButton: true,
            confirmButtonText: 'Open in Google Maps',
            cancelButtonText: 'Close',
            buttonsStyling: false,
            customClass: {
                popup: 'swal-map-modal',
                confirmButton: 'btn btn-modern btn-modern-success',
                cancelButton: 'btn btn-modern'
            },
            width: 640,
            focusConfirm: false,
            showCloseButton: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.open(mapsUrl, '_blank');
            }
        });
        return;
    }

    if (typeof window.swal === 'function') {
        const container = document.createElement('div');
        container.innerHTML = mapMarkup;

        swal({
            content: container,
            buttons: {
                cancel: 'Close',
                confirm: {
                    text: 'Open in Google Maps',
                    value: 'open',
                    closeModal: true
                }
            }
        }).then((value) => {
            if (value === 'open') {
                window.open(mapsUrl, '_blank');
            }
        });
        return;
    }

    window.open(mapsUrl, '_blank');
};

$(document).on('click', '.transaction-location', function () {
    const mode = $(this).data('mode');
    const lat = $(this).data('lat');
    const lng = $(this).data('lng');
    const address = $(this).data('location') || 'Location';
    const name = $(this).data('name') || 'Employee';

    let embedUrl = '';
    let mapsUrl = '';
    let title = `${name}`;
    let subtitle = address;

    if (mode === 'coords' && lat && lng) {
        embedUrl = `https://www.google.com/maps?q=${lat},${lng}&hl=en&z=18&output=embed`;
        mapsUrl = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
        subtitle = address ? `${address}` : 'Captured via coordinates';
    } else {
        const encoded = encodeURIComponent(address);
        embedUrl = `https://www.google.com/maps?q=${encoded}&hl=en&z=18&output=embed`;
        mapsUrl = `https://www.google.com/maps/search/?api=1&query=${encoded}`;
    }

    openMapModal({
        title,
        subtitle,
        embedUrl,
        mapsUrl
    });
});
</script>
@endsection
