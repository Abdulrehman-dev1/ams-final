# HCC Attendance Data Capture Guide

## Overview
This guide explains how to capture attendance data from Hik-Connect Central (HCC) attendance API.

## Problem Summary
The HCC system uses a complex authentication flow:
1. Login at `www.hik-connect.com` with phone/password
2. Navigation to `hikcentralconnect.com` domain
3. API calls require authentication tokens that are domain-specific

## Solutions Created

### 1. Browser Automation with API Capture (RECOMMENDED)
**Command**: `php artisan hcc:capture-attendance`  
**File**: `app/Console/Commands/HccCaptureAttendanceData.php`

This command:
- Opens a browser and logs in automatically
- Navigates through the UI to the attendance page
- Injects JavaScript to capture API calls
- Saves attendance records to database

**Usage**:
```bash
# Start ChromeDriver first
start-chromedriver.bat

# Run the command
php artisan hcc:capture-attendance --from=2025-10-01 --to=2025-10-31

# Or use the batch file
hcc-fetch-attendance.bat 2025-10-01 2025-10-31
```

**Current Status**: Login and navigation need refinement. The Vue.js selectors may need adjustment.

### 2. Direct API Call with Cookies
**Command**: `php artisan hcc:fetch-attendance`  
**File**: `app/Console/Commands/HccFetchAttendanceApi.php`

This command:
- Logs in via browser to get cookies
- Makes direct API calls using the cookies
- Displays full API request/response

**API Endpoint**:
```
POST https://isgp-team.hikcentralconnect.com/hcc/hccattendance/report/v1/list
```

**Payload Structure**:
```json
{
    "page": 1,
    "pageSize": 20,
    "language": "en",
    "reportTypeId": 1,
    "columnIdList": [],
    "filterList": [
        {
            "columnName": "fullName",
            "operation": "LIKE",
            "value": ""
        },
        {
            "columnName": "personCode",
            "operation": "LIKE",
            "value": ""
        },
        {
            "columnName": "groupId",
            "operation": "IN",
            "value": ""
        },
        {
            "columnName": "clockStamp",
            "operation": "BETWEEN",
            "value": "2025-10-01T00:00:00+05:00,2025-10-31T23:59:59+05:00"
        },
        {
            "columnName": "deviceId",
            "operation": "IN",
            "value": ""
        }
    ]
}
```

**Current Issue**: API returns `VMS002004: You need to login first!`  
**Reason**: Authentication tokens/cookies are not being properly transferred across domains.

### 3. Debug Cookie Inspector
**Command**: `php artisan hcc:debug-cookies`  
**File**: `app/Console/Commands/HccDebugCookies.php`

This command:
- Opens browser in non-headless mode
- Logs in automatically
- Lets you manually navigate and inspect cookies/tokens
- Shows localStorage and sessionStorage contents

**Usage**: Use this to debug and understand the authentication flow.

## Next Steps to Fix Authentication

The key issue is that the HCC API at `isgp-team.hikcentralconnect.com` requires authentication that isn't being captured properly. Here are possible solutions:

### Option A: Capture Bearer Token from Browser
The API likely uses a Bearer token stored in:
- localStorage
- sessionStorage
- Response headers after login

**Action**: Run `php artisan hcc:debug-cookies`, manually navigate to the attendance page, open DevTools, and check:
1. Application > Local Storage > Look for tokens
2. Network tab > Find the API call > Check Request Headers for `Authorization: Bearer xxx`

### Option B: Improve UI Navigation
The current navigation isn't reaching the actual attendance transaction page. This could be fixed by:
1. Using correct CSS selectors for the Vue.js components
2. Waiting for elements to be present before clicking
3. Using direct URL navigation if the route is known

**Action**: Update `app/Services/HccDuskScraper.php` `navigateToAttendance()` method with correct selectors.

### Option C: Manual Token Extraction
If automation is too complex:
1. Manually login to HCC
2. Navigate to attendance page
3. Open DevTools > Network tab
4. Find the API call to `/hcc/hccattendance/report/v1/list`
5. Copy the request headers (especially `Authorization` or `Cookie`)
6. Add to `.env`:
   ```
   HCC_BEARER_TOKEN=your_token_here
   # OR
   HCC_COOKIE=your_cookie_string_here
   ```
7. Use the existing `HccClient` service to make API calls

## Files Created

| File | Purpose |
|------|---------|
| `app/Console/Commands/HccCaptureAttendanceData.php` | Full browser automation with API capture |
| `app/Console/Commands/HccFetchAttendanceApi.php` | Cookie-based API calls |
| `app/Console/Commands/HccDebugCookies.php` | Debug tool for inspecting auth |
| `hcc-fetch-attendance.bat` | Batch script to run everything |
| `HCC_ATTENDANCE_CAPTURE_GUIDE.md` | This guide |

## Working Commands (from existing codebase)

These commands were already in the project and work:

```bash
# Sync persons from HCC
php artisan hcc:sync-persons

# Login with Dusk and use API
php artisan hcc:dusk-login --from=2025-10-01 --to=2025-10-31

# Scrape devices
php artisan hcc:sync-devices
```

## Technical Details

### Authentication Flow
1. User logs in at `www.hik-connect.com` with phone (03322414255) and password (Alrehman123)
2. After login, user is redirected to `hik-connect.com/#/main/overview`
3. Clicking Attendance tab should navigate to `hikcentralconnect.com` domain
4. The HCC domain requires its own authentication (token or session)

### Domains Involved
- Login: `www.hik-connect.com`
- API: `isgp-team.hikcentralconnect.com`

### Current Blocker
Cookies from `hik-connect.com` domain don't work for `hikcentralconnect.com` domain. Need to find the cross-domain authentication mechanism (likely a Bearer token passed through JavaScript).

## Recommendations

1. **Short-term**: Use manual token extraction (Option C above) to quickly get attendance data
2. **Long-term**: Debug and fix the automation to properly capture/transfer authentication tokens

## Support

If you need help:
1. Run `php artisan hcc:debug-cookies` and manually navigate
2. Check browser DevTools for auth tokens
3. Share the token format and we can update the commands accordingly





