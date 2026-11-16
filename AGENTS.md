# AGENTS.md

Working rules for LLM agents in this repository. Sub-project files (`backend/AGENTS.md`, `backend/CLAUDE.md`) add Laravel-specific rules; read them before touching `backend/`.

## Stack

- `frontend/`: Nuxt 4, Vue 3, Tailwind CSS, fully responsive.
- `backend/`: Laravel 13, PHP 8.3+, REST JSON API under `/api`.
- Database: MySQL 8 through migrations and seeders. SQLite is acceptable for the automated test run only.
- Tests: backend feature tests (PHPUnit or Pest), frontend unit/component tests (Vitest).

## Structure

- The frontend talks to the API over HTTP only. No shared database access, and no Nuxt server routes that bypass Laravel. The only server route is the token-adding forwarder to Laravel.
- The backend owns all data logic: filtering, sorting, pagination and totals. The frontend passes the user's intent to the API and renders the result. It never filters, sorts or sums data itself.
- Business rules live in the backend, in a place independent of the HTTP layer (not in controllers).
- Schema changes go through migrations, with foreign keys and indexes on the columns that are searched and filtered. Seed data goes through seeders.
- The frontend reads its API URL and token from environment variables.

## Data model

- `parts` hold `brand_id`, `category_id`, `name`, `oem`, `condition` (`App\Condition` enum), `price_cents`, `stock_quantity` and `image`. Brand, category and colour are tables referenced by foreign keys. OEM is indexed but not unique (a new and a used part can share it).
- Colours are many-to-many through the `colour_part` pivot. A part without colours has no pivot rows. Stock is one number per part, shared by all its colours.
- Money is stored and returned as integer cents (`price_cents`), never as decimals or floats. Only the frontend divides by 100, for display.
- `Part::in_stock` is derived from `stock_quantity`, not stored.
- Quote lines hold `part_id` and a nullable `colour_id` pointing at `colours`, a `backorder` flag and a `unit_price_cents` snapshot that is only filled on submit.
- Quote rules live in `app/Services/QuoteService.php` (lines, stock, submit) and `QuoteCalculator.php` (totals), never in controllers. Totals use integer cents and round half up at every step. Stock is shared across a part's colours, so the quantities of all lines of one part, in any colour, may not exceed the stock; a part without stock is a backorder. A part with colours needs one of its colours; a part without colours takes none.
- Another workshop's quote is a 404 through `QuotePolicy` (`denyAsNotFound`), applied with `->can()` on the routes; a submitted quote answers 409 on every change (the same policy, `update`).
- `PartSeeder` seeds the catalogue from `database/seeders/data/parts.json` (prices in euros, converted to cents) in every environment; `WorkshopSeeder` is local only.

## Testing

- Backend: a feature test for every endpoint, including unhappy paths, plus a test per business rule.
- Search and filter tests assert both what is returned and what is excluded.
- Frontend: Vitest tests for components and composables, covering what renders from an API response and which request each user action or URL state produces.
- Test behaviour, not implementation.

## Conventions

- Lines of code are at most 120 characters, except generated files (`openapi.json`, `api.d.ts`) and framework stubs. ESLint enforces it in `frontend/`; in `backend/` keep to it by hand (Pint does not).
- Keep it simple: use framework defaults. Don't add config, guards, factory states or helpers that the framework already covers.
- No inline comments. Document functions with a docblock whose description is one line, plus `@param`/`@return`; put a non-obvious why in that line or in a clearer name.
- Auth is Sanctum bearer tokens. `WorkshopSeeder` creates two workshops with fixed local tokens (`local-token-standard`, 0% discount; `local-token-discount`, 10%), and `DatabaseSeeder` only runs it in the `local` environment. `php artisan workshop:create {name} {discount=0}` creates a workshop and prints a generated token once.
- Controllers are regular controllers with named methods, not invokable ones; they are expected to grow.
- Return the model as is. Use an API resource only when the response differs from the model (computed fields, nested data). This overrides the "default to resources" line in `backend/CLAUDE.md`.
- Don't delete template tests; fix or repoint them.
- Seeders are not idempotent. Reset with `php artisan migrate:fresh --seed`.
- When a decision is genuinely hard or has real trade-offs (rendering mode, architecture, anything hard to reverse), ask the user instead of choosing.

## Commands

- Run every Artisan and Composer command in `backend/` through Sail (`./vendor/bin/sail artisan ...`, `sail artisan test`), not on the host. The host cannot reach the `mysql` host name.
- Backend: format with `vendor/bin/pint --format agent`. Frontend (in `frontend/`): `pnpm test`, `pnpm lint`, `pnpm typecheck`.
- `composer require` runs `boost:update`, which rewrites `backend/AGENTS.md` and `backend/CLAUDE.md`. Check the diff afterwards (the PHP version line must stay 8.5).
- Use the Laravel Boost `search-docs` tool for Laravel and Sanctum questions and the Nuxt MCP docs for Nuxt questions, instead of answering from memory.

## API contract

- The OpenAPI spec is generated by Scramble from the controllers; never edit `backend/openapi.json` or `frontend/app/types/api.d.ts` by hand.
- After changing an endpoint: run `make types` (it runs `sail artisan scramble:export` in `backend/`, then `pnpm generate:api-types` in `frontend/`). Commit both generated files.
- Scramble reads the database schema. If the Sail database is unusable, export with a temporary SQLite env file (`--env=...`) and delete it afterwards.

## Frontend

- Rendering is SSR. Components call the same-origin `/api/...` with `useFetch`; the Nitro route `frontend/server/api/[...path].ts` only forwards the request to Laravel and adds the workshop bearer token. It holds no logic. The token stays server-side and the browser never calls Laravel directly.
- Page state lives in the URL (`useRoute().query`); `useFetch('/api/...', { query: { page } })` refetches when it changes. Types for API data come from the generated `~/types/api` (see `app/types/parts.ts`), never hand-written.
- Prices arrive as integer cents; display them with `formatPrice()` from `app/utils`.
- Page and component tests mount with `mountSuspended` and mock the API with `registerEndpoint`; call `clearNuxtData()` before each test, because `useFetch` caches by key. Tests that start the real server (`apiProxy.test.ts`) must set `NUXT_API_BASE` and `NUXT_API_TOKEN` in `process.env`, or a local `frontend/.env` overrides them.
- Env variables (private runtime config, see `frontend/.env.example`): `NUXT_API_BASE` (Laravel API URL, in Docker `http://laravel.test/api`) and `NUXT_API_TOKEN`.

## Docker

- `docker compose up` in the repo root includes `backend/compose.yaml` (Sail) and `frontend/compose.yaml`. Each part owns its own file.
- The root stack and a separately running Sail stack use the same host ports (80, 3306, 8080). Don't stop the user's running stack; override `APP_PORT`, `FORWARD_DB_PORT`, `FORWARD_PMA_PORT` and `FRONTEND_PORT` to test.
- Never run `docker compose down -v` or recreate volumes without asking: they hold the user's data.
- The frontend container runs as root on a bind mount and can leave root-owned files in `frontend/.nuxt`, which breaks ESLint. Fix with a one-off `chown` through a throwaway container, then `pnpm nuxt prepare`.
- Start flow: `make setup` (env files and packages), `make up`, then `make seed` in a second terminal (generates `APP_KEY` if empty, then `migrate:fresh --seed`). The frontend defaults to the fixed local token `local-token-standard`, so no token needs copying.
