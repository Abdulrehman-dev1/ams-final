@extends('layouts.master')

@section('css')
<style>
    .equal-widget {
        display: flex;
        flex-direction: column;
        height: 100%;
        padding: 1rem;
        margin-top: 1rem;
        border-radius: 22px;
    }

    .avatar-wrapper {
        width: 44px;
        height: 44px;
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .avatar-wrapper img {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        display: block;
    }

    .letter-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #eef2ff;
        color: #4c1d95;
        font-weight: 600;
        display: none;
        align-items: center;
        justify-content: center;
        text-transform: uppercase;
    }

    .avatar-wrapper.avatar-missing .letter-avatar {
        display: inline-flex;
    }

    .employee-detail {
        display: flex;
        flex-direction: column;
        padding: 0.5rem;
        text-align: left;
    }

    .employee-detail small {
        display: block;
    }

    .swal-button.swal-button--confirm.btn.btn-primary {
        background-color: #000;
        border-color: #000;
        min-width: 120px;
        padding: 0.5rem 1.5rem;
        font-size: 1rem;
    }
</style>
@endsection

@section('breadcrumb')
<div class="d-flex align-items-center justify-content-between w-100">
    <div>
        <h2 class="mb-1" style="font-weight: 700; color: var(--gray-900);">
            <i class="bi bi-speedometer2 me-2" style="color: var(--primary);"></i>
            Dashboard
        </h2>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Attendance snapshot generated from today&rsquo;s transactions</p>
    </div>
    <span class="modern-badge modern-badge-success" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
        <i class="bi bi-calendar-day me-1"></i> {{ $today }}
    </span>
</div>
@endsection

@section('content')
@php
    $placeholderAvatar = asset('assets/images/users/user-1.jpg');
    $cards = [
        [
            'key'   => 'totalEmployees',
            'label' => 'Total Employees',
            'value' => number_format($metrics['totalEmployees']),
            'icon'  => 'bi-people-fill',
            'theme' => 'primary',
            'modal' => 'detailModal-totalEmployees',
        ],
        [
            'key'   => 'onTimePercentage',
            'label' => 'On Time Percentage',
            'value' => number_format($metrics['onTimePercentage'], 1) . '%',
            'icon'  => 'bi-graph-up-arrow',
            'theme' => 'success',
            'modal' => 'detailModal-onTimePercentage',
        ],
        [
            'key'   => 'onTimeToday',
            'label' => 'On Time Today',
            'value' => number_format($metrics['onTimeToday']),
            'icon'  => 'bi-check-circle-fill',
            'theme' => 'success',
            'modal' => 'detailModal-onTimeToday',
        ],
        [
            'key'   => 'lateToday',
            'label' => 'Late Today',
            'value' => number_format($metrics['lateToday']),
            'icon'  => 'bi-exclamation-triangle-fill',
            'theme' => 'warning',
            'modal' => 'detailModal-lateToday',
        ],
        [
            'key'   => 'mobileCheckinsToday',
            'label' => 'Mobile Check-in Today',
            'value' => number_format($metrics['mobileCheckinsToday']),
            'icon'  => 'bi-phone-fill',
            'theme' => 'info',
            'modal' => 'detailModal-mobileCheckinsToday',
        ],
        [
            'key'   => 'deviceCheckinsToday',
            'label' => 'Device Check-in Today',
            'value' => number_format($metrics['deviceCheckinsToday']),
            'icon'  => 'bi-device-hdd-fill',
            'theme' => 'primary',
            'modal' => 'detailModal-deviceCheckinsToday',
        ],
        [
            'key'   => 'overtimeToday',
            'label' => 'Overtime Count',
            'value' => number_format($metrics['overtimeToday']),
            'icon'  => 'bi-alarm',
            'theme' => 'success',
            'modal' => 'detailModal-overtimeToday',
        ],
        [
            'key'   => 'absentToday',
            'label' => 'Absent Today',
            'value' => number_format($metrics['absentToday']),
            'icon'  => 'bi-person-x-fill',
            'theme' => 'danger',
            'modal' => 'absentModal',
        ],
    ];
    $cardRows = array_chunk($cards, 4);
@endphp

<div class="container-fluid">
    @foreach($cardRows as $rowIndex => $rowCards)
        <div class="row g-2 {{ $rowIndex > 0 ? 'mt-2 mt-lg-3' : '' }}">
            @foreach($rowCards as $colIndex => $card)
                <div class="col-xxl-3 col-xl-3 col-lg-4 col-md-6">
                    @php
                        $detailKey = $card['key'] ?? null;
                        $modalId = $card['modal'] ?? ($detailKey ? 'detailModal-' . $detailKey : null);
                        $globalIndex = ($rowIndex * 4) + $colIndex;
                    @endphp
                    <div class="modern-widget slide-up equal-widget mt-3" style="animation-delay: {{ $globalIndex * 0.05 }}s ; height: 250px;">
                        <div class="modern-widget-icon modern-widget-icon-{{ $card['theme'] }}">
                            <i class="bi {{ $card['icon'] }}"></i>
                        </div>
                        <div class="modern-widget-label">{{ $card['label'] }}</div>
                        <div class="modern-widget-value">{{ $card['value'] }}</div>
                        @if($modalId)
                            <div class="metric-footer d-flex justify-content-end ">
                                <button type="button"
                                        class="btn btn-sm btn-outline-light text-{{ $card['theme'] }} metric-modal-trigger"
                                        data-metric-key="{{ $detailKey }}"
                                        data-metric-label="{{ $card['label'] }}"
                                        data-metric-theme="{{ $card['theme'] }}">
                                    More info
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

@endsection

@section('script-bottom')
<script>
    window.handleAvatarError = window.handleAvatarError || function (img) {
        try {
            var wrapper = img.closest('.avatar-wrapper');
            if (wrapper) {
                wrapper.classList.add('avatar-missing');
            }
            img.remove();
        } catch (e) {
            img.remove();
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var detailData = @json($detailLists);
        var fallbackAvatar = @json($placeholderAvatar);
        var todayLabel = @json($today);
        var maxRecords = 100;
        var equalWidgets = document.querySelectorAll('.equal-widget');

        var minCardHeight = 190;
        var maxCardHeight = 230;

        function applyEqualHeights(elements) {
            var measuredHeight = 0;
            elements.forEach(function (el) {
                el.style.minHeight = 'auto';
                measuredHeight = Math.max(measuredHeight, el.getBoundingClientRect().height);
            });
            var targetHeight = Math.max(minCardHeight, Math.min(measuredHeight, maxCardHeight));
            elements.forEach(function (el) {
                el.style.minHeight = targetHeight + 'px';
            });
        }

        if (equalWidgets.length) {
            applyEqualHeights(equalWidgets);
            window.addEventListener('resize', function () {
                applyEqualHeights(equalWidgets);
            });
        }

        function buildContent(records) {
            var wrapper = document.createElement('div');
            wrapper.className = 'swal-metric-content';

            if (!records || !records.length) {
                wrapper.innerHTML = '<div class="text-center text-muted py-3 mb-0">No records available.</div>';
                return wrapper;
            }

            var limited = records.slice(0, maxRecords);
            var notice = records.length > maxRecords
                ? '<p class="text-muted mb-2"><small>Showing first ' + maxRecords + ' records.</small></p>'
                : '';

            var rows = limited.map(function (record) {
                var safeName = record.name || '—';
                var safeCode = record.person_code || '—';
                var letter = (record.initial || safeName.charAt(0) || '—').toString().toUpperCase();
                var hasPhoto = record.photo && record.photo !== '';
                var wrapperClass = hasPhoto ? 'avatar-wrapper' : 'avatar-wrapper avatar-missing';
                var avatar = '<div class="' + wrapperClass + '">';
                if (hasPhoto) {
                    avatar += '<img src="' + record.photo + '" alt="' + safeName + '" ' +
                        'onerror="window.handleAvatarError && handleAvatarError(this);">';
                }
                avatar += '<div class="letter-avatar">' + letter + '</div></div>';

                return (
                    '<tr>' +
                        '<td>' +
                            '<div class="d-flex align-items-center gap-3">' +
                                avatar +
                                '<div class="employee-detail">' +
                                    '<span class="fw-500">' + safeName + '</span>' +
                                    '<small class="text-muted">' + safeCode + '</small>' +
                                '</div>' +
                            '</div>' +
                        '</td>' +
                    '</tr>'
                );
            }).join('');

            wrapper.innerHTML = notice +
                '<div class="table-responsive">' +
                    '<table class="table table-sm table-striped align-middle mb-0">' +
                        '<thead>' +
                            '<tr>' +
                                '<th>Employee</th>' +
                            '</tr>' +
                        '</thead>' +
                        '<tbody>' + rows + '</tbody>' +
                    '</table>' +
                '</div>';

            return wrapper;
        }

        var triggers = document.querySelectorAll('.metric-modal-trigger');
        triggers.forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                var key = trigger.getAttribute('data-metric-key');
                if (!key) {
                    return;
                }
                var label = trigger.getAttribute('data-metric-label') || 'Details';
                var records = detailData[key] || [];
                var contentNode = buildContent(records);

                if (typeof swal === 'function') {
                    swal({
                        title: label + ' — ' + todayLabel,
                        content: contentNode,
                        buttons: {
                            confirm: {
                                text: 'Close',
                                className: 'btn btn-primary'
                            }
                        }
                    });
                } else {
                    console.warn('SweetAlert library not available');
                }
            });
        });
    });
</script>
@endsection