# 🚀 AMS Final - Quick Reference Guide

## 🎯 Project Overview
**Attendance Management System** with ZKTeco & Hikvision integrations

## 🔧 Tech Stack
- **Laravel 8.65** | **PHP 7.3/8.0+** | **MySQL**
- **Bootstrap** | **jQuery** | **DataTables**
- **Python Playwright** (Browser Automation - **ACTIVE**)
- **Laravel Dusk** (Deprecated - not used)

## 📁 Key Directories
```
app/Console/Commands/    # 23+ Artisan commands
app/Http/Controllers/    # 24 controllers
app/Models/              # 18 models
app/Services/            # 4 services (HCC)
database/migrations/     # 22 migrations
```

## 🔌 Integrations
1. **ZKTeco** - Biometric devices (IP connection)
2. **Hikvision HCC** - REST API + Browser scraping
3. **Python Playwright** - Browser automation (ACTIVE)
4. **Laravel Dusk** - Deprecated (not used)

## ⚙️ Scheduled Tasks
- **HCC Sync** - Every 5 minutes (Python Playwright - `hcc:sync`) ✅ UPDATED
- **Device Sync** - Daily 3:05 AM (API-based - `hcc:sync:devices`)
- **HIK Token Refresh** - Every 3 hours
- **Employee Sync** - Every 6 hours

## 🚨 Critical Issues

### ✅ FIXED
1. **Timezone Mismatch** - ✅ FIXED (changed to `Asia/Karachi`)
2. **Duplicate Route** - ✅ FIXED (removed duplicate)
3. **Vite Error** - ✅ FIXED (manifest.json created)
4. **Missing .env.example** - ✅ FIXED (recreated with all variables)
5. **Scheduled Tasks** - ✅ FIXED (updated to use `hcc:sync` in Kernel.php)

### ⚠️ SHOULD FIX
6. **API Authentication** - Add to unprotected routes
7. **Mark Legacy Commands** - Document which commands are deprecated

## 📊 Key Models
- `Employee` - Employee master
- `Attendance` - Daily attendance
- `Schedule` - Work schedules
- `Leave` - Leave requests
- `HccAttendanceTransaction` - HCC logs
- `DailyAttendance` - Rollup data

## 🔑 Key Commands
```bash
# HCC Sync (PRIMARY - Python Playwright) ✅
php artisan hcc:sync

# Sync Devices
php artisan hcc:sync-devices

# Legacy Commands (deprecated)
php artisan hcc:capture --from=2025-10-01 --to=2025-10-31
php artisan hcc:fetch-attendance
php artisan hcc:ingest:range --from=2025-10-01 --to=2025-10-31
```

## 🌐 Key Routes
```
/admin              # Dashboard
/employees          # Employee management
/attendance         # Attendance
/schedule           # Schedules
/leave              # Leave management
/admin/acs/daily    # ACS daily view
/admin/attendance/rollup  # Rollup
```

## 📝 Configuration Files
- `config/app.php` - App settings
- `config/hik.php` - Hikvision API
- `config/hcc.php` - HCC settings
- `config/attendance.php` - Attendance rules

## 🔐 Environment Variables
```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=ams
DB_USERNAME=root
DB_PASSWORD=

# HCC
HCC_BASE_URL=https://isgp-team.hikcentralconnect.com
HCC_BEARER_TOKEN=your_token
HCC_COOKIE=your_cookie

# HIK
HIK_BASE_URL=https://isgp.hikcentralconnect.com/api/hccgw
HIK_TOKEN=your_token

# Attendance
ATTENDANCE_ON_TIME_CUTOFF=09:30:00
ATTENDANCE_ABSENT_CUTOFF=10:00:00
ATTENDANCE_TIMEZONE=Asia/Karachi
```

## 📚 Documentation
- `PROJECT_ANALYSIS.md` - Full analysis
- `README.md` - Main documentation
- `HCC_ATTENDANCE_CAPTURE_GUIDE.md` - HCC guide
- `IMPLEMENTATION_STATUS.md` - Implementation status

## 🐛 Common Issues

### Vite Error
**Solution:** ✅ Fixed - manifest.json created

### Timezone Issues
**Solution:** ✅ FIXED - Changed to `Asia/Karachi`

### HCC Authentication Failed
**Solution:** Update `HCC_BEARER_TOKEN` or `HCC_COOKIE` in `.env`

### Scheduler Not Running
**Solution:** Set up cron job:
```bash
* * * * * cd /path/to/project && php artisan schedule:run
```

## 🎯 Next Steps
1. ✅ Fix timezone inconsistency - DONE
2. ✅ Remove duplicate route - DONE
3. ✅ Recreate .env.example - DONE
4. ✅ Update scheduled tasks - DONE
5. Mark legacy HCC commands as deprecated
6. Add API authentication
7. Improve test coverage

---

**Last Updated:** 2025-01-23

