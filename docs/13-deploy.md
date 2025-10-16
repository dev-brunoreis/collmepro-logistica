# Deploy e Producao

## Visao Geral

Este documento descreve o processo de deploy e configuracao do sistema em ambiente de producao.

## Requisitos de Servidor

### Hardware Recomendado

- **CPU**: 2+ cores
- **RAM**: 4GB minimo, 8GB recomendado
- **Disco**: 20GB SSD minimo
- **Rede**: 100Mbps minimo

### Software

- **Sistema Operacional**: Ubuntu 22.04 LTS (recomendado)
- **Servidor Web**: Nginx ou Apache
- **PHP**: 8.2+ com extensoes necessarias
- **MySQL**: 8.0+
- **Redis**: 6.0+ (recomendado para cache e filas)
- **Node.js**: 18+ (para build do frontend)
- **Supervisor**: Para gerenciar workers
- **SSL**: Certificado HTTPS

## Configuracao do Servidor

### Instalar Dependencias (Ubuntu)

```bash
# Atualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar PHP 8.2 e extensoes
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis \
    php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd \
    php8.2-bcmath php8.2-intl

# Instalar MySQL
sudo apt install -y mysql-server

# Instalar Redis
sudo apt install -y redis-server

# Instalar Nginx
sudo apt install -y nginx

# Instalar Supervisor
sudo apt install -y supervisor

# Instalar Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

### Configurar MySQL

```bash
sudo mysql_secure_installation
```

Criar banco de dados:

```sql
CREATE DATABASE collmepro_logistica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'collmepro'@'localhost' IDENTIFIED BY 'senha_segura';
GRANT ALL PRIVILEGES ON collmepro_logistica.* TO 'collmepro'@'localhost';
FLUSH PRIVILEGES;
```

### Configurar Redis

```bash
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

## Deploy da Aplicacao

### 1. Clonar Repositorio

```bash
cd /var/www
sudo git clone <repository-url> collmepro-logistica
cd collmepro-logistica
sudo chown -R www-data:www-data /var/www/collmepro-logistica
```

### 2. Instalar Dependencias

```bash
# Composer
composer install --optimize-autoloader --no-dev

# NPM
npm ci
```

### 3. Configurar Ambiente

```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env` com configuracoes de producao:

```env
APP_NAME="Collmepro Logistica"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://logistica.collmepro.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=collmepro_logistica
DB_USERNAME=collmepro
DB_PASSWORD=senha_segura

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Bling API
BLING_PRO_CLIENT_ID=your-client-id
BLING_PRO_CLIENT_SECRET=your-client-secret
BLING_ATACADISTA_CLIENT_ID=your-client-id
BLING_ATACADISTA_CLIENT_SECRET=your-client-secret

# Broadcasting
BROADCAST_DRIVER=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=logistica.collmepro.com
REVERB_PORT=443
REVERB_SCHEME=https
```

### 4. Build Frontend

```bash
npm run build
```

### 5. Executar Migrations

```bash
php artisan migrate --force
```

### 6. Otimizacoes

```bash
# Cache de configuracao
php artisan config:cache

# Cache de rotas
php artisan route:cache

# Cache de views
php artisan view:cache

# Otimizar autoloader
composer dump-autoload --optimize
```

### 7. Permissoes

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## Configuracao do Nginx

Criar arquivo `/etc/nginx/sites-available/collmepro-logistica`:

```nginx
server {
    listen 80;
    server_name logistica.collmepro.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name logistica.collmepro.com;
    
    root /var/www/collmepro-logistica/public;
    index index.php;
    
    # SSL
    ssl_certificate /etc/letsencrypt/live/logistica.collmepro.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/logistica.collmepro.com/privkey.pem;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    
    # Logs
    access_log /var/log/nginx/collmepro-access.log;
    error_log /var/log/nginx/collmepro-error.log;
    
    # Laravel
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # WebSocket (Reverb)
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
}
```

Ativar site:

```bash
sudo ln -s /etc/nginx/sites-available/collmepro-logistica /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## Configuracao do Supervisor

Criar arquivo `/etc/supervisor/conf.d/collmepro.conf`:

```ini
[program:collmepro-invoices]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/collmepro-logistica/artisan queue:work redis --queue=invoices --tries=3 --timeout=300 --max-jobs=1000 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/collmepro-logistica/storage/logs/worker-invoices.log
stopwaitsecs=3600

[program:collmepro-tracking]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/collmepro-logistica/artisan queue:work redis --queue=tracking --tries=3 --timeout=300 --max-jobs=1000 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/collmepro-logistica/storage/logs/worker-tracking.log
stopwaitsecs=3600

[program:collmepro-sellers]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/collmepro-logistica/artisan queue:work redis --queue=sellers --tries=2 --timeout=300 --max-jobs=1000 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/collmepro-logistica/storage/logs/worker-sellers.log
stopwaitsecs=3600

[program:collmepro-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/collmepro-logistica/artisan queue:work redis --tries=3 --timeout=300 --max-jobs=1000 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/collmepro-logistica/storage/logs/worker-default.log
stopwaitsecs=3600

[program:collmepro-reverb]
command=php /var/www/collmepro-logistica/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/collmepro-logistica/storage/logs/reverb.log
```

Atualizar Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

## Agendamento de Tarefas (Cron)

Adicionar ao crontab:

```bash
sudo crontab -e -u www-data
```

Adicionar:

```
* * * * * cd /var/www/collmepro-logistica && php artisan schedule:run >> /dev/null 2>&1
```

## SSL com Let's Encrypt

```bash
# Instalar certbot
sudo apt install -y certbot python3-certbot-nginx

# Obter certificado
sudo certbot --nginx -d logistica.collmepro.com

# Auto-renewal
sudo certbot renew --dry-run
```

## Monitoramento

### Logs

```bash
# Logs do Laravel
tail -f /var/www/collmepro-logistica/storage/logs/laravel.log

# Logs do Bling Sync
tail -f /var/www/collmepro-logistica/storage/logs/bling-sync.log

# Logs do Nginx
tail -f /var/log/nginx/collmepro-error.log

# Logs dos workers
tail -f /var/www/collmepro-logistica/storage/logs/worker-*.log
```

### Verificar Status dos Workers

```bash
sudo supervisorctl status
```

### Reiniciar Workers

```bash
sudo supervisorctl restart all
```

## Backup e Recuperacao

### Backup Automatico Diario

Criar script `/usr/local/bin/backup-collmepro.sh`:

```bash
#!/bin/bash

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/collmepro"

# Criar diretorio
mkdir -p $BACKUP_DIR

# Backup do banco de dados
mysqldump -u collmepro -p'senha_segura' collmepro_logistica | gzip > $BACKUP_DIR/database_$DATE.sql.gz

# Backup de arquivos
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/collmepro-logistica/storage

# Remover backups com mais de 7 dias
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
```

Tornar executavel:

```bash
sudo chmod +x /usr/local/bin/backup-collmepro.sh
```

Adicionar ao cron (diario as 2h):

```bash
0 2 * * * /usr/local/bin/backup-collmepro.sh >> /var/log/backup-collmepro.log 2>&1
```

### Restauracao

```bash
# Restaurar banco de dados
gunzip < database_backup.sql.gz | mysql -u collmepro -p collmepro_logistica

# Restaurar arquivos
tar -xzf files_backup.tar.gz -C /
```

## Atualizacao da Aplicacao

### Script de Deploy

Criar `/var/www/deploy.sh`:

```bash
#!/bin/bash

cd /var/www/collmepro-logistica

# Ativar modo de manutencao
php artisan down

# Atualizar codigo
git pull origin main

# Instalar dependencias
composer install --optimize-autoloader --no-dev
npm ci
npm run build

# Executar migrations
php artisan migrate --force

# Limpar e recriar caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Reiniciar workers
sudo supervisorctl restart all

# Desativar modo de manutencao
php artisan up

echo "Deploy completed!"
```

Executar:

```bash
sudo chmod +x /var/www/deploy.sh
sudo /var/www/deploy.sh
```

## Performance e Otimizacao

### OPcache

Configurar `/etc/php/8.2/fpm/php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

### PHP-FPM

Configurar `/etc/php/8.2/fpm/pool.d/www.conf`:

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

### Redis

Configurar `/etc/redis/redis.conf`:

```conf
maxmemory 512mb
maxmemory-policy allkeys-lru
```

## Seguranca

### Firewall

```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### Fail2Ban

```bash
sudo apt install -y fail2ban

# Configurar para proteger SSH e Nginx
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### Permissoes de Arquivos

```bash
# Apenas www-data pode escrever
sudo chown -R www-data:www-data /var/www/collmepro-logistica
sudo find /var/www/collmepro-logistica -type f -exec chmod 644 {} \;
sudo find /var/www/collmepro-logistica -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/collmepro-logistica/storage
sudo chmod -R 775 /var/www/collmepro-logistica/bootstrap/cache
```

## Troubleshooting

### Erro 500

Verificar logs:
```bash
tail -f storage/logs/laravel.log
tail -f /var/log/nginx/collmepro-error.log
```

### Workers Nao Funcionam

```bash
sudo supervisorctl status
sudo supervisorctl restart all
```

### Banco de Dados Lento

```sql
SHOW PROCESSLIST;
EXPLAIN SELECT * FROM order_summary WHERE ...;
```

### Alto Uso de Memoria

```bash
top
sudo service php8.2-fpm restart
```

