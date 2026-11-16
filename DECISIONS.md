# Decisions

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

## 2. A single part model with a colour pivot, not part variants

**Chosen.** One `parts` row holds condition, price (integer cents) and stock. Colours are a many-to-many table (`colour_part`); a quote line stores the part and an optional colour.

**Rejected.** A `part_variants` table where each variant has its own colour, condition, price, stock and possibly OEM number and product image.

**Why.** Variants would be the more logical model for a shop where every variant carries its own price, OEM number, image and stock. This shop is kept simple: a part has one price, one condition and one stock number, so the list, filter and sort work on a single row per part. Variants would add tables, aggregate queries ("cheapest variant", "has a variant with this condition") and a more complex card for data this shop does not vary. A new and a used version of an OEM are simply two parts that share the OEM number.

**Trade-offs.** Stock is shared across a part's colours, so the quote check totals the quantities of all lines of a part, in any colour. That is stricter than checking each line alone, but a quote can never promise more units than exist. If per-colour stock were needed later, `part_variants` is the next step, and quote lines could point at it.

## 3. Condition is a text column cast to an enum, not an integer

**Chosen.** `parts.condition` is a string column (`new` or `used`) that Laravel casts to a backed PHP enum (`App\Condition`).

**Rejected.**
- An integer code (0 for new, 1 for used).
- A database `ENUM` column type

**Why.** Easily extendable without a migration: another condition (for example `refurbished`) is one new enum case.

**Trade-offs.** The database itself does not reject an invalid string; validation and the enum cast do. The column is indexed because it is filtered on.

## 4. Prices are stored as integer cents

**Chosen.** `parts.price_cents` is an unsigned integer (79500 for €795.00). All price and total calculations use integer cents, and only the display divides by 100.

**Rejected.**
- Floating point numbers.
- A decimal or database money type (`decimal(10,2)`).

**Why.**
- Floats cannot represent most decimal fractions exactly (0.1 is stored as 0.1000000000000000055...), so sums and comparisons drift. Integers are exact.
- Integers behave the same in every language, database and API client. There is no dependence on a money type, locale or driver, and the value is the same in PHP, SQL and JSON.
- A cent is the smallest unit a price can have, so building up from it is safer than breaking a larger unit down.
- Money maths becomes fixed-point: multiply and add first, round once per step. This suits the quote totals, where every step (line total, discount, VAT, total) is rounded to a cent.
- It is common practice, including in banking systems that run on stacks with a decimal type.

**Trade-offs.** Values must be divided by 100 for display and multiplied when importing (the seed file lists euros and the seeder converts). A `decimal(10,2)` column would be exact in the database too, but Laravel returns it as a string in PHP, so calculations would still need conversion to an exact type.

## 5. Quote rules live in a service class, not in controllers or the model

**Chosen.** `QuoteService` holds every rule (colour, quantity and stock checks, merging lines, backorders, submit and the price snapshot) and `QuoteCalculator` holds the totals maths. `Quote::totals()` derives the totals from the quote's own data through the calculator (stored once submitted, live while draft), so the resource can read them from the model. Controllers only validate input and call the service; access to a quote is a policy that answers 404 for another workshop's quote.

**Rejected.** Putting the rules in controllers, in model events, or in the frontend.

**Why.**
- The rules do not depend on HTTP, so they can be tested and reused (a console command or job could call them) without a request.
- One place to read and change them, instead of the same check spread over a store and an update action.
- Totals are calculated on the server only and returned with every response, so the frontend renders numbers and never adds them up.
- The calculator uses integer cents and rounds half up at every step, so results are exact and repeatable.

**Trade-offs.** The server must process every change. The frontend cannot show a new total or reject an over-stock quantity on its own, so each add, update or remove waits for a response before the quote drawer updates.

## 7. A quote stores its own discount and VAT rates

**Chosen.** `quotes.discount_percentage` and `quotes.vat_percentage` are copied from the workshop and from `config('quotes.vat_percentage')` when the quote is created. `Quote::totals()` and `QuoteResource` read them from the quote; `QuoteCalculator` is a pure function of line totals and the two rates.

**Rejected.**
- Reading the live workshop discount and the VAT config at calculation time, with the calculator resolved from the container inside the model.
- Snapshotting the rates on submit only, like the unit prices.

**Why.**
- A quote is an offer under the terms of the moment it was made. The response showed the live workshop discount next to a stored discount amount; after a discount change the two no longer matched.
- The model needs no container or config access, so the totals maths is testable with plain values and the model has no hidden dependencies.
- One place reads the config: `QuoteService::create`.

**Trade-offs.** A draft quote keeps the rates from its creation while its prices stay live. A workshop that gets a new discount sees it in its next quote, not in an open draft. If a draft should follow rate changes, `QuoteService` can refresh the two columns on the next change.

## 6. The frontend's API types are generated from the backend

**Chosen.** Scramble reads the Laravel routes, form requests, resources and docblocks and exports `backend/openapi.json`. `openapi-typescript` turns that into `frontend/app/types/api.d.ts`. `make types` runs both steps, and both generated files are committed. The frontend types (`Part`, `Quote`, `PartsResponse` and so on) are aliases of the generated ones.

**Rejected.**
- Writing the TypeScript interfaces by hand in the frontend.
- Validating responses at runtime with a schema library such as zod.

**Why.**
- The backend is the single source of truth for the API shape. A field renamed or removed there turns into a type error in the frontend after `make types`, instead of a blank value in the browser.
- Request parameters are typed too, for example `brand[]`, `sort: 'price_asc' | 'price_desc'` and the `Condition` enum, so the URL state of the overview can only produce values the API accepts.
- No second description of every endpoint to keep in sync. The same document is the API documentation.
- It fits the rule that the frontend does not compute anything: it renders what the generated types say the API returns.

**Trade-offs.**
- It only works when `make types` is run after an API change; a forgotten run leaves stale types. The generated files are committed so a diff shows the contract change.
- Scramble infers the shapes from the code, so some code needs help. Chaining query scopes hid the paginator and dropped `meta` from the spec until the query stayed typed, and a value Scramble cannot follow (a config value through the container) needs an inline `/** @var int */`.
- The types are checked at compile time only. If the backend sends something its own docs do not describe, nothing fails in the browser.

## 8. The API uses snake_case

**Chosen.** Every field name in the contract is snake_case: query parameters (`per_page`), request bodies (`part_id`) and responses (`price_cents`, `meta.last_page`). Resources add computed fields but never rename a column.

**Rejected.** camelCase, the JavaScript convention, which the first resources used.

**Why.**
- It is what Laravel produces by itself: a model's `toArray()`, the paginator's `meta` and validation error keys are all snake_case. A plain model or paginator can be returned without a resource, as the "return the model as is" rule intends.
- One name from database column to TypeScript type. A search for `price_cents` finds the migration, the model, the resource, the spec, the frontend and the tests.
- Less code: no pagination override, and resources that only list what is computed.

**Trade-offs.** Frontend code reads `part.price_cents` instead of `part.priceCents`. Types are generated either way, so nothing else in the frontend cares.
