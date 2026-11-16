# AGENTS.md

Working rules for LLM agents in this repository. Sub-project files (`backend/AGENTS.md`, `backend/CLAUDE.md`) add Laravel-specific rules; read them before touching `backend/`.

## Stack

- `frontend/`: Nuxt 4 with Vue 3, Nuxt UI and Tailwind CSS 4; server-side rendered, works on phones and desktops.
- `backend/`: Laravel 13 on PHP 8.5, a JSON REST API under `/api` with Sanctum bearer tokens.
- Database: MySQL 8; the schema comes from migrations, the data from seeders. The test suite runs on SQLite in memory, nothing else does.
- Tests: Pest for the backend, Vitest for the frontend.

## Structure

- Frontend and backend meet over HTTP only. The frontend has no database connection, and its single Nitro route does nothing but forward `/api/*` to Laravel with the workshop token added.
- Filtering, sorting, pagination and totals happen in Laravel. The frontend sends the user's choices as query parameters or a request body and renders what comes back; it never filters, sorts or adds up data itself.
- Business rules live in service classes under `backend/app/Services`, never in controllers, so they run without a request.
- Every schema change is a migration. Relations get foreign keys; every column that is searched, filtered or sorted gets an index. Data goes in through seeders.
- The frontend reads its API URL and token from environment variables.

## Data model

- `parts` hold `brand_id`, `category_id`, `name`, `oem`, `condition` (`App\Condition` enum), `price_cents`, `stock_quantity` and `image`. Brand, category and colour are tables referenced by foreign keys. OEM is indexed but not unique (a new and a used part can share it).
- Colours are many-to-many through the `colour_part` pivot. A part without colours has no pivot rows. Stock is one number per part, shared by all its colours.
- Money is stored and returned as integer cents (`price_cents`), never as decimals or floats. Only the frontend divides by 100, for display.
- `Part::in_stock` is derived from `stock_quantity`, not stored.
- Quote lines hold `part_id` and a nullable `colour_id` pointing at `colours`, a `backorder` flag and a `unit_price_cents` snapshot that is only filled on submit.
- Quote rules live in `app/Services/QuoteService.php` (lines, stock, submit) and `QuoteCalculator.php` (pure, static totals maths), never in controllers. A quote stores its own `discount_percentage` and `vat_percentage`, copied from the workshop and `config('quotes.vat_percentage')` when it is created; later changes to either only affect new quotes. A line's unit price and total come from `QuoteLine::unitPriceCents()` and `totalCents()`. Totals use integer cents and round half up at every step. Stock is shared across a part's colours, so the quantities of all lines of one part, in any colour, may not exceed the stock; a part without stock is a backorder. A part with colours needs one of its colours; a part without colours takes none.
- Another workshop's quote is a 404 through `QuotePolicy` (`denyAsNotFound`), applied with `->can()` on the routes; a submitted quote answers 409 on every change (the same policy, `update`).
- `PartSeeder` seeds the catalogue from `database/seeders/data/parts.json` (prices in euros, converted to cents) in every environment; `WorkshopSeeder` is local only.

## Testing

- Backend: a feature test for every endpoint, including unhappy paths, plus a test per business rule. Authenticate with `actingAsWorkshop()` (`Sanctum::actingAs`); the token path itself and the 401 of every endpoint are tested in `WorkshopAuthenticationTest`.
- A search or filter test checks the matches it expects and also names the parts that must be left out.
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
- Everything in the contract is snake_case, like the database columns and Laravel's own paginator and validation output: query parameters (`per_page`), bodies (`part_id`) and responses (`price_cents`, `meta.last_page`). Resources never rename a field; they only add computed ones. Nested data gets its own resource (`QuoteLineResource`) so the spec names its schema.
- After changing an endpoint: run `make types` (it runs `sail artisan scramble:export` in `backend/`, then `pnpm generate:api-types` in `frontend/`). Commit both generated files.
- Scramble reads the database schema. If the Sail database is unusable, export with a temporary SQLite env file (`--env=...`) and delete it afterwards.

## Frontend

- Rendering is SSR. Components call the same-origin `/api/...` with `useFetch`; the Nitro route `frontend/server/api/[...path].ts` only forwards the request to Laravel and adds the workshop bearer token. It holds no business logic; in development it logs every forwarded request and response. The token stays server-side and the browser never calls Laravel directly.
- Page state lives in the URL (`useRoute().query`); `useFetch('/api/...', { query: { page } })` refetches when it changes. Types for API data come from the generated `~/types/api` (see `app/types/parts.ts`), never hand-written.
- Prices arrive as integer cents; display them with `formatPrice()` from `app/utils`.
- The id of the open quote lives in the `quote-id` cookie, so the server render already contains the quote. The layout restores it once per render with `callOnce('quote', restore)`. Components run API actions through `useApiAction()` for the error messages.
- Page and component tests mount with `mountSuspended` and mock the API with `registerEndpoint`; call `clearNuxtData()` before each test, because `useFetch` caches by key. Wait for URL or state changes with `vi.waitFor`, never with a sleep. Tests that start the real server (`apiProxy.test.ts`) must set `NUXT_API_BASE` and `NUXT_API_TOKEN` in `process.env`, or a local `frontend/.env` overrides them.
- Env variables (private runtime config, see `frontend/.env.example`): `NUXT_API_BASE` (Laravel API URL, in Docker `http://laravel.test/api`) and `NUXT_API_TOKEN`.

## Docker

- `docker compose up` in the repo root includes `backend/compose.yaml` (Sail) and `frontend/compose.yaml`. Each part owns its own file.
- The root stack and a separately running Sail stack use the same host ports (80, 3306, 8080). Don't stop the user's running stack; override `APP_PORT`, `FORWARD_DB_PORT`, `FORWARD_PMA_PORT` and `FRONTEND_PORT` to test.
- Never run `docker compose down -v` or recreate volumes without asking: they hold the user's data.
- The frontend container runs the stock `node:24` image as its `node` user (uid 1000), so what it writes on the bind mount belongs to a host user with uid 1000. Another uid needs a Dockerfile that remaps `node`. Should `.nuxt` or `node_modules` ever turn root-owned anyway, `chown` them through a throwaway container, then run `pnpm nuxt prepare`.
- Start flow: `make setup` (env files and packages), `make up` (starts the stack detached), then `make seed` (generates `APP_KEY` if empty, then `migrate:fresh --seed`). `make attach` follows the logs of all containers; `make down` stops the stack. The frontend defaults to the fixed local token `local-token-standard`, so no token needs copying.
