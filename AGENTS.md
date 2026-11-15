# AGENTS.md

Working rules for LLM agents in this repository. Sub-project files (`backend/AGENTS.md`, `backend/CLAUDE.md`) add Laravel-specific rules; read them before touching `backend/`.

## Stack

- `frontend/`: Nuxt 4, Vue 3, Tailwind CSS, fully responsive.
- `backend/`: Laravel 13, PHP 8.3+, REST JSON API under `/api`.
- Database: MySQL 8 through migrations and seeders. SQLite is acceptable for the automated test run only.
- Tests: backend feature tests (PHPUnit or Pest), frontend unit/component tests (Vitest).

## Structure

- The frontend talks to the API over HTTP only. No shared database access, and no Nuxt server routes that bypass Laravel.
- The backend owns all data logic: filtering, sorting, pagination and totals. The frontend passes the user's intent to the API and renders the result. It never filters, sorts or sums data itself.
- Business rules live in the backend, in a place independent of the HTTP layer (not in controllers).
- Schema changes go through migrations, with foreign keys and indexes on the columns that are searched and filtered. Seed data goes through seeders.
- The frontend reads its API URL and token from environment variables.

## Testing

- Backend: a feature test for every endpoint, including unhappy paths, plus a test per business rule.
- Search and filter tests assert both what is returned and what is excluded.
- Frontend: Vitest tests for components and composables, covering what renders from an API response and which request each user action or URL state produces.
- Test behaviour, not implementation.