.PHONY: help up down build rebuild shell install test clean logs

# Default target
help: ## Show this help message
	@echo "FluentPDO Development Makefile"
	@echo ""
	@echo "Available commands:"
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# * Development environment
up: ## Start the development environment
	docker-compose up -d

down: ## Stop the development environment
	docker-compose down

build: ## Build the Docker images
	docker-compose build

rebuild: ## Rebuild and start the containers
	docker-compose down
	docker-compose build --no-cache
	docker-compose up -d

shell: ## Access the PHP container shell
	docker-compose exec php bash

# * Project management
install: ## Install PHP dependencies
	docker-compose exec php composer install

update: ## Update PHP dependencies
	docker-compose exec php composer update

# * Testing
test: ## Run the test suite
	docker-compose exec php vendor/bin/phpunit

# * Benchmarking
bench: ## Run PHPBench benchmarks
	docker-compose exec php vendor/bin/phpbench run --bootstrap=benchmarks/bootstrap.php benchmarks/

bench-compare: ## Compare benchmarks against baseline
	docker-compose exec php vendor/bin/phpbench run --bootstrap=benchmarks/bootstrap.php --report=aggregate benchmarks/

bench-store: ## Store current benchmarks as baseline
	docker-compose exec php vendor/bin/phpbench run --bootstrap=benchmarks/bootstrap.php --store benchmarks/

test-watch: ## Run tests in watch mode (if available)
	docker-compose exec php vendor/bin/phpunit-watcher watch

# * Utilities
logs: ## Show container logs
	docker-compose logs -f

logs-php: ## Show PHP container logs
	docker-compose logs -f php

logs-mysql: ## Show MySQL container logs
	docker-compose logs -f mysql

clean: ## Remove containers and volumes
	docker-compose down -v --remove-orphans
	docker system prune -f

status: ## Show running containers
	docker-compose ps

# * Database
db-connect: ## Connect to MySQL database
	docker-compose exec mysql mysql -u vagrant -pvagrant fluentdb

db-reset: ## Reset the database (drop and recreate)
	docker-compose exec mysql mysql -u root -proot -e "DROP DATABASE IF EXISTS fluentdb; CREATE DATABASE fluentdb;"
	docker-compose exec mysql mysql -u root -proot fluentdb < tests/_resources/fluentdb.sql