      <!-- ========== Left Sidebar Start ========== -->
            <div class="left side-menu">
                <div class="slimscroll-menu" id="remove-scroll">

                    <!--- Sidemenu -->
                    <div id="sidebar-menu">

                        <!-- Left Menu Start -->
                        <ul class="metismenu" id="side-menu">
                            <li class="menu-title">Main</li>
                            <li class="">
                                <a href="{{ route('admin') }}" class="waves-effect {{ request()->is('admin') || request()->is('admin/*') ? 'mm active' : '' }}">
                                    <i class="ti-home"></i><span> Dashboard </span>
                                </a>
                            </li>


                            <li>
                                <a href="javascript:void(0);" class="waves-effect"><i class="ti-user"></i><span> Employees <span class="float-right menu-arrow"><i class="mdi mdi-chevron-right"></i></span> </span></a>
                                <ul class="submenu">
                                    <li>
                                        <a href="{{ route('acs.people.index') }}" class="waves-effect {{ request()->is('admin/daily-people') || request()->is('admin/daily-people/*') ? 'mm active' : '' }}"><i class="dripicons-view-apps"></i><span>Employees List</span></a>
                                    </li>

                                </ul>
                            </li>

                            <li class="menu-title">Management</li>

                            <li class="">
                                <a href="{{ route('admin.attendances.index') }}" class="waves-effect {{ request()->is('admin/attendances') || request()->is('admin/attendances/*') ? 'mm active' : '' }}">
                                    <i class="dripicons-to-do"></i> <span> Attendance Sheet </span>
                                </a>
                            </li>
                            <li class="">
                                <a href="{{ route('acs.daily.index') }}" class="waves-effect {{ request()->is('admin/acs/daily') || request()->is('admin/acs/daily/*') ? 'mm active' : '' }}">
                                    <i class="dripicons-to-do"></i> <span>Daily Attendance</span>
                                </a>
                            </li>
                            <li class="">
                                <a href="{{ url('schedule') }}" class="waves-effect {{ request()->is('schedule') || request()->is('schedule/*') ? 'mm active' : '' }}">
                                    <i class="ti-time"></i> <span> Schedule </span>
                                </a>
                            </li>
                            <li class="">
                                <a href="{{ route('sheet-report') }}" class="waves-effect {{ request()->is('sheet-report') || request()->is('sheet-report/*') ? 'mm active' : '' }}">
                                    <i class="dripicons-to-do"></i> <span> Sheet Report </span>
                                </a>
                            </li>

                            <li class="">
                                <a href="{{ route('attendance') }}" class="waves-effect {{ request()->is('attendance') || request()->is('attendance/*') ? 'mm active' : '' }}">
                                    <i class="ti-calendar"></i> <span> Attendance Logs </span>
                                </a>
                            </li>
                            <li class="">
                                <a href="{{ route('indexLatetime') }}" class="waves-effect {{ request()->is('latetime') || request()->is('latetime/*') ? 'mm active' : '' }}">
                                    <i class="dripicons-warning"></i><span> Late Time </span>
                                </a>
                            </li>
                            <li class="">
                                <a href="{{ route('leave') }}" class="waves-effect {{ request()->is('leave') || request()->is('leave/*') ? 'mm active' : '' }}">
                                    <i class="dripicons-backspace"></i> <span> Leave </span>
                                </a>
                            </li>
                            <li class="">
                                <a href="{{ route('indexOvertime') }}" class="waves-effect {{ request()->is('overtime') || request()->is('overtime/*') ? 'mm active' : '' }}">
                                    <i class="dripicons-alarm"></i> <span> Over Time </span>
                                </a>
                            </li>
                            <li class="menu-title">Tools</li>
                            <li class="">
                                <a href="{{ route('finger_device.index') }}" class="waves-effect {{ request()->is('finger_device') || request()->is('finger_device/*') ? 'mm active' : '' }}">
                                    <i class="fas fa-fingerprint"></i> <span> Biometric Device </span>
                                </a>
                            </li>

                            <li class="menu-title">HikCentral Connect</li>
                            <li>
                                <a href="javascript:void(0);" class="waves-effect"><i class="ti-cloud"></i><span> HCC Attendance <span class="float-right menu-arrow"><i class="mdi mdi-chevron-right"></i></span> </span></a>
                                <ul class="submenu">
                                    <li>
                                        <a href="{{ route('admin.hcc.attendance.index') }}" class="waves-effect {{ request()->is('admin/hcc/attendance') || request()->is('admin/hcc/attendance/*') ? 'mm active' : '' }}">
                                            <i class="dripicons-to-do"></i><span> Attendance Records</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('admin.hcc.devices.index') }}" class="waves-effect {{ request()->is('admin/hcc/devices') || request()->is('admin/hcc/devices/*') ? 'mm active' : '' }}">
                                            <i class="dripicons-device-desktop"></i><span> Devices</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('admin.hcc.backfill.form') }}" class="waves-effect {{ request()->is('admin/hcc/backfill') ? 'mm active' : '' }}">
                                            <i class="dripicons-calendar"></i><span> Backfill Data</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>

                        </ul>

                    </div>
                    <!-- Sidebar -->
                    <div class="clearfix"></div>

                </div>
                <!-- Sidebar -left -->

            </div>
            <!-- Left Sidebar End -->
