SAIL := cd backend && ./vendor/bin/sail

.DEFAULT_GOAL := up

.PHONY: setup up attach down seed types format lint test test-backend test-frontend

setup:
	cd backend && cp --update=none .env.example .env
	cd backend && composer install
	cd frontend && cp --update=none .env.example .env
	cd frontend && pnpm install

up:
	docker compose up -d

attach:
	docker compose logs --follow

down:
	docker compose down

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
