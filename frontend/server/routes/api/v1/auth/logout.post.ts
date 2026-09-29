import { deleteCookie, getRequestHeader } from 'h3'

export default defineEventHandler(async (event) => {
  await $fetch('http://127.0.0.1:8000/api/v1/auth/logout', {
    method: 'POST',
    headers: {
      cookie: getRequestHeader(event, 'cookie') || ''
    }
  })

  deleteCookie(event, 'steelcode_session', {
    path: '/',
    httpOnly: true,
    sameSite: 'lax'
  })

  return null
})
