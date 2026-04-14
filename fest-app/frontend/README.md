# Frontend

This project was generated with [Angular CLI](https://github.com/angular/angular-cli) version 18.2.21.

## Development server

Run the development server with the npm scripts defined in `package.json`.

If this is the first time you open the project on a new machine, prepare everything first from `falles-app/`:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev-setup.ps1
```

To start backend + frontend afterwards with one command:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev-start.ps1
```

- `npm start` or `npm run start:local`
  - Starts the dev server on `http://localhost:4250/`.
  - Recommended for local development (no security warning).

- `npm run start:net`
  - Starts the dev server bound to `0.0.0.0:4250` (accessible from other machines on the LAN).
  - This will display the dev-server security warning about binding to an open connection —
	this is expected when exposing the server on all interfaces.

The application will automatically reload if you change any of the source files.

## Code scaffolding

Run `ng generate component component-name` to generate a new component. You can also use `ng generate directive|pipe|service|class|guard|interface|enum|module`.

## Build

Run `ng build` to build the project. The build artifacts will be stored in the `dist/` directory.

## Running unit tests

Run `ng test` to execute the unit tests via [Karma](https://karma-runner.github.io).

## Running end-to-end tests

Run `ng e2e` to execute the end-to-end tests via a platform of your choice. To use this command, you need to first add a package that implements end-to-end testing capabilities.

## Further help

To get more help on the Angular CLI use `ng help` or go check out the [Angular CLI Overview and Command Reference](https://angular.dev/tools/cli) page.

---

Quick tips:

- If you only need local access, prefer `start`/`start:local`.
- To expose the dev server to the LAN without the general warning, run with `--host <YOUR_IP>` (replace `<YOUR_IP>` with your machine IP) instead of `0.0.0.0`.
- When running inside Docker, keep `--host 0.0.0.0` in the container and map ports on the host; the warning is normal in that case.
- After cloning on a new machine, use `..\scripts\dev-setup.ps1` once to install backend/frontend dependencies and prepare demo users.

## Troubleshooting

- Warning about binding to 0.0.0.0: if you run `npm run start:net` the dev-server will print a security warning. This is expected — it only means the server is reachable from other hosts. Use `start`/`start:local` for local-only development.

- Puerto en uso: si `ng serve` falla porque el puerto está ocupado, comprueba qué proceso lo está usando y cambia el puerto con `--port <PUERTO>` o con `npm run start -- --port <PUERTO>`.

- CORS y API: durante desarrollo es habitual ejecutar frontend y backend por separado. Si el navegador bloquea peticiones al API, crea un `proxy.conf.json` en el frontend y arranca con `ng serve --proxy-config proxy.conf.json` o habilita CORS temporalmente en el backend.

- Docker: permisos de `var/` (Windows <-> Linux): si ves errores de permisos en el contenedor PHP al escribir en `var/`, ejecuta dentro del contenedor el script provisto:

```bash
docker compose exec --user root php bash -lc "/var/www/html/scripts/fix_var_permissions.sh"
```

- Primer arranque en equipo nuevo: si aún no tienes `node_modules`, dependencias PHP o usuarios demo, ejecuta desde `falles-app/`:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev-setup.ps1
```

- Build y despliegue en nginx: para servir la versión de producción desde Nginx via Docker Compose, ejecuta:

```powershell
cd falles-app/frontend
npm run build -- --configuration production
cp -r dist/* ../backend/public/
docker compose restart nginx
```


