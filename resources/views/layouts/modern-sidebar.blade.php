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
            <a href="{{ route('admin.attendances.index') }}" class="menu-item {{ request()->is('admin/attendances') || request()->is('admin/attendances/*') ? 'active' : '' }}">
                <i class="bi bi-calendar-check"></i>
                <span>Attendance Sheet</span>
            </a>
            <a href="{{ route('acs.daily.index') }}" class="menu-item {{ request()->is('admin/acs/daily') || request()->is('admin/acs/daily/*') ? 'active' : '' }}">
                <i class="bi bi-calendar-day"></i>
                <span>Daily Attendance</span>
            </a>
            <a href="{{ url('schedule') }}" class="menu-item {{ request()->is('schedule') || request()->is('schedule/*') ? 'active' : '' }}">
                <i class="bi bi-clock"></i>
                <span>Schedule</span>
            </a>
            <a href="{{ route('sheet-report') }}" class="menu-item {{ request()->is('sheet-report') || request()->is('sheet-report/*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-text"></i>
                <span>Sheet Report</span>
            </a>
            <a href="{{ route('attendance') }}" class="menu-item {{ request()->is('attendance') || request()->is('attendance/*') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i>
                <span>Attendance Logs</span>
            </a>
            <a href="{{ route('indexLatetime') }}" class="menu-item {{ request()->is('latetime') || request()->is('latetime/*') ? 'active' : '' }}">
                <i class="bi bi-exclamation-triangle"></i>
                <span>Late Time</span>
            </a>
            <a href="{{ route('leave') }}" class="menu-item {{ request()->is('leave') || request()->is('leave/*') ? 'active' : '' }}">
                <i class="bi bi-door-open"></i>
                <span>Leave</span>
            </a>
            <a href="{{ route('indexOvertime') }}" class="menu-item {{ request()->is('overtime') || request()->is('overtime/*') ? 'active' : '' }}">
                <i class="bi bi-alarm"></i>
                <span>Over Time</span>
            </a>
            <a href="{{ route('admin.reports.index') }}" class="menu-item {{ request()->is('admin/reports') ? 'active' : '' }}">
                <i class="bi bi-graph-up"></i>
                <span>Reports</span>
            </a>
        </div>

        <!-- Tools Section -->
        <div class="menu-section" style="margin-bottom: 2rem;">
            <div class="menu-title" style="padding: 0.5rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,0.5); font-weight: 600;">Tools</div>
            <a href="{{ route('finger_device.index') }}" class="menu-item {{ request()->is('finger_device') || request()->is('finger_device/*') ? 'active' : '' }}">
                <i class="bi bi-fingerprint"></i>
                <span>Biometric Device</span>
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
                @if(Route::has('admin.hcc.devices.index'))
                <a href="{{ route('admin.hcc.devices.index') }}" class="menu-item {{ request()->is('admin/hcc/devices') || request()->is('admin/hcc/devices/*') ? 'active' : '' }}" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                    <i class="bi bi-device-hdd"></i>
                    <span>Devices</span>
                </a>
                @endif
                @if(Route::has('admin.hcc.backfill.form'))
                <a href="{{ route('admin.hcc.backfill.form') }}" class="menu-item {{ request()->is('admin/hcc/backfill') ? 'active' : '' }}" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                    <i class="bi bi-calendar-range"></i>
                    <span>Backfill Data</span>
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

// Mobile menu toggle
function toggleSidebar() {
    const sidebar = document.getElementById('modern-sidebar');
    sidebar.classList.toggle('active');
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

