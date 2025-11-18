<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
        <title>Attendance Management System</title>
        <meta content="Admin Dashboard" name="description" />
        <meta content="Themesbrand" name="author" />
        @include('layouts.head')
    </head>
<body style="background: #f9fafb;">
    <div id="wrapper" style="display: flex;">
         <!-- Sidebar Overlay for Mobile -->
         <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar(false)"></div>
         
         @include('layouts.modern-sidebar')
         
         <div style="flex: 1; margin-left: 260px; min-height: 100vh; transition: var(--transition); overflow: auto !important;" id="mainContent">
            @include('layouts.modern-header')
            <main class="page-content" style="padding: 2rem; min-height: calc(100vh - 80px);">
               @include('layouts.settings')
               @yield('content')
            </main>
        </div> 
        @include('layouts.footer')  
        @include('layouts.footer-script')  
    </div> 
    @include('includes.flash')
    
    <script>
    function toggleSidebar(force) {
        const sidebar = document.getElementById('modern-sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');
        const isMobile = window.innerWidth <= 991;

        if (!sidebar || !overlay || !mainContent) {
            return;
        }

        if (isMobile) {
            const shouldOpen = typeof force === 'boolean'
                ? force
                : !sidebar.classList.contains('active');

            sidebar.classList.toggle('active', shouldOpen);
            overlay.classList.toggle('active', shouldOpen);
            document.body.classList.toggle('sidebar-open', shouldOpen);

            if (!shouldOpen) {
                mainContent.style.marginLeft = '0';
            }
        } else {
            // Ensure desktop layout keeps margin
            mainContent.style.marginLeft = '260px';
        }
    }

    function applyResponsiveLayout() {
        const sidebar = document.getElementById('modern-sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');

        if (!sidebar || !overlay || !mainContent) {
            return;
        }

        if (window.innerWidth <= 991) {
            sidebar.classList.remove('collapsed');
            mainContent.style.marginLeft = '0';

            if (!sidebar.classList.contains('active')) {
                overlay.classList.remove('active');
                document.body.classList.remove('sidebar-open');
            }
        } else {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.classList.remove('sidebar-open');
            mainContent.style.marginLeft = '260px';
        }
    }

    document.addEventListener('DOMContentLoaded', applyResponsiveLayout);
    window.addEventListener('resize', applyResponsiveLayout);
    </script>
    </body>
</html>