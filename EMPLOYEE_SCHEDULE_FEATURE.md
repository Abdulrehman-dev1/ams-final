# Employee Schedule & Location Feature

## ✅ Features Added

### 1. **Location Fields (Latitude & Longitude)**
- ✅ Added `latitude` and `longitude` fields to `daily_employees` table
- ✅ Decimal fields (10,8 for latitude, 11,8 for longitude)
- ✅ Editable in employee edit form
- ✅ Displayed in employee list with Google Maps link
- ✅ Clickable coordinates that open Google Maps

### 2. **Schedule Fields (Time In & Time Out)**
- ✅ Added `time_in` field (default: 09:00:00 / 9:00 AM)
- ✅ Added `time_out` field (default: 19:00:00 / 7:00 PM)
- ✅ Editable in employee edit form
- ✅ Displayed in employee list with badges
- ✅ Shows late cutoff time (time_in + 15 minutes)

### 3. **Dashboard Logic Updates**
- ✅ **Employee-specific late time calculation**
  - Each employee has their own `time_in` (default: 9:00 AM)
  - Late cutoff = `time_in + 15 minutes` (e.g., 9:15 AM)
  - If check-in time > late cutoff, employee is marked as late
- ✅ **Employee-specific early leave calculation**
  - Each employee has their own `time_out` (default: 7:00 PM)
  - If check-out time < `time_out`, employee is marked as early leave
- ✅ Updated `AdminController` to use employee schedules
- ✅ Updated `DashboardWidgetController` to use employee schedules

### 4. **Database Migration**
- ✅ Created migration: `add_location_and_schedule_to_daily_employees_table`
- ✅ Added `latitude`, `longitude`, `time_in`, `time_out` fields
- ✅ Added indexes for performance

### 5. **UI Updates**
- ✅ Edit form: Added Location & Schedule section
- ✅ Employee list: Added Time In, Time Out, and Location columns
- ✅ Time display: Shows time in 12-hour format (e.g., "9:00 AM")
- ✅ Late cutoff: Shows "Late: 9:15 AM" under Time In
- ✅ Location: Clickable badge with Google Maps link

## 📋 How It Works

### Late Time Calculation
1. **Default Behavior**: 
   - Time In: 9:00 AM
   - Late Cutoff: 9:15 AM (9:00 + 15 minutes)
   - If employee checks in after 9:15 AM, they are marked as late

2. **Employee-Specific**:
   - Each employee can have a custom `time_in` (e.g., 8:00 AM)
   - Late cutoff = `time_in + 15 minutes` (e.g., 8:15 AM)
   - Dashboard uses employee's specific schedule for calculations

3. **Fallback**:
   - If employee doesn't have a `time_in` set, uses default (9:00 AM)
   - Late cutoff = 9:15 AM

### Early Leave Calculation
1. **Default Behavior**:
   - Time Out: 7:00 PM
   - If employee checks out before 7:00 PM, they are marked as early leave

2. **Employee-Specific**:
   - Each employee can have a custom `time_out` (e.g., 6:00 PM)
   - If employee checks out before their `time_out`, marked as early leave

### Location Tracking
1. **Latitude & Longitude**:
   - Stored as decimal values
   - Can be entered manually or via GPS
   - Displayed in employee list with 6 decimal precision

2. **Google Maps Integration**:
   - Clickable coordinates
   - Opens Google Maps in new tab
   - Format: `https://www.google.com/maps?q={latitude},{longitude}`

## 🎨 UI Features

### Edit Form
- **Location & Schedule Section**:
  - Latitude input (with validation: -90 to 90)
  - Longitude input (with validation: -180 to 180)
  - Time In input (time picker, default: 9:00 AM)
  - Time Out input (time picker, default: 7:00 PM)
  - Helper text: "Late after 15 minutes (e.g., 9:15)"

### Employee List
- **Time In Column**:
  - Badge showing time (e.g., "9:00 AM")
  - Small text below showing late cutoff (e.g., "Late: 9:15 AM")
- **Time Out Column**:
  - Badge showing time (e.g., "7:00 PM")
- **Location Column**:
  - Clickable badge with coordinates
  - Opens Google Maps on click
  - Shows "—" if not set

## 🔧 Technical Details

### Model Updates
- Added `latitude`, `longitude`, `time_in`, `time_out` to `$fillable`
- Added accessors for `time_in` and `time_out` with defaults
- Added helper method `getLateCutoff()` (not used, but available)

### Controller Updates
1. **AttendanceController**:
   - Added validation for latitude, longitude, time_in, time_out
   - Handles time format conversion (H:i to H:i:s)
   - Sets defaults if fields are empty

2. **AdminController**:
   - Builds employee schedule map before calculating metrics
   - Uses employee-specific late cutoff for each employee
   - Uses employee-specific time_out for early leave calculation
   - Falls back to defaults if employee schedule not found

3. **DashboardWidgetController**:
   - Added `buildEmployeeSchedules()` method
   - Updated `onTime()` to use employee schedules
   - Updated `late()` to use employee schedules
   - Shows expected time in widget responses

### Validation Rules
- Latitude: `nullable|numeric|between:-90,90`
- Longitude: `nullable|numeric|between:-180,180`
- Time In: `nullable|date_format:H:i`
- Time Out: `nullable|date_format:H:i`

## 📝 Example Usage

### Setting Employee Schedule
1. Go to `/admin/daily-people`
2. Click "Edit" next to an employee
3. Scroll to "Location & Schedule" section
4. Enter:
   - Time In: `08:00` (8:00 AM)
   - Time Out: `18:00` (6:00 PM)
   - Latitude: `24.8607`
   - Longitude: `67.0011`
5. Click "Update Employee"

### Result
- Employee's late cutoff: 8:15 AM (8:00 + 15 minutes)
- If employee checks in at 8:16 AM, marked as late
- If employee checks out at 5:59 PM, marked as early leave
- Location: Clickable coordinates open Google Maps

## 🚀 Dashboard Impact

### Before
- All employees used hardcoded 9:30 AM cutoff for late time
- All employees used hardcoded 7:00 PM for early leave

### After
- Each employee uses their own `time_in + 15 minutes` for late cutoff
- Each employee uses their own `time_out` for early leave
- More accurate attendance tracking
- Flexible scheduling per employee

## 📊 Database Schema

```sql
ALTER TABLE daily_employees
ADD COLUMN latitude DECIMAL(10,8) NULL,
ADD COLUMN longitude DECIMAL(11,8) NULL,
ADD COLUMN time_in TIME DEFAULT '09:00:00',
ADD COLUMN time_out TIME DEFAULT '19:00:00';

CREATE INDEX idx_latitude_longitude ON daily_employees(latitude, longitude);
CREATE INDEX idx_time_in ON daily_employees(time_in);
```

## 🎯 Benefits

1. **Flexible Scheduling**: Each employee can have different work hours
2. **Accurate Late Time**: Late time calculated based on employee's schedule
3. **Location Tracking**: Track employee locations for attendance verification
4. **Google Maps Integration**: Easy access to employee locations
5. **Better Dashboard Metrics**: More accurate on-time/late calculations

---

**Status**: ✅ Complete - Location and schedule features are fully functional

