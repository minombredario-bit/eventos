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

## Documentación

- [Requisitos funcionales completos](docs/REQUIREMENTS.md)
- [API endpoints](docs/API.md)
- [Guía de despliegue](docs/DEPLOY.md)
- [Skill backend (OpenCode)](/.claude/backend.md)
- [Skill frontend (OpenCode)](/.claude/frontend.md)
