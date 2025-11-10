# 🚀 HikCentral Connect Scraper Deployment Guide - AlmaLinux VPS

## 📋 System Requirements

- AlmaLinux 8 or 9
- PHP 8.0+
- Composer
- MySQL/MariaDB
- Google Chrome
- ChromeDriver

---

## 🔧 Step-by-Step Deployment

### 1. System Preparation

```bash
# Connect to your VPS
ssh user@your-vps-ip

# Update system
sudo dnf update -y

# Install EPEL repository
sudo dnf install -y epel-release

# Install required packages
sudo dnf install -y git unzip wget curl
```

### 2. Install PHP 8.x

```bash
# Add Remi repository
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm

# Enable PHP 8.1 module
sudo dnf module reset php -y
sudo dnf module install php:remi-8.1 -y

# Install PHP extensions
sudo dnf install -y php php-cli php-fpm php-mysql php-mbstring php-xml \
    php-gd php-zip php-curl php-json php-bcmath php-tokenizer

# Verify PHP version
php -v
```

### 3. Install Composer

```bash
cd ~
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Verify
composer --version
```

### 4. Install Google Chrome

```bash
# Download Google Chrome
wget https://dl.google.com/linux/direct/google-chrome-stable_current_x86_64.rpm

# Install Chrome
sudo dnf install -y ./google-chrome-stable_current_x86_64.rpm

# Install dependencies
sudo dnf install -y liberation-fonts xorg-x11-server-Xvfb gtk3 \
    dbus-glib nspr nss

# Verify installation
google-chrome --version
```

### 5. Install ChromeDriver

```bash
# Get Chrome version
CHROME_VERSION=$(google-chrome --version | awk '{print $3}' | cut -d'.' -f1)

# Download ChromeDriver
wget https://chromedriver.storage.googleapis.com/LATEST_RELEASE_${CHROME_VERSION} -O /tmp/chrome_version
CHROMEDRIVER_VERSION=$(cat /tmp/chrome_version)
wget https://chromedriver.storage.googleapis.com/${CHROMEDRIVER_VERSION}/chromedriver_linux64.zip

# Install ChromeDriver
unzip chromedriver_linux64.zip
sudo mv chromedriver /usr/local/bin/
sudo chmod +x /usr/local/bin/chromedriver

# Verify
chromedriver --version
```

### 6. Setup ChromeDriver as System Service

```bash
# Create service file
sudo tee /etc/systemd/system/chromedriver.service > /dev/null <<'EOF'
[Unit]
Description=ChromeDriver Service
After=network.target

[Service]
Type=simple
User=your_username
ExecStart=/usr/local/bin/chromedriver --port=9515 --whitelisted-ips=127.0.0.1
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

# Replace 'your_username' with actual user
sudo sed -i "s/your_username/$(whoami)/g" /etc/systemd/system/chromedriver.service

# Start ChromeDriver
sudo systemctl daemon-reload
sudo systemctl enable chromedriver
sudo systemctl start chromedriver
sudo systemctl status chromedriver
```

### 7. Deploy Laravel Application

```bash
# Clone your repository
cd /var/www
sudo git clone https://github.com/your-repo/ams-final.git
cd ams-final

# Set permissions
sudo chown -R $USER:$USER /var/www/ams-final
chmod -R 755 storage bootstrap/cache

# Install dependencies
composer install --optimize-autoloader --no-dev

# Copy and configure .env
cp .env.example .env
nano .env
```

### 8. Configure .env File

```bash
# Application
APP_NAME="Attendance Management System"
APP_ENV=production
APP_KEY=  # Will generate below
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# HikCentral Connect Scraper
HCC_USERNAME=your_hik_connect_email@example.com
HCC_PASSWORD=your_hik_connect_password
HCC_LOGIN_URL=https://www.hik-connect.com
HCC_TIMEZONE=Asia/Karachi
HCC_LOOKBACK_MINUTES=10
HCC_PAGE_SIZE=100

# Dusk
DUSK_DRIVER_URL=http://localhost:9515
```

### 9. Generate App Key and Run Migrations

```bash
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 10. Install Laravel Dusk

```bash
composer require --dev laravel/dusk
php artisan dusk:install

# Make sure ChromeDriver can write to temp directories
sudo mkdir -p /tmp/chromedriver
sudo chown -R $USER:$USER /tmp/chromedriver
```

### 11. Test Dusk Installation

```bash
# Test basic Dusk
php artisan dusk

# If successful, test HCC scraper
php artisan hcc:scrape:devices
```

### 12. Setup Cron Jobs

```bash
# Edit crontab
crontab -e

# Add these lines:
* * * * * cd /var/www/ams-final && php artisan schedule:run >> /dev/null 2>&1
```

### 13. Configure Firewall (if needed)

```bash
# Allow HTTP/HTTPS
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

### 14. Setup Nginx (Optional)

```bash
# Install Nginx
sudo dnf install -y nginx

# Configure Nginx
sudo nano /etc/nginx/conf.d/ams.conf
```

Add this configuration:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/ams-final/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
# Start Nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

---

## ✅ Verification

### Test Individual Commands

```bash
# Test device scraping
php artisan hcc:scrape:devices

# Test attendance scraping for today
php artisan hcc:scrape:attendance --from=2025-10-18 --to=2025-10-18

# Test recent scraping (last 10 minutes)
php artisan hcc:scrape:recent
```

### Check Logs

```bash
# View Laravel logs
tail -f storage/logs/laravel.log

# View scraper logs
tail -f storage/logs/hcc-scraper.log

# Check ChromeDriver service
sudo journalctl -u chromedriver -f
```

### Verify Scheduler

```bash
# Run scheduler manually
php artisan schedule:run

# Check if cron is working
grep CRON /var/log/cron
```

---

## 🔒 Security Hardening

### 1. Secure .env File

```bash
chmod 600 .env
```

### 2. Disable SELinux (if causing issues)

```bash
sudo setenforce 0
sudo sed -i 's/SELINUX=enforcing/SELINUX=permissive/g' /etc/selinux/config
```

### 3. Setup SSL with Let's Encrypt

```bash
sudo dnf install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

---

## 🐛 Troubleshooting

### Chrome Crashes

```bash
# Increase shared memory
sudo mount -o remount,size=2G /dev/shm

# Add to /etc/fstab for persistence
echo "tmpfs /dev/shm tmpfs defaults,size=2G 0 0" | sudo tee -a /etc/fstab
```

### ChromeDriver Connection Refused

```bash
# Check if service is running
sudo systemctl status chromedriver

# Restart service
sudo systemctl restart chromedriver

# Check port
netstat -tuln | grep 9515
```

### Permission Errors

```bash
# Fix Laravel permissions
sudo chown -R $USER:nginx storage
sudo chown -R $USER:nginx bootstrap/cache
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Dusk Times Out

```bash
# Increase timeouts in tests/DuskTestCase.php
# Connection and request timeouts are already set to 60 seconds

# Check if Chrome is accessible
google-chrome --headless --disable-gpu --dump-dom https://www.google.com
```

---

## 📊 Monitoring

### Setup Log Rotation

```bash
sudo tee /etc/logrotate.d/laravel > /dev/null <<'EOF'
/var/www/ams-final/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 0640 your_user nginx
    sharedscripts
}
EOF
```

### Monitor Disk Usage

```bash
# Check disk space
df -h

# Check Laravel storage
du -sh storage/logs/*
```

---

## 🎯 Production Checklist

- [ ] ChromeDriver service running
- [ ] Cron job configured
- [ ] .env file secured with credentials
- [ ] Database migrations run
- [ ] Logs rotating properly
- [ ] Firewall configured
- [ ] SSL certificate installed
- [ ] Backup strategy in place

---

## 🚀 Start Scraping!

Once everything is set up, the system will automatically:
- ✅ Scrape attendance every 5 minutes
- ✅ Sync devices daily at 3:05 AM
- ✅ Store all data in MySQL
- ✅ Log all activities

**View scraped data at:** `https://your-domain.com/admin/hcc/attendance`

🎉 Your HikCentral Connect scraper is now fully operational on AlmaLinux!







