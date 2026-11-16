# Decisions

| # | Decision | In one line |
|---|----------|-------------|
| 1 | Nuxt proxy route | Browser calls same-origin `/api`, Nitro adds the token |
| 2 | Generated API types | Scramble and `openapi-typescript` from the backend |
| 3 | Server-side querying | Laravel filters, sorts, paginates and counts |
| 4 | Single part model | One row per part, colours via pivot, no variants |
| 5 | Integer cents | Money is never a float or decimal |
| 6 | Quote service | Rules in `QuoteService` and `QuoteCalculator`, not controllers |
| 7 | Rates stored on quote | Discount and VAT are copied at creation |
| 8 | snake_case API | Same names from column to TypeScript |

## 1. The frontend proxies API calls through a Nuxt server route

**Chosen.** The browser calls same-origin `/api/...`. One Nitro route, `frontend/server/api/[...path].ts`, forwards to Laravel and adds the workshop bearer token. The token and Laravel URL are private runtime config. The route holds no logic: all filtering, totals and rules stay in Laravel.

**Rejected.** Calling Laravel directly from the browser with the token in public config.

**Why.**
- The token and backend URL stay out of the browser.
- SSR: Nuxt runs calls to internal API routes directly while rendering, so there is no extra HTTP request, and the first paint already contains the data.
- Browser and server render use the same URL and code: no CORS, no second base URL.
- One controlled public interface in front of the backend (backend-for-frontend pattern).
- A natural place to cache catalog reads later (Nuxt route rules).
- Advised when an API needs a key or is not public.

**Trade-offs.** It adds one hop and some complexity when debugging requests

## 2. The frontend's API types are generated from the backend

**Chosen.** Scramble exports `backend/openapi.json` from routes, form requests and resources. `openapi-typescript` turns it into `frontend/app/types/api.d.ts`. `make types` runs both, and both files are committed. Frontend types (`Part`, `Quote`, ...) alias the generated ones.

**Rejected.** Hand-written interfaces, and runtime validation with a schema library such as zod.

**Why.**
- The backend is the single source of truth: a renamed field becomes a type error, not a blank value in the browser.
- Request parameters are typed too (`sort: 'price_asc' | 'price_desc'`, `Condition`).
- The same document doubles as API documentation.

**Trade-offs.**
- `make types` must be run after every API change. Committed files make the contract diff visible.
- Scramble infers from code, so some spots need hints (typed query chains for `meta`, `@var int` for config values).
- Compile-time only: a response that breaks its own docs is not caught in the browser.

## 3. Filtering, sorting, pagination and counts happen in the API

**Chosen.** `GET /api/parts` takes `q`, `brand[]`, `condition`, `sort`, `page` and `per_page`. `GET /api/brands` returns counts for the active search. The frontend only turns the URL into parameters and renders the result.

**Rejected.** Loading all 54 parts and filtering in the browser. Simpler and instant at this size.

**Why.**
- One definition of matching and sorting for every client.
- A shared link maps one-to-one onto API parameters.
- Scales to 50,000 parts: indexed queries, one page over the wire.

**Trade-off.** Every filter change is a round trip, so search is debounced.

## 4. A single part model with a colour pivot, not part variants

**Chosen.** One `parts` row holds condition, price and stock. Colours are a simple link table (`colour_part`).

**Rejected.** A `part_variants` table, where each variant has its own colour, price, stock and image. That is the better model for a complete store.

**Why.** This is a simple example store, so one row per part keeps it easy to build, query and read.

**Trade-off.** Stock is shared by all colours of a part. Per-colour stock would need `part_variants`.

## 5. Prices are stored as integer cents

**Chosen.** `parts.price_cents` is an unsigned integer (79500 is €795.00). All maths uses cents; only display divides by 100.

**Rejected.** Floats, and `decimal(10,2)`.

**Why.**
- Floats drift (0.1 is not exact). Integers are exact.
- Same value in PHP, SQL and JSON, with no money type or driver dependence.
- Fits quote totals, where every step is rounded to a cent.

**Trade-off.** Divide by 100 to display, multiply on import (the seeder converts euros). `decimal` is exact in the database, but Laravel returns it as a string, so PHP would need conversion anyway.

## 6. Quote rules live in a service class, not in controllers or the model

**Chosen.** `QuoteService` holds every rule (colour, quantity and stock checks, merging lines, backorders, submit, price snapshot). `QuoteCalculator` holds the totals maths. Controllers validate and call the service.

**Rejected.** Rules in controllers, model events or the frontend.

**Why.**
- No HTTP dependency, so rules are testable and reusable (commands, jobs).
- One place to read and change each rule.
- Totals come from the server only, so the frontend never adds anything up.

**Trade-off.** Every add, update or remove waits for the server before the quote drawer updates.

## 7. A quote stores its own discount and VAT rates

**Chosen.** `quotes.discount_percentage` and `quotes.vat_percentage` are copied from the workshop and `config('quotes.vat_percentage')` when the quote is created. `QuoteCalculator` is a pure function of line totals and these two rates.

**Rejected.**
- Reading live workshop discount and VAT config at calculation time.
- Snapshotting the rates on submit only, like unit prices.

**Why.**
- A quote is an offer under the terms of the moment it was made. With live rates, the shown discount and the stored amount drifted apart after a change.
- The model needs no config or container, and `QuoteService::create` is the only place that reads config.

**Trade-off.** A draft keeps its creation rates while its prices stay live. A new workshop discount applies to the next quote, not an open draft.

## 8. The API uses snake_case

**Chosen.** All contract fields are snake_case: `per_page`, `part_id`, `price_cents`, `meta.last_page`. Resources add computed fields but never rename columns.

**Rejected.** camelCase, which the first resources used.

**Why.**
- It is Laravel's own output (models, paginator `meta`, validation keys), so models can be returned as is.
- One name from column to TypeScript type, so one search finds every use.
- Less code: no pagination override, resources list only computed fields.

**Trade-off.** Frontend code reads `part.price_cents` instead of `part.priceCents`.
