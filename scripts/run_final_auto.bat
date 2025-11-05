@echo off
REM HCC Final Auto Sync - Production ready for Task Scheduler
REM Uses the WORKING browser script

cd /d D:\ams-final\scripts

echo ========================================
echo HCC Auto Sync - %date% %time%
echo ========================================

python hcc_final_auto.py >> ..\storage\logs\hcc-auto-sync.log 2>&1

if %errorlevel% equ 0 (
    echo [SUCCESS] Sync completed
) else (
    echo [FAILED] Error code: %errorlevel%
)

