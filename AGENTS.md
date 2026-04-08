# CLAUDE.md — Entidades Festivas App

Este archivo describe el proyecto y cómo trabajar en él con Claude Code / OpenCode.

## Descripción del proyecto

Aplicación PWA para gestión de comidas y eventos de entidades festivas y sociales. Stack: **Symfony 7 + API Platform 4** en backend, **Angular 18 + PWA** en frontend.

## Estructura del repositorio

```
entidades-app/
├── backend/          → API REST (Symfony)
├── frontend/         → App Angular PWA
├── docs/             → Documentación
│   └── REQUIREMENTS.md  → Requisitos completos
└── .claude/
    ├── CLAUDE.md     → Este archivo
    ├── backend.md    → Skill detallado del backend
    └── frontend.md   → Skill detallado del frontend
```

## Cómo usar las skills

Cuando trabajes en el backend, lee siempre `.claude/backend.md` antes de generar código.
Cuando trabajes en el frontend, lee siempre `.claude/frontend.md` antes de generar código.

Los requisitos funcionales completos están en `docs/REQUIREMENTS.md`. Consúltalo ante cualquier duda sobre reglas de negocio, entidades o flujos.

## Comandos habituales

### Backend

```bash
cd backend
composer install
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load --no-interaction
php bin/console lexik:jwt:generate-keypair
symfony server:start --no-tls
```

### Frontend

```bash
cd frontend
npm install
ng serve
ng build --configuration production
```

## Variables de entorno mínimas (backend/.env.local)

```
DATABASE_URL="mysql://user:pass@127.0.0.1:3306/entidades_app"
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=changeme
```

## Convenciones generales

- nombres de clase en PascalCase, propiedades en camelCase
- endpoints en snake_case siguiendo API Platform
- todos los enums son PHP 8.3 backed enums en `src/Enum/`
- los tests van en `backend/tests/` con PHPUnit
- los mensajes de commit en inglés, imperativo: `Add entidad censo import endpoint`
