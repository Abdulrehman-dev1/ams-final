# ⏰ HCC Auto Sync - Cron Job Setup

## 🎯 Automated Script

**Script:** `scripts/hcc_auto_sync.py`

**What it does:**
1. ✅ Opens browser (headless)
2. ✅ Logs in to HCC
3. ✅ Navigates to Transaction page
4. ✅ Filters for Today
5. ✅ Fetches data via API
6. ✅ Saves to database automatically
7. ✅ Closes browser

**One command does everything!**

---

## 🚀 Quick Test

```bash
cd D:\ams-final\scripts
python hcc_auto_sync.py
```

---

## 📅 Windows Task Scheduler Setup

### Step 1: Create Batch File

Already created: `D:\ams-final\scripts\run_auto_sync.bat`

### Step 2: Setup Task Scheduler

1. Open **Task Scheduler** (Win + R → `taskschd.msc`)

2. Click **Create Basic Task**

3. **Name:** HCC Auto Sync
   **Description:** Sync attendance data from HikCentral Connect

4. **Trigger:** Daily
   - Start: Today
   - Recur every: 1 days
   - Time: 09:00 AM (or your choice)

5. **Action:** Start a program
   - Program: `D:\ams-final\scripts\run_auto_sync.bat`
   - Start in: `D:\ams-final\scripts`

6. **Finish** → Check "Open Properties"

7. In **Properties:**
   - ✅ Check "Run whether user is logged on or not"
   - ✅ Check "Run with highest privileges"
   - **Configure for:** Windows 10

8. Click **OK**

---

## ⏱️ Multiple Times Per Day

If you want to run every hour:

1. **Trigger:** Daily
2. After creating, **edit trigger**
3. **Advanced settings:**
   - ✅ Repeat task every: **1 hour**
   - For a duration of: **1 day**

---

## 📝 Recommended Schedule

```
09:00 AM - Morning sync (yesterday + today)
01:00 PM - Afternoon sync (today)
05:00 PM - Evening sync (today)
```

Create 3 separate tasks with different times.

---

## 🔧 Configuration for Production

### Headless Mode (No browser window)

In `.env`:
```env
PLAYWRIGHT_HEADLESS=true
```

### Logging

To save logs, edit `run_auto_sync.bat`:

```batch
python hcc_auto_sync.py >> ..\storage\logs\hcc-sync.log 2>&1
```

---

## 🐛 Troubleshooting

### Task not running?

1. Check Task Scheduler → Task History
2. Make sure paths are absolute (not relative)
3. Run batch file manually first to test

### Browser doesn't close?

Add timeout in `.env`:
```env
PLAYWRIGHT_TIMEOUT=45000
```

### No data?

Check if cookie expired:
```bash
python hcc_debug_browser.py get-cookies
```
Update `HCC_COOKIE` in `.env`

---

## 📊 Monitor Logs

```bash
# View Laravel logs
tail -f storage/logs/laravel.log

# View sync logs (if enabled)
tail -f storage/logs/hcc-sync.log
```

---

## 🎯 Manual Run

```bash
cd D:\ams-final\scripts
python hcc_auto_sync.py
```

Or double-click:
```
run_auto_sync.bat
```

---

## ✅ Verify After Sync

```bash
php artisan tinker
>>> \App\Models\HccAttendanceTransaction::whereDate('attendance_date', today())->count()
>>> \App\Models\HccAttendanceTransaction::latest()->take(5)->get()
```

---

## 🔄 Alternative: Artisan Command Wrapper

Create a Laravel command:

```bash
php artisan hcc:auto-sync          # Today
php artisan hcc:auto-sync --yesterday
php artisan hcc:auto-sync --from=2024-11-01 --to=2024-11-03
```

Then in Task Scheduler, run:
```
php artisan hcc:auto-sync
```

---

## 📚 Files

- `scripts/hcc_auto_sync.py` - Main automation script
- `scripts/run_auto_sync.bat` - Windows batch wrapper
- `scripts/save_api_data.php` - Database import helper

---

## 💡 Tips

1. **Test manually first** before scheduling
2. **Check cookie expiry** (cookies last ~24 hours)
3. **Monitor first few runs** to ensure stability
4. **Use headless=true** in production
5. **Enable logging** for debugging

