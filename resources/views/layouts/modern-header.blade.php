<!-- Modern Header -->
<header class="modern-header">
    <div class="d-flex justify-content-between align-items-center">
        <!-- Left: Menu Toggle & Breadcrumb -->
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-link p-0" onclick="toggleSidebar()" style="color: var(--gray-700); display: none;" id="mobileMenuToggle">
                <i class="bi bi-list" style="font-size: 1.5rem;"></i>
            </button>
            <div class="breadcrumb-modern" style="display: none !important;">
                @yield('breadcrumb')
            </div>
        </div>

        <!-- Right: placeholder (removed user menu) -->
        <div></div>
    </div>
</header>

<style>
.modern-dropdown {
    border: none;
    box-shadow: var(--shadow-lg);
    border-radius: var(--radius);
    padding: 0.5rem;
    min-width: 250px;
}

.modern-dropdown .dropdown-item {
    padding: 0.75rem 1rem;
    border-radius: var(--radius-sm);
    transition: var(--transition);
}

.modern-dropdown .dropdown-item:hover {
    background: var(--gray-100);
}

.user-avatar.modern-header {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid var(--primary);
    object-fit: cover;
}
</style>

