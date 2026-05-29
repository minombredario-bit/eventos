# Festapp

Aplicación móvil multiplataforma (PWA) para gestión de comidas, inscripciones y pagos de entidades festivas: fallas, comparsas de moros y cristianos, peñas, hermandades, clubs y asociaciones.

## Stack tecnológico

| Capa | Tecnología |
|---|---|
| Frontend | Angular 18 + PWA |
| Backend | Symfony 7 + API Platform 3 |
| Autenticación | JWT (LexikJWTAuthenticationBundle) |
| Base de datos | MySQL 8 / MariaDB 11 |
| Servidor | PHP 8.3, Nginx |
| Exportes | PhpSpreadsheet (Excel), DomPDF (PDF) |

## Estructura del repositorio

```
falles-app/
├── backend/                  # Symfony API
│   ├── src/
│   │   ├── Entity/
│   │   ├── Repository/
│   │   ├── Service/
│   │   ├── Controller/
│   │   ├── Enum/
│   │   ├── EventSubscriber/
│   │   └── DataFixtures/
│   ├── config/
│   ├── migrations/
│   └── tests/
├── frontend/                 # Angular PWA
│   ├── src/
│   │   ├── app/
│   │   │   ├── auth/
│   │   │   ├── inicio/
│   │   │   ├── eventos/
│   │   │   ├── familia/
│   │   │   ├── inscripciones/
│   │   │   ├── perfil/
│   │   │   └── admin/
│   │   ├── environments/
│   │   └── assets/
│   └── angular.json
├── docs/                     # Documentación
│   ├── REQUIREMENTS.md
│   ├── API.md
│   └── DEPLOY.md
└── .claude/                  # Skills para OpenCode / Claude Code
    ├── CLAUDE.md
    ├── backend.md
    └── frontend.md
```

## Roles del sistema

| Rol | Descripción |
|---|---|
| `ROLE_SUPERADMIN` | Gestión global: crea entidades, sube censos, genera códigos de registro |
| `ROLE_ADMIN_ENTIDAD` | Gestión de su entidad: eventos, menús, inscripciones, pagos, usuarios |
| `ROLE_USER` | Usuario final: inscripciones, familia, pagos propios |

## Arranque rápido

### Primer arranque recomendado en Windows

Si acabas de clonar el repositorio en un equipo nuevo, no hace falta lanzar todos los comandos manualmente uno a uno. Desde `falles-app/` puedes preparar el entorno completo con:

```powershell
Set-Location C:\Users\te0162\PhpstormProjects\eventos\falles-app
powershell -ExecutionPolicy Bypass -File .\scripts\dev-setup.ps1
```

Luego, para trabajar en local:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev-start.ps1
```

Y para verificar la instalación:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev-verify.ps1
```

Usuarios demo creados automáticamente:

- `ana.gonzalez@example.com` / `password`
- `luis.martinez@example.com` / `password`
- `sofia.perez@example.com` / `password`

> Importante: ejecuta siempre estos scripts desde `falles-app/`. Queda fijado el `docker-compose.yml` de esa ruta para no levantar el `compose.yaml` de `falles-app/backend/`.

### Backend

```bash
cd backend
composer install
cp .env .env.local          # configurar DB_URL y JWT_SECRET
php bin/console doctrine:migrations:migrate
php bin/console lexik:jwt:generate-keypair
symfony server:start
```

### Frontend

```bash
cd frontend
npm install
ng serve
```

### Ejecutar con Docker Compose (backend + db + nginx)

El repositorio incluye un `docker-compose.yml` en `falles-app/` que arranca los servicios del backend (PHP/FPM), Nginx y MySQL.

Para un onboarding nuevo en Windows, prioriza `scripts/dev-setup.ps1` y deja estos comandos manuales como referencia avanzada o troubleshooting.

Comandos básicos (PowerShell):

```powershell
cd C:\Users\te0162\PhpstormProjects\eventos\falles-app
# Levantar servicios en segundo plano
docker compose up -d

# Ver logs
docker compose logs -f

# Ejecutar migraciones / generar claves JWT dentro del contenedor PHP
docker compose exec php bash -lc "composer install && php bin/console doctrine:migrations:migrate && php bin/console lexik:jwt:generate-keypair"

# (Opcional) Cargar fixtures/usuarios de desarrollo
docker compose exec php bash -lc "php bin/console doctrine:fixtures:load --no-interaction || true"

# Parar y eliminar contenedores
docker compose down
```

Notas:
- El servicio `nginx` queda expuesto en el host en el puerto `8080` (configurable en `docker-compose.yml`).
- El servicio `php` expone el puerto `9000` internamente para FPM; Nginx está configurado para conectarse a él.
- Para servir el frontend desde Nginx en producción: compila el frontend (`ng build --configuration production`) y copia `dist/` al directorio público del backend (por ejemplo `backend/public/`), luego recarga el contenedor Nginx.


## Documentación

- [Requisitos funcionales completos](docs/REQUIREMENTS.md)
- [API endpoints](docs/API.md)
- [Guía de despliegue](docs/DEPLOY.md)
- [Skill backend (OpenCode)](/.claude/backend.md)
- [Skill frontend (OpenCode)](/.claude/frontend.md)
