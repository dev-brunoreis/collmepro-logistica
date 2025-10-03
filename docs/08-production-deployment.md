# Production Deployment Guide

## 🚀 Complete Production Setup

This guide covers everything needed to deploy the Collmepro Logistics System to production with automatic order import.

## 📋 Prerequisites

- Linux server (Ubuntu/CentOS recommended)
- PHP 8.4+ with required extensions
- MySQL 8.0+
- Composer
- Node.js & NPM
- Supervisor (for queue workers)
- Cron service

## 🔧 Step-by-Step Deployment

### 1. Server Setup

#### Install Required Packages
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.4 and extensions
sudo apt install php8.4-fpm php8.4-mysql php8.4-xml php8.4-mbstring php8.4-curl php8.4-zip php8.4-bcmath php8.4-gd

# Install other requirements
sudo apt install nginx mysql-server composer nodejs npm supervisor
```

#### Create Application User
```bash
sudo adduser --system --group --home /var/www/collmepro-logistica collmepro
sudo usermod -a -G www-data collmepro
```

### 2. Application Deployment

#### Clone and Setup

# Install dependencies
sudo -u collmepro composer install --optimize-autoloader --no-dev
sudo -u collmepro npm install
sudo -u collmepro npm run build
```

#### Environment Configuration
```bash
sudo -u collmepro cp .env.example .env
sudo -u collmepro php artisan key:generate
```

Edit `.env` for production:
```env
APP_NAME="Collmepro Logistics"
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=collmepro_logistica
DB_USERNAME=collmepro_user
DB_PASSWORD=secure_password

QUEUE_CONNECTION=database

# WooCommerce API Credentials
WOOCOMMERCE_COLLMEPRO_CONSUMER_KEY=ck_your_key_here
WOOCOMMERCE_COLLMEPRO_CONSUMER_SECRET=cs_your_secret_here
WOOCOMMERCE_COLLMEPRO_WHOLESALE_CONSUMER_KEY=ck_your_wholesale_key_here
WOOCOMMERCE_COLLMEPRO_WHOLESALE_CONSUMER_SECRET=cs_your_wholesale_secret_here

# Shopify API Credentials (for future use)
SHOPIFY_COLLME_BEAUTY_SHOP_DOMAIN=your-shop.myshopify.com
SHOPIFY_COLLME_BEAUTY_ACCESS_TOKEN=shpat_xxxxxxxxxxxxxxxx

# Bling API (for future use)
BLING_API_KEY=your_bling_api_key

# Other APIs (for future use)
CORREIOS_API_KEY=your_correios_key
TOTAL_EXPRESS_API_KEY=your_total_express_key
MELHOR_ENVIO_API_KEY=your_melhor_envio_key
```

### 3. Database Setup

#### Create Database and User
```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE collmepro_logistica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'collmepro_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON collmepro_logistica.* TO 'collmepro_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### Run Migrations
```bash
cd /var/www/collmepro-logistica
sudo -u collmepro php artisan migrate --force
```

### 4. Web Server Configuration

#### Nginx Configuration
Create `/etc/nginx/sites-available/collmepro-logistica`:
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    root /var/www/collmepro-logistica/public;

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
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Enable Site
```bash
sudo ln -s /etc/nginx/sites-available/collmepro-logistica /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 5. Queue Worker Setup

#### Supervisor Configuration
Create `/etc/supervisor/conf.d/laravel-worker.conf`:
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/collmepro-logistica/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=collmepro
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/collmepro-logistica/storage/logs/worker.log
stopwaitsecs=3600
```

#### Start Supervisor
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### 6. Cron Job Setup

#### Add Cron Job
```bash
sudo crontab -e
```

Add this line:
```bash
* * * * * cd /var/www/collmepro-logistica && php artisan schedule:run >> /dev/null 2>&1
```

#### Verify Cron
```bash
sudo systemctl status cron
sudo tail -f /var/log/cron
```

### 7. SSL Certificate (Optional but Recommended)

#### Using Certbot
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

## 🔍 Verification Steps

### 1. Test Application
```bash
# Check if application is running
curl -I https://your-domain.com

# Test database connection
cd /var/www/collmepro-logistica
sudo -u collmepro php artisan tinker --execute="echo 'DB Connection: ' . (DB::connection()->getPdo() ? 'OK' : 'FAILED');"
```

### 2. Test Scheduler
```bash
# List scheduled tasks
cd /var/www/collmepro-logistica
sudo -u collmepro php artisan schedule:list

# Run scheduler manually
sudo -u collmepro php artisan schedule:run
```

### 3. Test Queue Worker
```bash
# Check supervisor status
sudo supervisorctl status

# Check queue worker logs
sudo tail -f /var/www/collmepro-logistica/storage/logs/worker.log
```

### 4. Test Manual Import
```bash
# Test manual import
cd /var/www/collmepro-logistica
sudo -u collmepro php artisan orders:import
```

## 📊 Monitoring Setup

### 1. Log Rotation
Create `/etc/logrotate.d/collmepro-logistica`:
```
/var/www/collmepro-logistica/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 644 collmepro www-data
    postrotate
        sudo supervisorctl restart laravel-worker:*
    endscript
}
```

### 2. Health Check Script
Create `/var/www/collmepro-logistica/health-check.sh`:
```bash
#!/bin/bash

# Check if application is responding
if ! curl -f -s https://your-domain.com > /dev/null; then
    echo "Application is down!"
    exit 1
fi

# Check if queue worker is running
if ! supervisorctl status laravel-worker:* | grep -q "RUNNING"; then
    echo "Queue worker is down!"
    exit 1
fi

# Check if cron is running
if ! systemctl is-active --quiet cron; then
    echo "Cron service is down!"
    exit 1
fi

echo "All systems operational"
exit 0
```

Make it executable:
```bash
sudo chmod +x /var/www/collmepro-logistica/health-check.sh
```

## 🚨 Troubleshooting

### Common Issues

#### 1. Scheduler Not Running
```bash
# Check cron service
sudo systemctl status cron

# Check cron logs
sudo tail -f /var/log/cron

# Test scheduler manually
cd /var/www/collmepro-logistica
sudo -u collmepro php artisan schedule:run -v
```

#### 2. Queue Worker Issues
```bash
# Check supervisor status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart laravel-worker:*

# Check worker logs
sudo tail -f /var/www/collmepro-logistica/storage/logs/worker.log
```

#### 3. Permission Issues
```bash
# Fix ownership
sudo chown -R collmepro:www-data /var/www/collmepro-logistica
sudo chmod -R 755 /var/www/collmepro-logistica
sudo chmod -R 775 /var/www/collmepro-logistica/storage
sudo chmod -R 775 /var/www/collmepro-logistica/bootstrap/cache
```

#### 4. API Connection Issues
```bash
# Test API connections
cd /var/www/collmepro-logistica
sudo -u collmepro php artisan tinker --execute="
\$service = app(App\Services\WooCommerceService::class);
echo 'Collmepro: ' . (\$service->testConnection('collmepro') ? 'OK' : 'FAILED') . PHP_EOL;
echo 'Atacadista: ' . (\$service->testConnection('collmepro_atacadista') ? 'OK' : 'FAILED') . PHP_EOL;
"
```

## 📈 Performance Optimization

### 1. PHP-FPM Optimization
Edit `/etc/php/8.4/fpm/pool.d/www.conf`:
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 1000
```

### 2. MySQL Optimization
Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:
```ini
[mysqld]
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
query_cache_size = 32M
query_cache_type = 1
```

### 3. Application Optimization
```bash
# Optimize autoloader
cd /var/www/collmepro-logistica
sudo -u collmepro composer dump-autoload --optimize

# Cache configuration
sudo -u collmepro php artisan config:cache
sudo -u collmepro php artisan route:cache
sudo -u collmepro php artisan view:cache
```

## 🔄 Maintenance

### Daily Tasks
- Monitor logs for errors
- Check queue worker status
- Verify scheduler execution

### Weekly Tasks
- Review failed jobs: `php artisan queue:failed`
- Check disk space
- Update dependencies if needed

### Monthly Tasks
- Review and rotate logs
- Update system packages
- Backup database

## 📞 Support

For issues or questions:
1. Check logs in `/var/www/collmepro-logistica/storage/logs/`
2. Verify all services are running
3. Test manual commands
4. Check system resources (CPU, memory, disk)

This setup ensures your Collmepro Logistics System runs reliably in production with automatic order import every 10 minutes.
