# CLAUDE.md — Falles App

Este archivo es el punto de entrada para Claude Code / OpenCode.
**Léelo completo antes de escribir cualquier línea de código.**

---

## ⚠️ Reglas de comportamiento — OBLIGATORIAS

Estas reglas se aplican siempre, sin excepción, independientemente de la tarea.

### Lo que NUNCA debes hacer sin que se pida explícitamente

- **No toques archivos fuera del alcance de la tarea.** Si la tarea menciona un componente, no edites el servicio, el modelo ni el routing a menos que sea estrictamente necesario para que la tarea funcione.
- **No reformatees código existente.** No cambies indentación, orden de imports, espacios ni saltos de línea en líneas que no modificas.
- **No renombres nada.** Variables, clases, métodos, archivos, rutas — si ya tienen nombre, ese nombre se queda.
- **No refactorices código que funciona.** Tu trabajo es implementar lo pedido, no mejorar lo que ya existe.
- **No añadas dependencias** (npm, composer) sin confirmación explícita.
- **No toques ficheros de configuración** sin que se indique:
  - Frontend: `angular.json`, `tsconfig.json`, `package.json`, `ngsw-config.json`, `environments/`
  - Backend: `composer.json`, `symfony.lock`, `config/`, `security.yaml`, `services.yaml`
- **No generes ni modifiques migraciones** a menos que se pida expresamente.
- **No hagas commits ni stages** de archivos ajenos a la tarea.

### Lo que SÍ debes hacer siempre

- **Antes de empezar**, declara exactamente qué archivos vas a modificar y por qué.
- **Si la tarea es ambigua**, haz UNA sola pregunta de clarificación antes de proceder.
- **Al terminar**, lista solo los archivos que realmente cambiaste.
- **Si necesitas tocar un archivo no mencionado**, para y pregunta primero.
- **Haz el cambio mínimo** que cumpla el objetivo. Menos es más.

---

## Descripción del proyecto

Aplicación PWA para gestión de comidas y eventos de fallas valencianas.

- **Backend**: Symfony 7.3 + API Platform 4.2.6 (JSON-LD / Hydra)
- **Frontend**: Angular 18 + Angular Material 18 + PWA

---

## Estructura del repositorio

```
falles-app/
├── backend/          → API REST (Symfony)
├── frontend/         → App Angular PWA
├── docs/
│   └── REQUIREMENTS.md  → Requisitos funcionales completos
└── .claude/
    ├── CLAUDE.md     → Este archivo (entry point)
    ├── backend.md    → Skill detallado del backend
    └── frontend.md   → Skill detallado del frontend
```

## Cómo usar las skills

- Tarea de **backend** → lee `.claude/backend.md` completo antes de generar código.
- Tarea de **frontend** → lee `.claude/frontend.md` completo antes de generar código.
- Tarea de **generación de código Angular** (componentes, servicios, modelos, guards, pipes, helpers)
  → lee `.claude/angular-generator.md` antes de generar nada.
- Tarea de **generación de código Symfony** (entidades, servicios, processors, voters, tests, fixtures)
  → lee `.claude/symfony-generator.md` antes de generar nada.
- Dudas sobre **reglas de negocio** → consulta `docs/REQUIREMENTS.md`.

---

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

---

## Variables de entorno mínimas (backend/.env.local)

```
DATABASE_URL="mysql://user:pass@127.0.0.1:3306/falles_app"
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=changeme
```

---

## Convenciones generales

- Clases en PascalCase, propiedades en camelCase
- Endpoints REST en snake_case siguiendo API Platform
- Todos los enums son PHP 8.1 backed enums en `src/Enum/`
- Tests en `backend/tests/` con PHPUnit 11
- Mensajes de commit en inglés, imperativo: `Add censo import endpoint`
