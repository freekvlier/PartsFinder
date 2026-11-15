SAIL := cd backend && ./vendor/bin/sail

.DEFAULT_GOAL := up

.PHONY: setup up down seed test test-backend test-frontend

setup:
	cd backend && cp --update=none .env.example .env
	cd backend && composer install
	cd frontend && cp --update=none .env.example .env
	cd frontend && pnpm install

up:
	docker compose up

down:
	docker compose down

seed:
	@grep -q '^APP_KEY=.\+' backend/.env || ( $(SAIL) artisan key:generate )
	$(SAIL) artisan migrate:fresh --seed

test: test-backend test-frontend

test-backend:
	$(SAIL) artisan test

test-frontend:
	cd frontend && pnpm test
