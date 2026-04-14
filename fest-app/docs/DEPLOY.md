# Guía de despliegue

## Requisitos del servidor

- PHP 8.3+ con extensiones: pdo_mysql, intl, openssl, zip
- MySQL 8 o MariaDB 11
- Node.js 20+ (solo para build del frontend)
- Nginx o Apache

## Backend

```bash
cd backend
composer install --no-dev --optimize-autoloader
APP_ENV=prod APP_SECRET=<secret> php bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=prod php bin/console cache:clear
php bin/console lexik:jwt:generate-keypair
```

Configurar en `.env.local`:

```
APP_ENV=prod
APP_SECRET=<random32chars>
DATABASE_URL="mysql://user:pass@localhost:3306/falles_prod"
JWT_PASSPHRASE=<passphrase>
CORS_ALLOW_ORIGIN='^https://tudominio\.com$'
```

## Frontend

```bash
cd frontend
npm ci
ng build --configuration production
# Copiar dist/ al webroot del servidor
```

## Nginx (ejemplo)

```nginx
server {
    listen 443 ssl;
    server_name api.tudominio.com;
    root /var/www/falles-app/backend/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }
}

server {
    listen 443 ssl;
    server_name app.tudominio.com;
    root /var/www/falles-app/frontend/dist/browser;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```
