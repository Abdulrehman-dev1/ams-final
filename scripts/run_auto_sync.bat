@echo off
REM HCC Auto Sync - Complete automation for cron jobs
REM Run this from Windows Task Scheduler

cd /d D:\ams-final\scripts

echo ========================================
echo HCC Auto Sync - Starting...
echo Date: %date% %time%
echo ========================================
echo.

REM Run Python scraper + auto save
python hcc_auto_sync.py

echo.
echo ========================================
echo Auto Sync Complete!
echo ========================================
echo.

REM Log to file (optional)
REM python hcc_auto_sync.py >> ..\storage\logs\hcc-auto-sync.log 2>&1

