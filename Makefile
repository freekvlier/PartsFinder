SAIL := cd backend && ./vendor/bin/sail

.DEFAULT_GOAL := up

.PHONY: setup up dev attach down seed types format lint test test-backend test-frontend

setup:
	cd backend && cp --update=none .env.example .env
	docker run --rm -u "$$(id -u):$$(id -g)" -v "$$(pwd)/backend:/var/www/html" -w /var/www/html \
		composer:2 composer install --ignore-platform-reqs
	cd frontend && cp --update=none .env.example .env
	cd frontend && pnpm install

up:
	$(SAIL) up -d

dev:
	cd frontend && pnpm dev

attach:
	$(SAIL) logs --follow

down:
	$(SAIL) down

seed:
	@grep -q '^APP_KEY=.\+' backend/.env || ( $(SAIL) artisan key:generate )
	$(SAIL) artisan migrate:fresh --seed

types:
	$(SAIL) artisan scramble:export
	cd frontend && pnpm generate:api-types

format:
	$(SAIL) bin pint
	cd frontend && pnpm eslint --fix .

lint:
	$(SAIL) bin pint --test
	cd frontend && pnpm lint && pnpm typecheck

test: test-backend test-frontend

test-backend:
	$(SAIL) artisan test

test-frontend:
	cd frontend && pnpm test
