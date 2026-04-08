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

### Primer arranque en Windows (recomendado)

Desde la carpeta `falles-app/` puedes dejar el proyecto listo en un equipo nuevo con un único script:

```powershell
Set-Location C:\Users\te0162\PhpstormProjects\eventos\falles-app
powershell -ExecutionPolicy Bypass -File .\scripts\dev-setup.ps1
```

Ese script deja preparado lo siguiente:

- contenedores Docker (`php`, `nginx`, `db`)
- permisos de `backend/var`
- dependencias PHP (`composer install`)
- migraciones Doctrine
- claves JWT (solo si faltan)
- usuarios demo de acceso
- dependencias del frontend (`npm install`)
- build del frontend como verificación rápida

> Importante: usa siempre los scripts desde `falles-app/`. Están fijados al `docker-compose.yml` de esa carpeta y evitan levantar el `compose.yaml` de `backend/` (Postgres legacy).

Después, para arrancar backend + frontend en desarrollo:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev-start.ps1
```

Y para revisar que todo sigue correcto:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev-verify.ps1
```

Credenciales demo creadas por el setup:

- `ana.gonzalez@example.com` / `password`
- `luis.martinez@example.com` / `password`
- `sofia.perez@example.com` / `password`

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

> Si ya has ejecutado `scripts/dev-setup.ps1`, normalmente solo necesitarás `scripts/dev-start.ps1`.

## Documentación

- [Requisitos funcionales completos](docs/REQUIREMENTS.md)
- [API endpoints](docs/API.md)
- [Guía de despliegue](docs/DEPLOY.md)
- [Skill backend (OpenCode)](/.claude/backend.md)
- [Skill frontend (OpenCode)](/.claude/frontend.md)
