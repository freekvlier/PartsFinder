SAIL := cd backend && ./vendor/bin/sail

.PHONY: up down backend frontend test

up: backend
	$(MAKE) frontend

backend:
	$(SAIL) up -d

down:
	$(SAIL) down

frontend:
	cd frontend && pnpm dev

test:
	$(SAIL) artisan test
