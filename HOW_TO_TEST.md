# How to Test HCC Attendance Capture

## Quick Test (Recommended)

### Step 1: Run the test script
```bash
test-hcc-capture.bat
```

This will:
1. Start ChromeDriver automatically
2. Open a browser window (you can watch it work)
3. Run the capture command
4. Show you the output files
5. Keep browser open so you can inspect

### Step 2: Watch the Browser
When the browser opens, you'll see it:
- ✅ Go to Hik-Connect
- ✅ Click Log In
- ✅ Enter phone number
- ✅ Enter password  
- ✅ Click Login button
- ⚠️ Try to click Attendance tab
- ⚠️ Try to navigate to Transaction page

**WHAT TO LOOK FOR:**
- Does it actually click through to the Transaction page?
- Do you see a table with attendance data?
- Does it stay on the overview page?

### Step 3: Check the Output

After the command finishes, look at:

```bash
# View the captured response
type storage\app\hcc\response.json
```

**WORKING** = You see JSON with data like:
```json
{
  "data": {
    "reportDataList": [
      {
        "personCode": "123",
        "fullName": "John Doe",
        "clockStamp": "2025-10-15T08:00:00+05:00",
        ...
      }
    ],
    "columnList": [...],
    "total": 50
  }
}
```

**NOT WORKING** = You see:
```json
[]
```
or
```json
{}
```

## Manual Test (If You Want More Control)

### Step 1: Start ChromeDriver
```bash
start-chromedriver.bat
```

### Step 2: Run the command
```bash
php artisan hcc:capture --from=2025-10-01T00:00:00+05:00 --to=2025-10-31T23:59:59+05:00
```

### Step 3: Watch What Happens
- Browser opens
- Watch each step in the console output
- When asked "Close browser? (yes/no)" - type **no** to keep it open
- Manually inspect the page

### Step 4: Manual Navigation Test
If automation doesn't reach the right page:
1. In the open browser, manually click Attendance → Transaction
2. Open DevTools (F12)
3. Go to Network tab
4. Look for the POST to `/hcc/hccattendance/report/v1/list`
5. Click on it and check the Request Payload

This tells you what selectors/steps are missing.

## Debugging Steps

### Check 1: Is ChromeDriver Running?
```bash
tasklist | findstr chromedriver
```
Should show: `chromedriver.exe`

### Check 2: Did Login Work?
Look at console output - should say:
```
✅ Logged in: https://www.hik-connect.com/views/login/index.html#/main/overview
```

If it says still on login page = credentials wrong or captcha appeared

### Check 3: Were Files Created?
```bash
dir storage\app\hcc\
```
Should show:
- `request_headers.json`
- `payload.json`
- `response.json`
- `error.log` (only if error happened)

### Check 4: What's in the Files?
```bash
# Quick check all files
type storage\app\hcc\*.json
```

## Common Issues & Fixes

### Issue: Empty response.json `[]`
**Cause**: Navigation didn't reach Transaction page OR page loaded but API wasn't triggered

**Fix**:
1. Run test with browser open
2. See where navigation stops
3. Update selectors in `HccCaptureAttendanceFinal.php`
4. Or add a "Search" button click step

### Issue: Login failed
**Cause**: Credentials wrong, captcha, or page layout changed

**Fix**: 
1. Try manual login in browser to verify credentials work
2. Check if captcha/MFA is required
3. Screenshot saved in `storage/logs/login-failed.png`

### Issue: "Failed to connect to localhost port 9515"
**Cause**: ChromeDriver not running

**Fix**:
```bash
start-chromedriver.bat
```

## Success Checklist

- [ ] ChromeDriver is running
- [ ] Browser opens automatically
- [ ] Login succeeds (sees "Logged in" message)
- [ ] Navigation clicks execute (watch browser)
- [ ] Response.json contains actual data (not `[]`)
- [ ] Data has `reportDataList` array with attendance records
- [ ] Files saved in `storage/app/hcc/`

## Getting Help

If it's not working:

1. **Run this command:**
   ```bash
   php artisan hcc:capture
   # Type 'no' when asked to close browser
   ```

2. **Take a screenshot of:**
   - The console output
   - The browser page (where did it stop?)
   - The contents of `response.json`

3. **Check `IMPLEMENTATION_STATUS.md`** for detailed troubleshooting

4. **Most likely issue**: Navigation selectors need adjustment for your specific page layout





