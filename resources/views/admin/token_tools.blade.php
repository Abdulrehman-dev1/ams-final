@extends('layouts.master')

@section('content')
<style>
    .token-card {
        border-radius: 24px;
        box-shadow: 0 15px 35px rgba(15, 23, 42, 0.08);
        border: none;
    }

    .token-terminal {
        background: #0f172a;
        color: #e2e8f0;
        font-family: "Fira Code", Consolas, Monaco, monospace;
        font-size: 0.9rem;
        border-radius: 16px;
        padding: 1rem;
        min-height: 240px;
        white-space: pre-wrap;
        overflow-y: auto;
    }

    .token-actions .btn-modern {
        min-width: 180px;
    }
</style>

<div class="container-fluid">
    <div class="modern-card token-card mb-4">
        <div class="modern-card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h2 class="mb-1" style="font-weight:700;color:var(--gray-900);">
                    <i class="bi bi-shield-lock me-2" style="color:var(--primary);"></i>
                    Hikvision Token Tools
                </h2>
                <p class="mb-0 text-muted" style="font-size:0.9rem;">Run `php artisan hik:test-token fetch` directly from the dashboard.</p>
            </div>
            <div class="token-actions d-flex flex-wrap gap-2">
                <form action="{{ route('admin.token.fetch') }}" method="POST"
                      class="d-inline js-submit-loading"
                      data-loading-text='<span class="modern-loader"></span> Running...'>
                    @csrf
                    <button type="submit" class="btn-modern btn-modern-dark">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        Fetch Token
                    </button>
                </form>
            </div>
        </div>
        <div class="modern-card-body">
            @if(session('token_output'))
                <pre class="token-terminal">{{ session('token_output') }}</pre>
            @else
                <div class="token-terminal d-flex align-items-center justify-content-center text-muted">
                    Command output will appear here.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('script-bottom')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-submit-loading').forEach(function (form) {
            form.addEventListener('submit', function () {
                const btn = form.querySelector('button[type="submit"]');
                if (!btn) {
                    return;
                }
                btn.disabled = true;
                btn.innerHTML = form.getAttribute('data-loading-text') || 'Running...';
            });
        });
    });
</script>
@endsection

