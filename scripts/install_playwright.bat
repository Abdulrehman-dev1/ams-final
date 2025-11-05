@echo off
echo Installing Python dependencies for HCC Playwright Scraper...
echo.

REM Check if Python is installed
python --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python is not installed or not in PATH
    echo Please install Python 3.8+ from https://www.python.org/
    pause
    exit /b 1
)

echo Installing Python packages...
pip install -r requirements.txt

echo.
echo Installing Playwright browsers...
python -m playwright install chromium

echo.
echo ========================================
echo Installation Complete!
echo ========================================
echo.
echo You can now run:
echo   php artisan hcc:playwright get-cookies
echo.
pause

