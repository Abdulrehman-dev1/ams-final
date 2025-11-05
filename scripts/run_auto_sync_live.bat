@echo off
REM HCC Live Sync - Runs every 1 minute via Task Scheduler
REM Optimized for frequent execution

cd /d D:\ams-final\scripts

REM Quick timestamp
echo [%date% %time%] Starting sync...

REM Run auto sync (headless mode for speed)
python hcc_auto_sync.py >> ..\storage\logs\hcc-live-sync.log 2>&1

REM Exit code
if %errorlevel% equ 0 (
    echo [%date% %time%] Success
) else (
    echo [%date% %time%] Failed with error %errorlevel%
)

