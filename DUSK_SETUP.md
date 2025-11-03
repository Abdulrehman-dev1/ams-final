# Laravel Dusk Setup for AlmaLinux VPS

## 📋 Prerequisites

### 1. Install ChromeDriver Dependencies on AlmaLinux

```bash
# Update system
sudo dnf update -y

# Install required packages
sudo dnf install -y wget unzip xorg-x11-server-Xvfb liberation-fonts

# Install Google Chrome
wget https://dl.google.com/linux/direct/google-chrome-stable_current_x86_64.rpm
sudo dnf install -y ./google-chrome-stable_current_x86_64.rpm

# Verify Chrome installation
google-chrome --version

# Install ChromeDriver (will be managed by Dusk automatically)
```

### 2. Install Laravel Dusk

```bash
cd /path/to/your/laravel/project

# Install Dusk via Composer
composer require --dev laravel/dusk

# Install Dusk
php artisan dusk:install

# Set APP_URL in .env
echo "APP_URL=http://localhost" >> .env
```

### 3. Configure Dusk for Headless Mode

Edit `tests/DuskTestCase.php` to ensure it runs headless:

```php
protected function driver()
{
    $options = (new ChromeOptions)->addArguments([
        '--disable-gpu',
        '--headless',
        '--no-sandbox',
        '--disable-dev-shm-usage',
        '--window-size=1920,1080',
    ]);

    return RemoteWebDriver::create(
        'http://localhost:9515',
        DesiredCapabilities::chrome()->setCapability(
            ChromeOptions::CAPABILITY, $options
        )
    );
}
```

### 4. Start ChromeDriver Service

```bash
# Install ChromeDriver as a service
sudo tee /etc/systemd/system/chromedriver.service > /dev/null <<EOF
[Unit]
Description=ChromeDriver Service
After=network.target

[Service]
Type=simple
User=your_user
ExecStart=/usr/local/bin/chromedriver --port=9515
Restart=on-failure

[Install]
WantedBy=multi-user.target
EOF

# Download and install ChromeDriver
CHROME_VERSION=$(google-chrome --version | awk '{print $3}' | cut -d'.' -f1)
wget https://chromedriver.storage.googleapis.com/LATEST_RELEASE_${CHROME_VERSION} -O /tmp/chrome_version
CHROMEDRIVER_VERSION=$(cat /tmp/chrome_version)
wget https://chromedriver.storage.googleapis.com/${CHROMEDRIVER_VERSION}/chromedriver_linux64.zip
unzip chromedriver_linux64.zip
sudo mv chromedriver /usr/local/bin/
sudo chmod +x /usr/local/bin/chromedriver

# Start ChromeDriver service
sudo systemctl daemon-reload
sudo systemctl enable chromedriver
sudo systemctl start chromedriver
sudo systemctl status chromedriver
```

### 5. Configure Permissions

```bash
# Give Chrome permission to run
sudo chmod +x /usr/bin/google-chrome

# Set SELinux to permissive (if needed)
sudo setenforce 0
```

## ✅ Test Dusk Installation

```bash
# Run Dusk example test
php artisan dusk

# You should see the test pass
```

## 🔧 Environment Variables

Add to your `.env`:

```bash
# Dusk Configuration
DUSK_DRIVER_URL=http://localhost:9515

# HikCentral Connect Credentials
HCC_USERNAME=your_email@example.com
HCC_PASSWORD=your_password
HCC_LOGIN_URL=https://www.hik-connect.com
HCC_ATTENDANCE_URL=https://www.hik-connect.com/attendance
```

## 🚀 Running the Scraper

```bash
# Sync devices
php artisan hcc:scrape:devices

# Sync attendance
php artisan hcc:scrape:attendance --from=2025-10-01 --to=2025-10-31

# Sync recent (last 10 minutes)
php artisan hcc:scrape:recent
```

## 📅 Setup Cron Job

```bash
crontab -e

# Add these lines:
*/5 * * * * cd /path/to/project && php artisan hcc:scrape:recent >> /var/log/hcc-scraper.log 2>&1
0 3 * * * cd /path/to/project && php artisan hcc:scrape:devices >> /var/log/hcc-scraper.log 2>&1
```

## 🐛 Troubleshooting

### Chrome crashes
```bash
# Increase shared memory
sudo mount -o remount,size=2G /dev/shm
```

### ChromeDriver not found
```bash
# Check ChromeDriver path
which chromedriver

# Restart service
sudo systemctl restart chromedriver
```

### Permission denied
```bash
# Fix permissions
sudo chown -R your_user:your_user /path/to/project
chmod -R 755 storage bootstrap/cache
```






