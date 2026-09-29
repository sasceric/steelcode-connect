import { getRequestURL, getRouterParam, proxyRequest } from 'h3'

export default defineEventHandler((event) => {
  const path = getRouterParam(event, 'path') || ''
  const { search } = getRequestURL(event)

  return proxyRequest(event, `http://127.0.0.1:8000/api/${path}${search}`)
})
