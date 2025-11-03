<p align="center"><a href="https://ams.aliatayee.com" target="_blank"><h1>Attendance Management System</h1></a></p>

## About Attendance Management System

Attendance Management System is a web application based on Laravel which keeps track of employee hours. It is the system you use to document the time your employees work and the time they take off.

## Major Technologies
- HTML5
- CSS
- JAVASCRIPT
- BOOTSTRAP
- PHP
- LARAVEL

## Demo
<a href="http://ams.alihost.co">Demo link</a> 

  ### Admin credential
    username:ali@aliatayee.com
    password:ali123


### Install & Setup

To setup and install Attendance Management System project, follow the below steps:
- Clone this project by the command: 

```
$ git clone https://github.com/aliatayee/Attendance_Management_System
```

- Then switch to the project folder by the bellow query:

```
$ cd Attendance_Management_System
```

- Then open ```env``` file and update database credentials.

- Then run the below command to install composer dependencies

```
$ composer install
```

- Then run the below command to install dependencies

```
$ npm i
```
- Then run the below command to migrate the tables.

```
$ php artisan migrate 
```
- Then run the below command to run seeder.

```
$ php artisan db:seed 
```

- Finally, run the below command to start the project.

```
$ php artisan serve
```

## Screenshots
![1](https://user-images.githubusercontent.com/74867463/144262662-b7fbe66e-5c4c-46fb-8bab-9cf3121c2032.png)
![2](https://user-images.githubusercontent.com/74867463/144262668-545c4d8d-8570-4e38-a769-4c26520e366d.png)
![3](https://user-images.githubusercontent.com/74867463/144262431-32223a06-8c25-49fd-b969-56a4bab697f2.png)
![4](https://user-images.githubusercontent.com/74867463/144262645-29d4bfa4-c737-4123-8c22-c8c1fd49477e.png)


### Prerequisites
- PHP installed
- Composer installed
- IDE to edit and run the code (We use Visual Studio Code 🔥).
- Git to versionning your work.

### Authors
👤 **Ali**

- GitHub: [@aliatayee](https://github.com/aliatayee)
- Twitter: [@aqaatayee](https://twitter.com/aqaatayee)


## 🤝 Contributing
Contributions, issues, and feature requests are welcome!

Feel free to check the [issues page](../../issues/).

## Show your support
Give a ⭐️ if you like this project!

## Acknowledgments
- Hat tip to anyone whose code was used
- Inspiration
- etc

## HikCentral Connect Integration

This application includes a production-ready module for automatically ingesting attendance data from HikCentral Connect's APIs. The module runs scheduled tasks to fetch attendance transactions and device information.

### Features

- **Automatic Ingestion**: Fetches attendance records every 5 minutes with a 10-minute look-back window
- **Device Sync**: Daily synchronization of device information (names, serial numbers)
- **Pagination Support**: Handles large datasets with automatic pagination
- **Duplicate Prevention**: Uses composite unique keys to prevent duplicate records
- **Auth Flexibility**: Supports both Bearer token and Cookie authentication
- **Resilient**: Includes retry logic with exponential backoff for rate limits and server errors
- **Timezone Aware**: All timestamps are properly converted to Asia/Karachi timezone
- **Backfill Support**: Manual command to import historical data by date range

### Configuration

#### 1. Environment Variables

Add these variables to your `.env` file:

```bash
# HikCentral Connect Configuration
HCC_BASE_URL=https://isgp-team.hikcentralconnect.com
HCC_BEARER_TOKEN=your_bearer_token_here
# OR use Cookie authentication
HCC_COOKIE="JSESSIONID=...; HIKTOKEN=..."

# Optional configurations (defaults shown)
HCC_TIMEOUT=20
HCC_PAGE_SIZE=100
HCC_RETRY_TIMES=3
HCC_RETRY_SLEEP=1000
HCC_LOOKBACK_MINUTES=10
HCC_TIMEZONE=Asia/Karachi
```

**Authentication Options:**
- **Bearer Token**: Preferred method. Set `HCC_BEARER_TOKEN` with your API token
- **Cookie**: Alternative method. Set `HCC_COOKIE` with your session cookie string
- If both are present, Bearer token takes precedence

#### 2. Run Migrations

Execute the migrations to create required tables:

```bash
php artisan migrate
```

This creates two tables:
- `hcc_attendance_transactions`: Stores raw attendance transaction logs from HCC
- `hcc_devices`: Stores device information (id, name, serial number)

**Note:** Your existing `attendances` table remains untouched. The HCC module uses a separate table for transaction logs.

### Available Commands

#### Ingest Recent Attendance (Scheduled)

Fetches attendance records from the last 10 minutes:

```bash
php artisan hcc:ingest:recent
```

This command runs automatically every 5 minutes via the Laravel scheduler.

#### Sync Devices (Scheduled)

Synchronizes device information from HCC:

```bash
php artisan hcc:sync:devices
```

This command runs automatically daily at 3:05 AM via the Laravel scheduler.

#### Backfill Date Range (Manual)

Import historical attendance data for a specific date range:

```bash
# Backfill a single month
php artisan hcc:ingest:range --from=2025-10-01 --to=2025-10-31

# Backfill a single day
php artisan hcc:ingest:range --from=2025-10-18 --to=2025-10-18

# Backfill multiple months
php artisan hcc:ingest:range --from=2025-01-01 --to=2025-12-31
```

The command processes each day individually and reports progress.

### Setting Up the Scheduler

#### On Hostinger Shared Hosting

1. Log in to your Hostinger control panel
2. Navigate to **Advanced → Cron Jobs**
3. Add a new cron job with this configuration:

```bash
* * * * * /usr/bin/php /home/YOUR_USERNAME/public_html/artisan schedule:run >> /home/YOUR_USERNAME/laravel-schedule.log 2>&1
```

Replace `YOUR_USERNAME` with your actual Hostinger username and adjust the path to match your Laravel installation.

This cron job runs every minute and executes any scheduled tasks that are due.

#### On VPS/Dedicated Server

Add to crontab:

```bash
crontab -e
```

Add this line:

```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

### Data Model

#### HCC Attendance Transactions Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| person_code | string | Employee code/ID |
| full_name | string | Employee name |
| department | string | Department/group (nullable) |
| attendance_date | date | Date of attendance |
| attendance_time | time | Time of clock-in/out |
| device_id | string | Device identifier (nullable) |
| device_name | string | Device name (auto-populated, nullable) |
| device_serial | string | Device serial number (auto-populated, nullable) |
| weekday | string | Day of week (nullable) |
| source_data | json | Raw API response data |
| created_at | timestamp | Record creation time |
| updated_at | timestamp | Record update time |

**Unique Constraint**: (`person_code`, `attendance_date`, `attendance_time`, `device_id`)

#### HCC Devices Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| device_id | string | Device identifier (unique) |
| name | string | Device name (nullable) |
| serial_no | string | Serial number (nullable) |
| category | string | Device category (nullable) |
| raw | json | Raw API response data |
| created_at | timestamp | Record creation time |
| updated_at | timestamp | Record update time |

### Monitoring & Logs

#### Log Files

- **Scheduled Ingestion**: `storage/logs/hcc-ingest.log`
- **Device Sync**: `storage/logs/hcc-devices.log`
- **General Laravel Log**: `storage/logs/laravel.log`

#### Sample Log Output

```
[2025-10-18 09:00:00] HCC Attendance Ingestion started
[2025-10-18 09:00:02] HCC page 1 processed: 100 fetched, 98 upserted
[2025-10-18 09:00:03] HCC page 2 processed: 24 fetched, 22 upserted
[2025-10-18 09:00:03] HCC Attendance Ingestion completed: 124 fetched, 120 upserts, 4 duplicates skipped in 3.4s
```

#### Authentication Errors

If you see this error:

```
HCC Authentication failed. Please update HCC_BEARER_TOKEN or HCC_COOKIE in .env
```

Your authentication credentials have expired. Update them in `.env`:

1. Log in to HikCentral Connect portal
2. Open browser DevTools (F12)
3. Go to Network tab
4. Make any request
5. Copy the `Authorization` header (Bearer token) OR `Cookie` header
6. Update `.env` file
7. Restart scheduler/commands

### Testing

Run the feature tests:

```bash
php artisan test --filter HccIngestionTest
```

This tests:
- Pagination logic
- Duplicate handling
- Device synchronization
- Command execution
- Date range ingestion

### Troubleshooting

#### No Records Being Ingested

1. Check authentication credentials are valid
2. Verify the date/time range in the API matches your timezone
3. Check logs for API errors: `tail -f storage/logs/laravel.log`
4. Test manually: `php artisan hcc:ingest:recent`

#### Device Names Not Appearing

Device information is populated during the daily sync and when new attendance records are inserted. Run manually:

```bash
php artisan hcc:sync:devices
```

#### Scheduler Not Running

Verify cron job is active:

```bash
# On VPS
crontab -l

# Check Laravel log for scheduler entries
tail -f storage/logs/laravel.log
```

Test scheduler manually:

```bash
php artisan schedule:run
```

#### Duplicate Key Errors

The composite unique index prevents duplicates. If you see duplicate key errors, it means the exact same attendance record (same person, date, time, and device) already exists. This is expected behavior and the record will be updated instead.

### API Response Handling

The module is flexible and adapts to different API response structures:

- Supports multiple response formats: `data.list`, `data`, `list`, `records`
- Handles missing optional fields gracefully
- Stores complete raw response in `source_data` field for debugging

### Performance

- **Batch Processing**: Processes 100 records per page (configurable)
- **Database Upserts**: Uses `updateOrCreate` for efficient deduplication
- **Connection Pooling**: Reuses HTTP connections
- **Background Jobs**: Scheduler runs without blocking

### Security

- Credentials stored in `.env` (not version controlled)
- API responses logged only in debug mode
- SQL injection prevention via Eloquent ORM
- Rate limit handling with exponential backoff

## Contributing

Thank you for considering contributing to the attendance management system!

