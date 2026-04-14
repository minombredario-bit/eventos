# FestApp – Infraestructura Docker

## Arquitectura

```
┌─────────────────────────────────────────────────────────┐
│  Tu máquina local                                        │
│                                                          │
│  Cliente FTP (FileZilla / WinSCP / VS Code Extension)   │
│       │                                                  │
│       │  FTP :21  (pasivo 30000-30009)                   │
└───────┼─────────────────────────────────────────────────┘
        │
        ▼
┌───────────────────────────────────────────────────────────┐
│  Docker                                                    │
│                                                            │
│  ┌──────────────┐    FastCGI    ┌──────────────────────┐  │
│  │  festapp-    │◄─────────────►│  festapp-php         │  │
│  │  nginx       │  :9000        │  (PHP-FPM 8.2)       │  │
│  │  :80 / :443  │               │                      │  │
│  └──────────────┘               └──────────────────────┘  │
│         │                               │                  │
│         └──────────┐   ┌───────────────┘                  │
│                    ▼   ▼                                   │
│              ┌──────────────┐                              │
│              │  app_data    │ ◄── festapp-ftp sube aquí   │
│              │  (volumen)   │                              │
│              └──────────────┘                              │
│                                                            │
│  ┌──────────────┐                                          │
│  │  festapp-db  │  MySQL 8.0  (red interna, sin puerto     │
│  │              │  expuesto en prod)                       │
│  └──────────────┘                                          │
└───────────────────────────────────────────────────────────┘
```

## Requisitos

- Docker Desktop ≥ 4.x  /  Docker Engine + Compose v2
- Cliente FTP: FileZilla, WinSCP o extensión VS Code *SFTP/FTP*

---

## Desarrollo

### 1. Preparar variables de entorno

```bash
cp .env.dev backend/.env
```

### 2. Levantar contenedores

```bash
make dev-up
# o:
docker compose -f docker-compose.dev.yml up -d --build
```

### 3. Subir el código por FTP

Configura tu cliente FTP con:

| Campo       | Valor              |
|-------------|--------------------|
| Host        | `localhost`        |
| Puerto      | `21`               |
| Usuario     | `ftpuser`          |
| Contraseña  | `ftppass_dev`      |
| Protocolo   | FTP explícito TLS  |
| Modo        | Pasivo             |
| Dir. remoto | `/var/www/html`    |

> **FileZilla:** Archivo → Gestor de sitios → Nuevo sitio → Protocolo: FTP, Cifrado: TLS explícito

Sube todo el contenido de `backend/` al directorio remoto `/var/www/html`.

### 4. Instalar dependencias y migrar

```bash
make dev-shell
# dentro del contenedor:
composer install
php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:migrations:migrate
```

O con make:
```bash
make jwt-keys
make migrate
```

### 5. Acceder

- **API Symfony:** http://localhost:8080
- **API Platform UI:** http://localhost:8080/api
- **Adminer** (opcional): `make dev-tools` → http://localhost:8081

---

## Producción

### 1. Preparar secretos

```bash
mkdir -p secrets
echo "root_password_muy_segura" > secrets/mysql_root_pass.txt
echo "user_password_muy_segura" > secrets/mysql_pass.txt
chmod 600 secrets/*.txt
```

### 2. Preparar .env.prod

```bash
cp .env.prod.example .env.prod
# Editar .env.prod con valores reales
```

### 3. Certificados TLS

```bash
mkdir -p docker/nginx/ssl
# Coloca aquí fullchain.pem y privkey.pem (Let's Encrypt, etc.)
```

### 4. Editar nginx para tu dominio

Cambia `tudominio.com` en `docker/nginx/default.prod.conf`.

### 5. Levantar

```bash
make prod-up
```

### 6. Desplegar código

Configura tu cliente FTP con las credenciales de `FTP_USER_NAME` / `FTP_USER_PASS` de `.env.prod` y sube el contenido de `backend/`.

Tras el deploy:
```bash
make prod-shell
php bin/console cache:clear --env=prod
php bin/console doctrine:migrations:migrate --no-interaction
```

---

## Estructura del proyecto

```
falles-app/
├── docker-compose.dev.yml
├── docker-compose.prod.yml
├── .env.dev                    ← variables desarrollo (sin secretos)
├── .env.prod.example           ← plantilla producción
├── .gitignore
├── Makefile
├── secrets/                    ← NO en git
│   ├── mysql_root_pass.txt
│   └── mysql_pass.txt
├── docker/
│   ├── php/
│   │   ├── Dockerfile.dev
│   │   ├── Dockerfile.prod
│   │   ├── entrypoint.dev.sh
│   │   ├── entrypoint.prod.sh
│   │   ├── php.ini             ← dev
│   │   ├── php.prod.ini        ← prod
│   │   └── conf.d/
│   │       ├── xdebug.ini
│   │       └── opcache.prod.ini
│   ├── nginx/
│   │   ├── default.dev.conf
│   │   ├── default.prod.conf
│   │   └── ssl/                ← NO en git
│   ├── ftp/
│   │   └── ssl/                ← cert para FTP-TLS (NO en git)
│   └── mysql/
│       └── init.sql
└── backend/                    ← código Symfony (se sube por FTP)
    ├── composer.json
    ├── composer.lock
    ├── ...
```

---

## Comandos útiles

```bash
make help           # lista todos los comandos
make dev-up         # arrancar dev
make dev-down       # parar dev
make dev-logs       # ver logs
make dev-shell      # entrar al contenedor PHP
make ftp-info       # recordatorio de credenciales FTP dev
make migrate        # ejecutar migraciones (dev)
make cache-clear    # limpiar caché Symfony (dev)
make jwt-keys       # regenerar claves JWT (dev)
```

---

## Notas de seguridad

- En producción el puerto 3306 de MySQL **no está expuesto** al host.
- Nginx y PHP-FPM corren en **contenedores separados** — si nginx es comprometido no tiene acceso al código PHP ni a PHP-FPM directamente más allá del socket FastCGI.
- Los secretos de MySQL se gestionan con **Docker Secrets**, no como variables de entorno.
- `.env.prod` y `secrets/` están en `.gitignore`.
