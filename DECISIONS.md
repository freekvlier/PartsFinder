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

**Trade-offs.** Stock is shared across a part's colours, so the quote rules must decide how to treat several colour lines of one part. If per-colour stock were needed later, `part_variants` is the next step, and quote lines could point at it.

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
