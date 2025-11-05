# 🎭 HCC Playwright Scraper

Python Playwright scraper for HikCentral Connect attendance data.

## 📁 Files

- `hcc_playwright_scraper.py` - Main scraper script
- `hcc_config.py` - Configuration handler (reads from Laravel .env)
- `requirements.txt` - Python dependencies
- `install_playwright.bat` - Windows installer script

## 🚀 Installation

```bash
# Run the installer
install_playwright.bat
```

This will:
1. Install Python packages (playwright, requests, python-dotenv)
2. Install Chromium browser for Playwright
3. Verify installation

## ⚙️ Configuration

The scraper reads configuration from Laravel `.env` file (parent directory).

Required variables:
```env
HCC_USERNAME=your_phone_or_email
HCC_PASSWORD=your_password
PYTHON_PATH=python
```

Optional:
```env
PLAYWRIGHT_HEADLESS=true
PLAYWRIGHT_TIMEOUT=30000
PLAYWRIGHT_SLOW_MO=0
HCC_LOGIN_URL=https://www.hik-connect.com/views/login/index.html#/login
HCC_BASE_URL=https://isgp-team.hikcentralconnect.com
HCC_TIMEZONE=Asia/Karachi
APP_URL=http://localhost
```

## 🎯 Usage

### Via Laravel Artisan (Recommended)

```bash
# Get cookies
php artisan hcc:playwright get-cookies

# Fetch today
php artisan hcc:playwright fetch-today

# Fetch range
php artisan hcc:playwright fetch-range --from=2025-11-01 --to=2025-11-03

# Fetch recent (last 24 hours)
php artisan hcc:playwright fetch-recent
```

### Direct Python Script

```bash
cd scripts

# Get cookies
python hcc_playwright_scraper.py get-cookies

# Fetch today
python hcc_playwright_scraper.py fetch-today

# Fetch range
python hcc_playwright_scraper.py fetch-range --from=2025-11-01 --to=2025-11-03

# Fetch recent
python hcc_playwright_scraper.py fetch-recent
```

## 🔧 How It Works

1. **Login**: Authenticates to HikCentral Connect using credentials
2. **Navigate**: Goes to attendance transaction page
3. **Intercept**: Captures API calls (faster than HTML scraping)
4. **Extract**: Parses attendance data from API responses
5. **Save**: POSTs data to Laravel API endpoint (`/api/playwright/save-attendance`)

## 📊 Data Flow

```
HCC Website 
    ↓ (login)
Playwright Browser
    ↓ (intercept API)
Attendance Data
    ↓ (HTTP POST)
Laravel API (/api/playwright/save-attendance)
    ↓ (save)
Database (hcc_attendance_transactions)
```

## 🐛 Debugging

### Run in Headed Mode (see browser)

```env
PLAYWRIGHT_HEADLESS=false
```

### Slow Down Actions

```env
PLAYWRIGHT_SLOW_MO=1000  # 1 second delay per action
```

### Increase Timeout

```env
PLAYWRIGHT_TIMEOUT=60000  # 60 seconds
```

## 📝 Logs

Python script logs to console.
Laravel logs to `storage/logs/laravel.log`

## 🆚 vs Laravel Dusk

| Feature | Dusk | Playwright |
|---------|------|------------|
| Speed | Slow | Fast ✅ |
| Stability | Medium | High ✅ |
| Setup | Complex | Simple ✅ |
| Auto-wait | Manual | Automatic ✅ |
| Debugging | Basic | Advanced ✅ |

## 🔒 Security

- Credentials are read from `.env` (not hardcoded)
- Cookies are printed to console (user must manually copy)
- No credentials stored in Python scripts

## 📚 Dependencies

- **playwright** - Browser automation
- **requests** - HTTP client for Laravel API
- **python-dotenv** - Read .env files

## 🆘 Troubleshooting

### Python not found
```bash
where python
# Add Python to PATH
```

### Playwright not installed
```bash
pip install playwright
python -m playwright install chromium
```

### Login fails
```bash
# Check credentials in .env
# Run in visible mode: PLAYWRIGHT_HEADLESS=false
```

### Module import error
```bash
pip install -r requirements.txt
```

## 📖 Full Documentation

See parent directory:
- `PLAYWRIGHT_SETUP.md` - Complete setup guide
- `QUICK_START_PLAYWRIGHT.md` - Quick start guide

