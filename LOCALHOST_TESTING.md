# 🧪 Testing HikCentral Connect Scraper on Windows Localhost

This guide will help you test the Laravel Dusk scraper on your Windows machine before deploying to VPS.

---

## 📋 Prerequisites

- ✅ Windows 10/11
- ✅ XAMPP running
- ✅ PHP 8.0+ with required extensions
- ✅ Composer installed
- ✅ Google Chrome browser installed

---

## 🚀 Step-by-Step Setup

### Step 1: Install Laravel Dusk

Open PowerShell or CMD in your project directory:

```bash
cd D:\XAMPP\htdocs\ams-final

# Install Dusk
composer require --dev laravel/dusk

# Install Dusk (creates tests/Browser directory)
php artisan dusk:install
```

### Step 2: Download ChromeDriver for Windows

```bash
# Check your Chrome version
# Open Chrome → Settings → About Chrome → Note the version (e.g., 119.x.x.x)

# Download matching ChromeDriver from:
# https://chromedriver.chromium.org/downloads

# Or use this PowerShell command to download automatically:
$chromeVersion = (Get-Item "C:\Program Files\Google\Chrome\Application\chrome.exe").VersionInfo.FileVersion.Split('.')[0]
$driverUrl = "https://chromedriver.storage.googleapis.com/LATEST_RELEASE_$chromeVersion"
$latestVersion = Invoke-RestMethod -Uri $driverUrl
$downloadUrl = "https://chromedriver.storage.googleapis.com/$latestVersion/chromedriver_win32.zip"
Invoke-WebRequest -Uri $downloadUrl -OutFile "chromedriver_win32.zip"
Expand-Archive -Path "chromedriver_win32.zip" -DestinationPath "." -Force
```

### Step 3: Place ChromeDriver in Your Project

```bash
# Create a 'drivers' folder in your project
mkdir drivers
move chromedriver.exe drivers\chromedriver.exe
```

### Step 4: Configure .env

Add these lines to your `.env` file:

```env
# HikCentral Connect Scraper Credentials
HCC_USERNAME=your_hik_connect_email@example.com
HCC_PASSWORD=your_hik_connect_password
HCC_LOGIN_URL=https://www.hik-connect.com
HCC_TIMEZONE=Asia/Karachi

# Dusk Driver URL (localhost)
DUSK_DRIVER_URL=http://localhost:9515

# App URL (for Dusk)
APP_URL=http://localhost
```

### Step 5: Start ChromeDriver in Background

**Option A: Using PowerShell**

Open a new PowerShell window and run:

```powershell
cd D:\XAMPP\htdocs\ams-final
.\drivers\chromedriver.exe --port=9515
```

Keep this window open while testing.

**Option B: Using Task Scheduler (Auto-start)**

Create a batch file `start-chromedriver.bat`:

```batch
@echo off
cd /d D:\XAMPP\htdocs\ams-final
.\drivers\chromedriver.exe --port=9515
```

Double-click to run, or add to Windows Startup folder.

### Step 6: Verify ChromeDriver is Running

Open another PowerShell/CMD window:

```bash
# Test if ChromeDriver is accessible
curl http://localhost:9515/status

# You should see JSON response like:
# {"value":{"ready":true,"message":"ChromeDriver ready"}}
```

### Step 7: Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

---

## ✅ Testing Commands

### Test 1: Scrape Devices

```bash
php artisan hcc:scrape:devices
```

**Expected Output:**
```
Scraping devices from HikCentral Connect...
✓ Scraped X devices
```

### Test 2: Scrape Recent Attendance (Last 10 Minutes)

```bash
php artisan hcc:scrape:recent
```

**Expected Output:**
```
Scraping recent attendance (10 minute look-back)...
From: 2025-10-18 16:00:00
To: 2025-10-18 16:10:00
✓ Scraped X recent attendance records
```

### Test 3: Scrape Date Range

```bash
php artisan hcc:scrape:attendance --from=2025-10-18 --to=2025-10-18
```

**Expected Output:**
```
Scraping attendance from 2025-10-18 to 2025-10-18...
✓ Saved X attendance records
Scraping completed! Total records: X
```

### Test 4: Run with Visible Browser (Debug Mode)

To see what the browser is doing, temporarily disable headless mode:

Edit `tests/DuskTestCase.php` and remove `'--headless'`:

```php
protected function driver()
{
    $options = (new ChromeOptions)->addArguments(collect([
        '--disable-gpu',
        // '--headless',  // Comment this out to see browser
        '--no-sandbox',
        '--disable-dev-shm-usage',
        '--window-size=1920,1080',
    ])->all());
    // ... rest of code
}
```

Then run:
```bash
php artisan hcc:scrape:devices
```

You'll see Chrome window opening and performing actions!

---

## 🔍 Debugging

### Enable Detailed Logging

In `app/Services/HccDuskScraper.php`, the service already logs to Laravel logs.

**View logs in real-time:**

```powershell
# In PowerShell
Get-Content storage\logs\laravel.log -Wait -Tail 50
```

### Take Screenshots on Errors

Add to your scraper commands:

```php
try {
    // ... scraping code
} catch (\Exception $e) {
    $browser->screenshot('error-screenshot');
    throw $e;
}
```

Screenshots saved to: `tests/Browser/screenshots/`

### Check ChromeDriver Logs

ChromeDriver console shows detailed information about browser interactions.

---

## 🐛 Common Issues & Fixes

### Issue 1: "ChromeDriver not found"

**Fix:**
```bash
# Ensure chromedriver.exe is in drivers folder
dir drivers\chromedriver.exe

# Or add to PATH
$env:Path += ";D:\XAMPP\htdocs\ams-final\drivers"
```

### Issue 2: "Connection refused to localhost:9515"

**Fix:**
```bash
# Check if ChromeDriver is running
netstat -ano | findstr "9515"

# If not, start it:
.\drivers\chromedriver.exe --port=9515
```

### Issue 3: "Chrome version mismatch"

**Fix:**
```bash
# Check Chrome version
chrome --version

# Download matching ChromeDriver from:
# https://chromedriver.chromium.org/downloads
```

### Issue 4: "Login timeout" or "Element not found"

**Fix:**

1. **Check selectors** - HikConnect UI may have changed
2. **Increase timeouts** in `HccDuskScraper.php`:
   ```php
   $browser->waitFor('.attendance-container', 30); // Increase from 10 to 30
   ```
3. **Run in visible mode** to see what's happening

### Issue 5: "Session not created"

**Fix:**
```bash
# Kill any existing Chrome processes
taskkill /F /IM chrome.exe /T

# Restart ChromeDriver
```

---

## 📊 Verify Data in Database

After scraping, check your database:

```bash
# Using MySQL CLI
mysql -u root -p

USE your_database_name;

# Check scraped devices
SELECT * FROM hcc_devices;

# Check scraped attendance
SELECT * FROM hcc_attendance_transactions ORDER BY created_at DESC LIMIT 10;

# Count records
SELECT COUNT(*) FROM hcc_attendance_transactions;
```

**Or use phpMyAdmin:**
- Go to: http://localhost/phpmyadmin
- Select your database
- Browse `hcc_devices` and `hcc_attendance_transactions` tables

---

## 🌐 View in Web Interface

Once data is scraped, view it in your browser:

```
http://localhost/admin/hcc/attendance
http://localhost/admin/hcc/devices
```

---

## 🔄 Troubleshooting Flow

```
1. Is ChromeDriver running?
   → Check: curl http://localhost:9515/status
   
2. Are credentials correct in .env?
   → Check: HCC_USERNAME and HCC_PASSWORD
   
3. Is Chrome version matching ChromeDriver?
   → Run: chrome --version
   → Check ChromeDriver version
   
4. Can the scraper access the login page?
   → Run in visible mode (disable headless)
   → Watch for errors in browser console
   
5. Are selectors correct?
   → Check browser DevTools
   → Update selectors in HccDuskScraper.php
```

---

## 📝 Quick Test Script

Create `test-scraper.bat`:

```batch
@echo off
echo Testing HCC Scraper on Localhost...
echo.

echo [1/4] Checking ChromeDriver...
curl -s http://localhost:9515/status >nul
if %errorlevel% neq 0 (
    echo ERROR: ChromeDriver not running on port 9515
    echo Please start: drivers\chromedriver.exe --port=9515
    pause
    exit /b 1
)
echo OK - ChromeDriver is running

echo.
echo [2/4] Testing device scraping...
php artisan hcc:scrape:devices

echo.
echo [3/4] Testing recent attendance scraping...
php artisan hcc:scrape:recent

echo.
echo [4/4] Checking database...
php artisan tinker --execute="echo 'Devices: ' . \App\Models\HccDevice::count(); echo PHP_EOL; echo 'Attendance: ' . \App\Models\HccAttendanceTransaction::count();"

echo.
echo Test completed!
pause
```

Run: `test-scraper.bat`

---

## 🎯 Testing Checklist

Before deploying to VPS, verify:

- [ ] ChromeDriver starts successfully
- [ ] `hcc:scrape:devices` command works
- [ ] `hcc:scrape:recent` command works
- [ ] `hcc:scrape:attendance --from --to` works
- [ ] Data appears in database tables
- [ ] Web interface displays scraped data
- [ ] Logs show no errors
- [ ] Screenshots work (in debug mode)

---

## 🚀 Next Steps

Once everything works on localhost:

1. ✅ **Commit your code** to Git
2. ✅ **Deploy to AlmaLinux VPS** (follow `DEPLOYMENT.md`)
3. ✅ **Configure cron jobs** for automatic scraping
4. ✅ **Monitor logs** on production

---

## 💡 Pro Tips

### Tip 1: Use Visible Mode for Development

Disable headless when developing/debugging:
```php
// Comment out --headless in DuskTestCase.php
```

### Tip 2: Add Debug Pauses

In `HccDuskScraper.php`:
```php
$browser->pause(5000); // Wait 5 seconds to inspect
```

### Tip 3: Log Everything

Add logging at each step:
```php
Log::info("HCC Dusk: Step completed", ['data' => $someData]);
```

### Tip 4: Test with Small Date Ranges First

```bash
# Test with just today
php artisan hcc:scrape:attendance --from=2025-10-18 --to=2025-10-18

# Then expand to larger ranges
```

---

## 📞 Need Help?

If you encounter issues:

1. Check `storage/logs/laravel.log`
2. Run in visible mode (disable headless)
3. Take screenshots at each step
4. Check HikConnect UI hasn't changed
5. Verify credentials are correct

---

🎉 **You're ready to test the scraper on localhost!**

Start ChromeDriver, configure your credentials, and run the test commands above.







