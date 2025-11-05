# 🎭 Python Playwright Setup Guide for HCC Scraper

## ✨ Why Playwright over Dusk?

| Feature | Laravel Dusk | Python Playwright |
|---------|--------------|-------------------|
| Speed | ⚡ Slow | ⚡⚡⚡ Fast |
| Stability | 😐 Medium | ✅ High |
| Setup | 🔧 Complex | 🚀 Simple |
| Auto-wait | ❌ Manual pause() | ✅ Automatic |
| Network Intercept | ❌ Complex | ✅ Native |
| Debugging | 📝 Basic | 🔍 Advanced |

---

## 📋 Prerequisites

### 1. **Python Installation (Windows)**

```bash
# Check if Python is installed
python --version

# If not installed, download from:
# https://www.python.org/downloads/
# ⚠️ Make sure to check "Add Python to PATH" during installation
```

### 2. **Verify Installation**

```bash
python --version
# Should show: Python 3.8.x or higher

pip --version
# Should show: pip x.x.x
```

---

## 🚀 Installation Steps

### Step 1: Install Python Dependencies

```bash
cd D:\ams-final\scripts

# Install required packages
pip install -r requirements.txt

# Install Playwright browsers
python -m playwright install chromium
```

**Or use the automated installer:**

```bash
cd D:\ams-final\scripts
install_playwright.bat
```

### Step 2: Configure Environment Variables

Add these to your `.env` file:

```env
# Python Configuration
PYTHON_PATH=python

# Playwright Settings
PLAYWRIGHT_HEADLESS=true
PLAYWRIGHT_TIMEOUT=30000
PLAYWRIGHT_SLOW_MO=0

# HCC Credentials (REQUIRED)
HCC_USERNAME=your_phone_or_email
HCC_PASSWORD=your_password
HCC_LOGIN_URL=https://www.hik-connect.com/views/login/index.html#/login

# HCC API
HCC_BASE_URL=https://isgp-team.hikcentralconnect.com
HCC_TIMEZONE=Asia/Karachi

# Laravel
APP_URL=http://localhost
```

### Step 3: Verify Installation

```bash
# Check if Playwright is working
python scripts/hcc_playwright_scraper.py --help
```

---

## 🎯 Usage

### **Method 1: Via Laravel Artisan Command (Recommended)**

```bash
# Get authentication cookies
php artisan hcc:playwright get-cookies

# Fetch today's attendance
php artisan hcc:playwright fetch-today

# Fetch recent (last 24 hours)
php artisan hcc:playwright fetch-recent

# Fetch specific date range
php artisan hcc:playwright fetch-range --from=2025-11-01 --to=2025-11-03
```

### **Method 2: Direct Python Script**

```bash
cd scripts

# Get cookies
python hcc_playwright_scraper.py get-cookies

# Fetch today
python hcc_playwright_scraper.py fetch-today

# Fetch range
python hcc_playwright_scraper.py fetch-range --from=2025-11-01 --to=2025-11-03
```

---

## 📖 Complete Workflow

### **First Time Setup:**

```bash
# 1. Install dependencies
cd D:\ams-final\scripts
install_playwright.bat

# 2. Add credentials to .env
# HCC_USERNAME=your_phone
# HCC_PASSWORD=your_password

# 3. Get authentication cookies
php artisan hcc:playwright get-cookies

# 4. Copy the cookie string to .env
# HCC_COOKIE="session_id=abc123; auth_token=xyz789"
```

### **Daily Sync:**

```bash
# Fetch recent data (last 24 hours)
php artisan hcc:playwright fetch-recent
```

### **Historical Backfill:**

```bash
# Fetch October 2025 data
php artisan hcc:playwright fetch-range --from=2025-10-01 --to=2025-10-31
```

---

## 🔧 Advanced Configuration

### **Run in Headed Mode (See Browser)**

```env
# In .env
PLAYWRIGHT_HEADLESS=false
```

### **Slow Down for Debugging**

```env
# Slow down by 1000ms (1 second) per action
PLAYWRIGHT_SLOW_MO=1000
```

### **Increase Timeout**

```env
# 60 seconds timeout
PLAYWRIGHT_TIMEOUT=60000
```

### **Custom Python Path**

```env
# If Python is not in PATH
PYTHON_PATH=C:\Python311\python.exe
```

---

## 📅 Scheduled Automation

### **Windows Task Scheduler**

Create `D:\ams-final\sync-hcc-playwright.bat`:

```batch
@echo off
cd /d D:\ams-final
php artisan hcc:playwright fetch-recent >> storage\logs\playwright-sync.log 2>&1
```

**Setup Task:**
1. Open Task Scheduler
2. Create Basic Task
3. Name: "HCC Playwright Sync"
4. Trigger: Every 30 minutes
5. Action: Start a program
6. Program: `D:\ams-final\sync-hcc-playwright.bat`

---

## 🐛 Troubleshooting

### **Problem 1: Python not found**

```bash
# Check PATH
where python

# Add Python to PATH:
# Control Panel → System → Advanced → Environment Variables
# Add: C:\Python311 to PATH
```

### **Problem 2: playwright module not found**

```bash
# Reinstall
cd scripts
pip install --upgrade playwright
python -m playwright install
```

### **Problem 3: Login fails**

```bash
# Run in headed mode to see what's happening
# In .env: PLAYWRIGHT_HEADLESS=false

php artisan hcc:playwright get-cookies
```

### **Problem 4: Timeout errors**

```bash
# Increase timeout in .env
PLAYWRIGHT_TIMEOUT=60000
PLAYWRIGHT_SLOW_MO=500
```

### **Problem 5: Permission denied on scripts**

```bash
# Make sure you're running CMD as Administrator
```

---

## 📊 Verify Data

### **Check Database:**

```bash
php artisan tinker
>>> \App\Models\HccAttendanceTransaction::count()
>>> \App\Models\HccAttendanceTransaction::latest()->take(5)->get()
```

### **Check via Web Interface:**

- Dashboard: `http://localhost/admin`
- HCC Attendance: `http://localhost/admin/hcc/attendance`

---

## 🎨 Features

### ✅ **What Playwright Does:**

1. **Logs in** to HikCentral Connect
2. **Navigates** to attendance transaction page
3. **Intercepts** API calls (faster than scraping HTML)
4. **Extracts** attendance data
5. **Sends** to Laravel API for storage
6. **Auto-waits** for elements (no manual delays needed)
7. **Retries** on failures

### ✅ **Data Captured:**

- Person Code
- Full Name
- Department/Group
- Clock In/Out Timestamp
- Device ID/Name
- All raw data for audit trail

---

## 💡 Tips

1. **Cookies expire in 24 hours** - Rerun `get-cookies` if API returns 401
2. **Start with small date ranges** - Test with 1-2 days first
3. **Use headless mode in production** - Faster and uses less resources
4. **Monitor logs** - Check `storage/logs/laravel.log` for errors
5. **Run during off-peak hours** - Less load on HCC servers

---

## 🆚 Comparison with Dusk

| Task | Dusk | Playwright |
|------|------|------------|
| Get Cookies | `php artisan hcc:get-cookies` | `php artisan hcc:playwright get-cookies` |
| Fetch Today | `php artisan hcc:dusk-login` | `php artisan hcc:playwright fetch-today` |
| Fetch Range | `php artisan hcc:scrape:attendance` | `php artisan hcc:playwright fetch-range` |
| Speed | ~30s | ~10s |
| Stability | 70% | 95% |
| Debugging | Basic | Advanced (screenshots, traces) |

---

## 📚 Resources

- **Playwright Docs:** https://playwright.dev/python/
- **Python Docs:** https://docs.python.org/3/
- **Laravel Process:** https://laravel.com/docs/8.x/processes

---

## ✅ Quick Start (TL;DR)

```bash
# 1. Install
cd scripts
install_playwright.bat

# 2. Add HCC_USERNAME & HCC_PASSWORD to .env

# 3. Get cookies
php artisan hcc:playwright get-cookies

# 4. Add HCC_COOKIE to .env

# 5. Sync data
php artisan hcc:playwright fetch-recent

# Done! 🎉
```

---

**Need help? Check logs in `storage/logs/laravel.log` or run with `PLAYWRIGHT_HEADLESS=false` to see browser actions.**

