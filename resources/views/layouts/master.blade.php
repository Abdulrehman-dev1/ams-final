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
         <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
         
         @include('layouts.modern-sidebar')
         
         <div style="flex: 1; margin-left: 260px; min-height: 100vh; transition: var(--transition);" id="mainContent">
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
    function toggleSidebar() {
        const sidebar = document.getElementById('modern-sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');
        
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        
        if (window.innerWidth <= 768) {
            if (sidebar.classList.contains('active')) {
                mainContent.style.marginLeft = '0';
            } else {
                mainContent.style.marginLeft = '0';
            }
        }
    }
    
    // Close sidebar when clicking outside on mobile
    document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            toggleSidebar();
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('modern-sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');
        
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            mainContent.style.marginLeft = '260px';
        } else {
            if (!sidebar.classList.contains('active')) {
                mainContent.style.marginLeft = '0';
            }
        }
    });
    </script>
    </body>
</html>