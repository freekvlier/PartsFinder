// @vitest-environment node
import { createServer } from 'node:http'
import type { AddressInfo } from 'node:net'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { $fetch, fetch, setup } from '@nuxt/test-utils/e2e'

const received: { url?: string, authorization?: string }[] = []

const upstream = createServer((request, response) => {
  received.push({ url: request.url, authorization: request.headers.authorization })
  response.setHeader('Content-Type', 'application/json')
  response.statusCode = request.url?.startsWith('/api/quotes/404') ? 404 : 200
  response.end(JSON.stringify({ ok: true }))
})
await new Promise<void>(resolve => upstream.listen(0, resolve))
const port = (upstream.address() as AddressInfo).port

await setup({
  rootDir: fileURLToPath(new URL('..', import.meta.url)),
  server: true,
  nuxtConfig: {
    runtimeConfig: { apiBase: `http://localhost:${port}/api`, apiToken: 'test-token' }
  }
})

describe('API proxy', () => {
  it('forwards the path and query to Laravel with the workshop bearer token added', async () => {
    await $fetch('/api/parts?q=sideskirt&brand[]=DAF&page=2')

    expect(received.at(-1)).toEqual({
      url: '/api/parts?q=sideskirt&brand[]=DAF&page=2',
      authorization: 'Bearer test-token'
    })
  })

  it('passes Laravel error statuses through unchanged', async () => {
    const response = await fetch('/api/quotes/404')

    expect(response.status).toBe(404)
  })
})
