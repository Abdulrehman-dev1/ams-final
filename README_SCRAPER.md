# 🤖 HikCentral Connect Web Scraper with Laravel Dusk

This module provides **browser automation** to scrape attendance data from HikCentral Connect when API access is limited or requires complex authentication.

---

## 🎯 Features

✅ **Fully Automated Login** - Uses your HikConnect credentials  
✅ **Headless Browser** - Runs without GUI on VPS  
✅ **Session Management** - Maintains login state  
✅ **JavaScript Support** - Handles dynamic content  
✅ **API Interception** - Captures network requests  
✅ **Production Ready** - Optimized for AlmaLinux VPS  
✅ **Scheduled Scraping** - Every 5 minutes via cron  

---

## 📦 What's Included

### Services
- `app/Services/HccDuskScraper.php` - Main scraper service

### Commands
- `php artisan hcc:scrape:attendance` - Scrape date range
- `php artisan hcc:scrape:recent` - Scrape last 10 minutes
- `php artisan hcc:scrape:devices` - Scrape device list

### Configuration
- `config/hcc.php` - Scraper settings
- `tests/DuskTestCase.php` - Dusk configuration
- `.env` - Credentials (HCC_USERNAME, HCC_PASSWORD)

### Documentation
- `DUSK_SETUP.md` - Installation guide
- `DEPLOYMENT.md` - Full VPS deployment guide

---

## 🚀 Quick Start (Local Testing)

### 1. Install Dusk

```bash
composer require --dev laravel/dusk
php artisan dusk:install
```

### 2. Configure .env

```bash
HCC_USERNAME=your_email@example.com
HCC_PASSWORD=your_password
HCC_LOGIN_URL=https://www.hik-connect.com
DUSK_DRIVER_URL=http://localhost:9515
```

### 3. Start ChromeDriver (Windows)

Download ChromeDriver and run:
```bash
chromedriver.exe --port=9515
```

### 4. Test Scraper

```bash
# Scrape today's attendance
php artisan hcc:scrape:attendance --from=2025-10-18 --to=2025-10-18

# Scrape recent (last 10 min)
php artisan hcc:scrape:recent

# Scrape devices
php artisan hcc:scrape:devices
```

---

## 🖥️ VPS Deployment (AlmaLinux)

Follow the complete guide in `DEPLOYMENT.md` for:
- Installing Chrome & ChromeDriver
- Setting up system services
- Configuring cron jobs
- Security hardening

**Key command:**
```bash
# On AlmaLinux VPS, after setup:
php artisan hcc:scrape:recent
```

---

## 🔧 How It Works

### 1. Login Flow
```
Browser → Hik-Connect Login → Enter Credentials → Redirect to Dashboard
```

### 2. Navigate to Attendance
```
Dashboard → Click Attendance → Click Transaction → Load Data
```

### 3. Inject API Capture
```javascript
// Intercepts XHR/Fetch requests
window.__attendanceData = [];
// Captures API responses containing attendance data
```

### 4. Extract & Save
```
Extract JSON from API → Normalize Records → Upsert to Database
```

---

## 🎨 Customization

### Adjust Selectors

The scraper uses CSS selectors to interact with the UI. If HikConnect changes their interface, update selectors in `app/Services/HccDuskScraper.php`:

```php
// Login selectors
->type('input[type="email"]', $this->username)
->type('input[type="password"]', $this->password)
->press('button[type="submit"]')
```

### Change Wait Times

```php
// Wait for element (in seconds)
$browser->waitFor('.attendance-container', 10);

// Pause execution
$browser->pause(3000); // 3 seconds
```

### Modify Data Extraction

```php
// Extract from table
$tableRows = $browser->elements('table tbody tr');

// Extract from JavaScript
$jsonData = $browser->script("return window.__attendanceData");
```

---

## 📊 Data Flow

```
HikConnect Portal (Browser)
    ↓
Laravel Dusk (Headless Chrome)
    ↓
HccDuskScraper Service
    ↓
Extract & Normalize Data
    ↓
HccAttendanceTransaction Model
    ↓
MySQL Database (hcc_attendance_transactions)
```

---

## 🔐 Security

### Credentials Storage
- Credentials stored in `.env` (not in version control)
- Use environment-specific .env files
- Consider encrypting sensitive values

### Browser Fingerprinting
- Custom User-Agent set
- Automation flags disabled
- Realistic browser behavior

### Error Handling
- Screenshots on failure (optional)
- Detailed logging
- Graceful error recovery

---

## 📝 Logging

### View Logs
```bash
# Scraper logs
tail -f storage/logs/hcc-scraper.log

# Laravel logs
tail -f storage/logs/laravel.log
```

### Log Levels
```
INFO: Login successful, data extracted
WARNING: Selector not found, retrying
ERROR: Login failed, check credentials
```

---

## 🐛 Troubleshooting

### "Chrome failed to start"
```bash
# Install missing dependencies
sudo dnf install -y liberation-fonts xorg-x11-server-Xvfb
```

### "Connection refused to ChromeDriver"
```bash
# Check if ChromeDriver is running
ps aux | grep chromedriver
netstat -tuln | grep 9515

# Restart service
sudo systemctl restart chromedriver
```

### "Login timeout"
```bash
# Increase timeout in HccDuskScraper.php
$browser->waitForLocation('/dashboard', 60); // 60 seconds
```

### "No data extracted"
```bash
# Check if selectors match the UI
# Enable screenshots on failure
$browser->screenshot('debug-screenshot');
```

---

## 🔄 Comparison: API vs Scraper

| Feature | API Method | Dusk Scraper |
|---------|-----------|--------------|
| Speed | ⚡ Fast | 🐢 Slower |
| Reliability | 🔴 Cookie expires | 🟢 Auto-login |
| Resources | 💚 Low | 🟡 Medium |
| Maintenance | 🔴 Manual token refresh | 🟢 Automatic |
| VPS Support | ✅ Yes | ✅ Yes (with Chrome) |
| Shared Hosting | ✅ Yes | ❌ No |

**Recommendation:** Use **Dusk Scraper** on VPS for hands-free automation!

---

## 📅 Scheduled Tasks

Configured in `app/Console/Kernel.php`:

```php
// Every 5 minutes
$schedule->command('hcc:scrape:recent')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Daily at 3:05 AM
$schedule->command('hcc:scrape:devices')
    ->dailyAt('03:05');
```

---

## 🎯 Use Cases

### Scenario 1: New VPS Deployment
✅ Use Dusk Scraper - No manual token refresh needed

### Scenario 2: Hostinger Shared Hosting
❌ Can't use Dusk - Use API method with manual token updates

### Scenario 3: Enterprise with API Access
✅ Use API method - Faster and more efficient

### Scenario 4: Testing Locally
✅ Use Dusk Scraper - Easy to debug with visible browser

---

## 🚀 Next Steps

1. **Deploy to VPS** - Follow `DEPLOYMENT.md`
2. **Configure Credentials** - Update `.env`
3. **Test Scraper** - Run commands manually
4. **Enable Scheduler** - Setup cron job
5. **Monitor Logs** - Watch for errors
6. **Customize Selectors** - Adjust for your UI

---

## 📞 Support

For issues or questions:
1. Check `DEPLOYMENT.md` troubleshooting section
2. Review Laravel Dusk documentation
3. Check storage/logs for errors
4. Test selectors in browser DevTools

---

## 📜 License

Same as main project

---

🎉 **You now have a fully automated HikCentral Connect scraper!**

The system will run in the background, logging in automatically and fetching attendance data every 5 minutes without any manual intervention.







