<!-- Modern Sidebar -->
<div class="modern-sidebar" id="modern-sidebar">
    <!-- Logo -->
    <div class="logo">
        <div class="logo-icon">
            <i class="bi bi-clock-history" style="font-size: 2rem; color: #6366f1;"></i>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">AMS</h1>
            <p style="margin: 0; font-size: 0.75rem; color: rgba(255,255,255,0.6);">Attendance System</p>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav" style="padding: 1rem 0;">
        <!-- Main Section -->
        <div class="menu-section" style="margin-bottom: 2rem;">
            <div class="menu-title" style="padding: 0.5rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,0.5); font-weight: 600;">Main</div>
            <a href="{{ route('admin') }}" class="menu-item {{ request()->is('admin') && !request()->is('admin/*') ? 'active' : '' }}">
                <i class="bi bi-house-door"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <!-- Employees Section -->
        <div class="menu-section" style="margin-bottom: 2rem;">
            <div class="menu-title" style="padding: 0.5rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,0.5); font-weight: 600;">Employees</div>
            <a href="{{ route('acs.people.index') }}" class="menu-item {{ request()->is('admin/daily-people') || request()->is('admin/daily-people/*') ? 'active' : '' }}">
                <i class="bi bi-people"></i>
                <span>Employees List</span>
            </a>
        </div>

        <!-- Management Section -->
        <div class="menu-section" style="margin-bottom: 2rem;">
            <div class="menu-title" style="padding: 0.5rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,0.5); font-weight: 600;">Management</div>
            <a href="{{ route('admin.transactions.index') }}" class="menu-item {{ request()->is('admin/transactions') ? 'active' : '' }}">
                <i class="bi bi-card-checklist"></i>
                <span>Transactions</span>
            </a>
        </div>

        <!-- Tools Section -->
        <div class="menu-section" style="margin-bottom: 2rem;">
            <div class="menu-title" style="padding: 0.5rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,0.5); font-weight: 600;">Tools</div>
            <a href="{{ route('admin.token.tools') }}" class="menu-item {{ request()->is('admin/token-tools') ? 'active' : '' }}">
                <i class="bi bi-key"></i>
                <span>Token Tools</span>
            </a>
        </div>

        <!-- HikCentral Connect Section -->
        <div class="menu-section">
            <div class="menu-title" style="padding: 0.5rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,0.5); font-weight: 600;">HikCentral Connect</div>
            <a href="javascript:void(0);" class="menu-item" onclick="toggleSubmenu(this)">
                <i class="bi bi-cloud"></i>
                <span>HCC Attendance</span>
                <i class="bi bi-chevron-right ms-auto" style="font-size: 0.875rem;"></i>
            </a>
            <div class="submenu" style="display: none; padding-left: 2rem;">
                @if(Route::has('admin.hcc.attendance.index'))
                <a href="{{ route('admin.hcc.attendance.index') }}" class="menu-item {{ request()->is('admin/hcc/attendance') || request()->is('admin/hcc/attendance/*') ? 'active' : '' }}" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                    <i class="bi bi-list-ul"></i>
                    <span>Attendance Records</span>
                </a>
                @endif
            </div>
        </div>
    </nav>
</div>

<script>
function toggleSubmenu(element) {
    const submenu = element.nextElementSibling;
    const icon = element.querySelector('.bi-chevron-right');
    
    if (submenu.style.display === 'none') {
        submenu.style.display = 'block';
        icon.style.transform = 'rotate(90deg)';
    } else {
        submenu.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}
</script>

<style>
.menu-item {
    transition: transform 0.2s ease;
}

.menu-item i.bi-chevron-right {
    transition: transform 0.3s ease;
    margin-left: auto;
}
</style>

