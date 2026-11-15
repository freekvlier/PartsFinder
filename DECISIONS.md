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
