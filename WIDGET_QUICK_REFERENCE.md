# Dashboard Widget Quick Reference Card

## 🎯 Widget Overview

| Widget | Color | Icon | Shows | API Endpoint |
|--------|-------|------|-------|-------------|
| **Total Employees** | Blue | 👤 ID Badge | Total employee count | `/api/dashboard/widgets/total-employees` |
| **On Time %** | Blue | ⏰ Alarm Clock | Percentage on time | `/api/dashboard/widgets/on-time` |
| **On Time Today** | Blue | ✅ Checkbox | Count on time | `/api/dashboard/widgets/on-time` |
| **Late Today** | Blue | ⚠️ Alert | Count late | `/api/dashboard/widgets/late` |
| **Mobile Check-in** | Blue | 📱 Mobile | Mobile app check-ins | `/api/dashboard/widgets/mobile-checkins` |
| **Device Check-in** | Blue | 💾 Device | Biometric check-ins | `/api/dashboard/widgets/device-checkins` |
| **Early Leave** | Blue | 🧾 Receipt | Left before shift end | `/api/dashboard/widgets/early-leave` |
| **Absent** | Blue | ❌ N/A | Absent employees | `/api/dashboard/widgets/absent` |
| **Avg Check-in Time** | Green | ⏱️ Time | Average arrival time | `/api/dashboard/widgets/on-time` |
| **Overtime Count** | Yellow | ⏰ Alarm | Stayed after hours | `/api/dashboard/widgets/overtime` |
| **Pending Leaves** | Blue | 📅 Calendar | Pending requests | `/api/dashboard/widgets/pending-leaves` |
| **Device Status** | Dark | 💻 Devices | Active/Total devices | `/api/dashboard/widgets/device-status` |

---

## ⚙️ Configuration Settings

### Environment Variables (.env)

```env
# On-time cutoff (check-in before this = on time)
ATTENDANCE_ON_TIME_CUTOFF=09:30:00

# Absent cutoff (no check-in by this time = absent)
ATTENDANCE_ABSENT_CUTOFF=10:00:00

# Shift end time (check-out before this = early leave)
ATTENDANCE_SHIFT_END_TIME=19:00:00

# Shift start time (expected check-in time)
ATTENDANCE_SHIFT_START_TIME=09:00:00

# Application timezone
APP_TIMEZONE=Asia/Karachi
```

### Apply Configuration Changes

```bash
php artisan config:clear
php artisan config:cache
```

---

## 🔢 Business Rules

| Rule | Time | Description |
|------|------|-------------|
| **On Time** | ≤ 09:30:00 | Arrived on time or early |
| **Late** | > 09:30:00 | Arrived after cutoff |
| **Absent** | No check-in by 10:00:00 | Marked absent |
| **Early Leave** | < 19:00:00 | Left before shift end |
| **Overtime** | > 19:00:00 | Stayed after shift end |

---

## 📊 Widget Calculations

### Total Employees
```
Count of all records in daily_employees table
```

### On Time %
```
(On-time arrivals / Total arrivals) × 100
```

### On Time Count
```
Count where check_in_time ≤ 09:30:00
```

### Late Count
```
Count where check_in_time > 09:30:00
```

### Mobile Check-ins
```
Count where device_name contains 'mobile' or 'app'
```

### Device Check-ins
```
Count where source is NOT mobile (biometric/card readers)
```

### Early Leave
```
Count where check_out_time < 19:00:00
```

### Absent
```
Total employees - Present by 10:00 AM
```

### Average Check-in Time
```
SUM(all check-in minutes) / COUNT(arrivals)
Formatted as HH:MM
```

### Overtime Count
```
Count where check_out_time > 19:00:00
```

### Pending Leaves
```
Count of leaves where status = 0 or NULL
```

### Device Status
```
Active Devices / Total Devices
```

---

## 🎨 Color Codes

| Status | Color | Badge | Meaning |
|--------|-------|-------|---------|
| Success | Green | `badge-success` | On time, healthy, active |
| Warning | Yellow | `badge-warning` | Late, delayed, pending |
| Danger | Red | `badge-danger` | Absent, error, inactive |
| Info | Blue | `badge-info` | Informational, overtime |
| Primary | Blue | `badge-primary` | Standard data |

---

## 💡 SweetAlert Popup Columns

### Total Employees Popup
- Person Code
- Name
- Group
- Contact (Phone/Email)

### On Time Popup
- Person Code
- Name
- Check-in Time (with success badge)
- Source (Mobile/Device)

### Late Popup
- Person Code
- Name
- Check-in Time (with danger badge)
- Late By (minutes with warning badge)
- Source

### Mobile/Device Check-ins Popup
- Person Code
- Name
- Check-in Time
- Device Type

### Early Leave Popup
- Person Code
- Name
- Check-out Time
- Early By (minutes)
- Source

### Absent Popup
- Person Code
- Name
- Group
- Status (danger badge)

### Overtime Popup
- Person Code
- Name
- Check-out Time
- Overtime (minutes)

### Pending Leaves Popup
- Request ID
- Employee Name
- Leave Date
- Leave Time
- Type

### Device Status Popup
- Device ID
- Device Name
- IP Address (as code)
- Serial Number
- Status

---

## 🚀 Quick Actions

### View Today's Dashboard
```
URL: /admin
```

### View Specific Date
```
URL: /admin?date=2025-10-15
```

### Test Widget API
```bash
# Test on-time widget
curl http://localhost/api/dashboard/widgets/on-time?date=2025-10-17

# Test total employees
curl http://localhost/api/dashboard/widgets/total-employees

# Test device status
curl http://localhost/api/dashboard/widgets/device-status
```

### Debug Mode (Browser Console)
```javascript
// Check focus date
console.log(FOCUS_DATE);

// Test widget click
showWidgetDetails('on-time');

// Manual API call
fetch('/api/dashboard/widgets/late?date=' + FOCUS_DATE)
  .then(r => r.json())
  .then(console.log);
```

---

## 🔍 Troubleshooting Checklist

- [ ] ACS events synced? Check `acs_events` table
- [ ] Config cache cleared? Run `php artisan config:clear`
- [ ] Routes registered? Run `php artisan route:list | grep widget`
- [ ] Browser console errors? Check F12 Developer Tools
- [ ] API accessible? Test endpoint in browser/Postman
- [ ] Database connected? Check `daily_employees` table
- [ ] SweetAlert loaded? Check page source for sweetalert.min.js
- [ ] Date format correct? Use YYYY-MM-DD format

---

## 📱 Mobile Responsiveness

Widgets are responsive and adapt to screen sizes:

- **Desktop (xl)**: 4 columns per row
- **Tablet (md)**: 2 columns per row
- **Mobile (sm)**: 1 column (stacked)

SweetAlert popups:
- **Desktop**: 800px width
- **Mobile**: 90% width with auto-scroll

---

## 🎯 Performance Tips

1. **Limit Results**: Popups show max 100 records
2. **Cache Config**: Always cache after changes
3. **Index Database**: Ensure indexes on `person_code`, `occur_time_pk`
4. **Optimize Queries**: Filter by date to reduce dataset
5. **Browser Cache**: Leverage browser caching for static assets

---

## 📞 Common Use Cases

### Scenario 1: Check who's late today
1. Look at **Late Today** widget
2. Click **More info**
3. See list with times and how many minutes late

### Scenario 2: Verify mobile app adoption
1. Compare **Mobile Check-in** vs **Device Check-in**
2. Click **More info** on each
3. Review percentage using mobile app

### Scenario 3: Identify attendance patterns
1. Check **Average Check-in Time**
2. Compare with **On Time %**
3. Adjust policies if needed

### Scenario 4: Approve pending leaves
1. Check **Pending Leaves** widget
2. Click **More info**
3. Note request IDs
4. Navigate to leave management

### Scenario 5: Monitor device health
1. Check **Device Status** widget
2. If X/Y shows issues (e.g., 3/5)
3. Click **More info**
4. Identify offline devices

---

**Last Updated:** October 2025  
**Version:** 2.0


