<!-- Modern Header -->
<header class="modern-header">
    <div class="d-flex justify-content-between align-items-center">
        <!-- Left: Menu Toggle & Breadcrumb -->
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-link p-0" onclick="toggleSidebar()" style="color: var(--gray-700);">
                <i class="bi bi-list" style="font-size: 1.5rem;"></i>
            </button>
            <div class="breadcrumb-modern">
                @yield('breadcrumb')
            </div>
        </div>

        <!-- Right: User Menu & Actions -->
        <div class="user-menu">
            <!-- Notifications (optional) -->
            <div class="dropdown">
                <button class="btn btn-link position-relative p-2" type="button" data-bs-toggle="dropdown" style="color: var(--gray-700);">
                    <i class="bi bi-bell" style="font-size: 1.25rem;"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                        3
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end modern-dropdown">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <li><a class="dropdown-item" href="#">New attendance record</a></li>
                    <li><a class="dropdown-item" href="#">Sync completed</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-center" href="#">View all</a></li>
                </ul>
            </div>

            <!-- User Profile -->
            <div class="dropdown">
                <button class="btn btn-link d-flex align-items-center gap-2 p-0" type="button" data-bs-toggle="dropdown" style="text-decoration: none;">
                    <img src="{{ asset('assets/images/profile1.jpg') }}" alt="User" class="user-avatar modern-header">
                    <span style="color: var(--gray-700); font-weight: 500;">User</span>
                    <i class="bi bi-chevron-down" style="font-size: 0.75rem; color: var(--gray-500);"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end modern-dropdown">
                    <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                    </li>
                </ul>
            </div>
        </div>
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

