# ────────────────────────────────────────────────
#  Makefile  –  Comandos principales de FestApp
# ────────────────────────────────────────────────

COMPOSE_DEV  = docker compose -f docker-compose.dev.yml
COMPOSE_PROD = docker compose -f docker-compose.prod.yml

.PHONY: help dev-up dev-down dev-logs dev-shell \
        prod-up prod-down prod-logs prod-shell \
        ftp-info migrate cache-clear jwt-keys fix-perms

help: ## Muestra esta ayuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
	  awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ── Desarrollo ───────────────────────────────────

dev-up: ## Arranca el entorno de desarrollo
	$(COMPOSE_DEV) up -d --build

dev-down: ## Para el entorno de desarrollo
	$(COMPOSE_DEV) down

dev-logs: ## Logs en tiempo real (dev)
	$(COMPOSE_DEV) logs -f

dev-shell: ## Shell en el contenedor PHP (dev)
	$(COMPOSE_DEV) exec php sh

dev-tools: ## Arranca herramientas opcionales (adminer, mailpit)
	$(COMPOSE_DEV) --profile tools up -d

# ── Producción ───────────────────────────────────

prod-up: ## Arranca el entorno de producción
	$(COMPOSE_PROD) up -d --build

prod-down: ## Para el entorno de producción
	$(COMPOSE_PROD) down

prod-logs: ## Logs en tiempo real (prod)
	$(COMPOSE_PROD) logs -f

prod-shell: ## Shell en el contenedor PHP (prod)
	$(COMPOSE_PROD) exec php sh

# ── Symfony ──────────────────────────────────────

migrate: ## Ejecuta migraciones Doctrine (dev)
	$(COMPOSE_DEV) exec --user www-data php php bin/console doctrine:migrations:migrate --no-interaction

migrate-prod: ## Ejecuta migraciones Doctrine (prod)
	$(COMPOSE_PROD) exec --user www-data php php bin/console doctrine:migrations:migrate --no-interaction

cache-clear: ## Limpia la caché de Symfony (dev)
	$(COMPOSE_DEV) exec --user www-data php php bin/console cache:clear

fix-perms: ## Corrige permisos del directorio var/ en el contenedor PHP (dev)
	$(COMPOSE_DEV) exec --user root php sh /var/www/html/scripts/fix_var_permissions.sh

jwt-keys: ## Genera las claves JWT (dev)
	$(COMPOSE_DEV) exec --user www-data php php bin/console lexik:jwt:generate-keypair --overwrite

# ── FTP ──────────────────────────────────────────

ftp-info: ## Muestra la configuración FTP activa
	@echo "─────────────────────────────────────────"
	@echo " FTP Dev"
	@echo "  Host : localhost"
	@echo "  Puerto: 21"
	@echo "  Usuario: ftpuser"
	@echo "  Contraseña: ftppass_dev"
	@echo "  Directorio remoto: /var/www/html"
	@echo "─────────────────────────────────────────"
	@echo " Modo pasivo: 30000-30009"
	@echo "─────────────────────────────────────────"

# ── Frontend Angular ──────────────────────────────

frontend-build: ## Compila Angular para producción
	cd frontend && npm ci && npx ng build --configuration production

frontend-deploy: frontend-build ## Compila y sube el dist por FTP a producción
	@echo "Sube la carpeta frontend/dist/frontend/ por FTP a /var/www/angular/"
	@echo "Host: $$(grep PUBLICHOST .env.prod | cut -d= -f2)"
	@echo "Dir remoto: /var/www/angular"
	@echo "Puedes usar: lftp -u ftpuser,pass ftp://host -e 'mirror -R frontend/dist/frontend/ /var/www/angular/; quit'"
