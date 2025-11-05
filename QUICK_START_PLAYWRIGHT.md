# 🚀 Quick Start: Playwright Scraper

## 1️⃣ Install Python Dependencies

```bash
cd D:\ams-final\scripts
install_playwright.bat
```

Wait for installation to complete...

---

## 2️⃣ Configure .env

Add these lines to your `.env` file (see `PLAYWRIGHT_ENV_EXAMPLE.txt` for reference):

```env
# Python
PYTHON_PATH=python
PLAYWRIGHT_HEADLESS=true
PLAYWRIGHT_TIMEOUT=30000

# HCC Credentials
HCC_USERNAME=your_phone_or_email
HCC_PASSWORD=your_password
HCC_LOGIN_URL=https://www.hik-connect.com/views/login/index.html#/login
HCC_BASE_URL=https://isgp-team.hikcentralconnect.com
HCC_TIMEZONE=Asia/Karachi

# Laravel
APP_URL=http://localhost
```

---

## 3️⃣ Get Authentication Cookies

```bash
php artisan hcc:playwright get-cookies
```

**Copy the output and add to .env:**

```env
HCC_COOKIE="session_id=abc123; auth_token=xyz789..."
```

---

## 4️⃣ Fetch Attendance Data

```bash
# Fetch today's data
php artisan hcc:playwright fetch-today

# Fetch specific date range
php artisan hcc:playwright fetch-range --from=2025-11-01 --to=2025-11-03

# Fetch last 24 hours
php artisan hcc:playwright fetch-recent
```

---

## ✅ Done!

Check your database:

```bash
php artisan tinker
>>> \App\Models\HccAttendanceTransaction::count()
>>> \App\Models\HccAttendanceTransaction::latest()->take(5)->get()
```

Or view in browser:
- Dashboard: `http://localhost/admin`
- HCC Attendance: `http://localhost/admin/hcc/attendance`

---

## 🔄 Schedule Auto-Sync

Create `sync-hcc-playwright.bat`:

```batch
@echo off
cd /d D:\ams-final
php artisan hcc:playwright fetch-recent >> storage\logs\playwright.log 2>&1
```

Then setup Windows Task Scheduler to run every 30 minutes.

---

## 📚 Full Documentation

See `PLAYWRIGHT_SETUP.md` for detailed documentation.

---

## 🆘 Troubleshooting

### Python not found
```bash
where python
# Add to PATH if needed
```

### Login fails
```bash
# Run in visible mode
# .env: PLAYWRIGHT_HEADLESS=false
php artisan hcc:playwright get-cookies
```

### Module not found
```bash
cd scripts
pip install -r requirements.txt
python -m playwright install chromium
```

---

**Need Help?** Check `storage/logs/laravel.log`

