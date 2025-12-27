.PHONY: help install up down restart build logs shell db-shell redis-shell migrate seed fresh test horizon clear

help:
	@echo "MacQueen Travel Platform - Available Commands"
	@echo ""
	@echo "Setup:"
	@echo "  make install    - First time setup (build, up, composer, migrate)"
	@echo "  make build      - Build Docker containers"
	@echo "  make up         - Start Docker containers"
	@echo "  make down       - Stop Docker containers"
	@echo "  make restart    - Restart Docker containers"
	@echo ""
	@echo "Development:"
	@echo "  make shell      - Access PHP container shell"
	@echo "  make db-shell   - Access MySQL shell"
	@echo "  make redis-shell- Access Redis shell"
	@echo "  make logs       - View container logs"
	@echo "  make horizon    - Start Horizon queue worker"
	@echo ""
	@echo "Database:"
	@echo "  make migrate    - Run migrations"
	@echo "  make seed       - Run seeders"
	@echo "  make fresh      - Fresh migrate with seeders"
	@echo ""
	@echo "Testing:"
	@echo "  make test       - Run tests"
	@echo ""
	@echo "Maintenance:"
	@echo "  make clear      - Clear all caches"

install:
	@echo "Building containers..."
	docker-compose build
	@echo "Starting containers..."
	docker-compose up -d
	@echo "Installing dependencies..."
	docker-compose exec app composer install
	@echo "Copying environment file..."
	docker-compose exec app cp .env.example .env
	@echo "Generating application key..."
	docker-compose exec app php artisan key:generate
	@echo "Running migrations..."
	docker-compose exec app php artisan migrate
	@echo ""
	@echo "Installation complete!"
	@echo "Access the application at: http://localhost:8847"

build:
	docker-compose build

up:
	docker-compose up -d

down:
	docker-compose down

restart:
	docker-compose restart

logs:
	docker-compose logs -f

shell:
	docker-compose exec app bash

db-shell:
	docker-compose exec db mysql -u macqueen_user -psecret macqueen

redis-shell:
	docker-compose exec redis redis-cli

migrate:
	docker-compose exec app php artisan migrate

seed:
	docker-compose exec app php artisan db:seed

fresh:
	docker-compose exec app php artisan migrate:fresh --seed

test:
	docker-compose exec app php artisan test

horizon:
	docker-compose exec app php artisan horizon

clear:
	docker-compose exec app php artisan cache:clear
	docker-compose exec app php artisan config:clear
	docker-compose exec app php artisan route:clear
	docker-compose exec app php artisan view:clear

lint:
	docker-compose exec app ./vendor/bin/pint