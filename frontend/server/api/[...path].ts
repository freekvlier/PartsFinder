export default defineEventHandler(async (event) => {
  const { apiBase, apiToken } = useRuntimeConfig(event)
  const path = event.path.replace(/^\/api/, '')
  const log = (outcome: string | number, body = '') => {
    if (import.meta.dev) {
      console.info(`[api proxy] ${event.method} ${event.path} -> ${outcome}${body ? ` ${body}` : ''}`)
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
