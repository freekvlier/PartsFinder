export default defineEventHandler(async (event) => {
  const { apiBase, apiToken } = useRuntimeConfig(event)
  const path = event.path.replace(/^\/api/, '')
  // Dev only: h3 caches the body, so proxyRequest still forwards it after this read.
  // readRawBody throws 405 on methods without a body, hence the method check.
  const hasBody = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(event.method)
  const payload = import.meta.dev && hasBody ? await readRawBody(event, 'utf8') : undefined
  const log = (outcome: string | number, body = '') => {
    if (import.meta.dev) {
      const request = `${event.method} ${event.path}${payload ? ` ${payload}` : ''}`
      console.info(`[api proxy] ${request} -> ${outcome}${body ? ` ${body}` : ''}`)
    }
  }

  try {
    return await proxyRequest(event, `${apiBase}${path}`, {
      headers: { Authorization: `Bearer ${apiToken}` },
      onResponse: async (_, response) => {
        log(response.status, (await response.clone().text()).slice(0, 300))
      }
    })
  } catch (error) {
    log(`failed (${(error as { cause?: { cause?: { code?: string } } }).cause?.cause?.code ?? 'bad gateway'})`)
    throw error
  }
})
