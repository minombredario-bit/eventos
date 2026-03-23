# Falles App - Development Makefile
# Usage: make <command>
# (On Windows, use Git Bash or WSL to run make)

.PHONY: help build up down logs shell install setup migrate fixtures test jwt

# Default target
help:
	@echo "Falles App - Available commands:"
	@echo ""
	@echo "  make build      - Build Docker images"
	@echo "  make up         - Start all containers"
	@echo "  make down       - Stop all containers"
	@echo "  make logs       - View container logs"
	@echo "  make shell      - Open shell in PHP container"
	@echo "  make install    - Run composer install in container"
	@echo "  make setup      - Full setup (install + jwt + migrate)"
	@echo "  make migrate    - Run database migrations"
	@echo "  make fixtures   - Load development fixtures"
	@echo "  make test       - Run PHPUnit tests"
	@echo "  make jwt        - Generate JWT keys"
	@echo ""

# Build Docker images
build:
	docker-compose build

# Start containers
up:
	docker-compose up -d
	@echo ""
	@echo "Services started:"
	@echo "  - API: http://localhost:8080"
	@echo "  - Adminer: http://localhost:8081 (admin/admin)"
	@echo ""

# Stop containers
down:
	docker-compose down

# View logs
logs:
	docker-compose logs -f

# Open shell in PHP container
shell:
	docker-compose exec php sh

# Install dependencies
install:
	docker-compose exec php composer install

# Generate JWT keys
jwt:
	docker-compose exec php mkdir -p config/jwt
	docker-compose exec php php bin/console lexik:jwt:generate-keypair --overwrite

# Create migrations
migrate-diff:
	docker-compose exec php php bin/console doctrine:migrations:diff

# Run migrations
migrate:
	docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Load fixtures
fixtures:
	docker-compose exec php php bin/console doctrine:fixtures:load --no-interaction

# Run tests
test:
	docker-compose exec php php bin/phpunit

# Full setup
setup: install jwt migrate
	@echo ""
	@echo "Setup complete! API should be available at http://localhost:8080"

# Clear cache
cache:
	docker-compose exec php php bin/console cache:clear
