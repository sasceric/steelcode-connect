import assert from 'node:assert/strict'
import test from 'node:test'

// Run against an available Nuxt server with no authenticated session.
// LOCALE_TEST_BASE_URL allows an isolated server without restarting user services.
const baseUrl = process.env.LOCALE_TEST_BASE_URL || 'http://localhost:3000'
const languages = [
  { code: 'bs', prefix: '' },
  { code: 'en', prefix: '/en' },
  { code: 'de', prefix: '/de' }
]

for (const { code, prefix } of languages) {
  for (const page of ['/login', '/signup', '/forgot-password', '/reset-password']) {
    test(`${code}: ${prefix}${page} renders without an auth redirect loop`, async () => {
      const response = await fetch(new URL(`${prefix}${page}`, baseUrl), {
        redirect: 'manual',
        signal: AbortSignal.timeout(20000)
      })
      assert.equal(response.status, 200)
      const html = await response.text()
      assert.equal(html.match(/<html[^>]*lang="([^"]*)"/)?.[1], code)
    })
  }

  test(`${code}: login links retain their locale`, async () => {
    const response = await fetch(new URL(`${prefix}/login`, baseUrl), {
      signal: AbortSignal.timeout(20000)
    })
    const html = await response.text()
    assert.ok(html.includes(`href="${prefix}/forgot-password"`))
    assert.ok(html.includes(`href="${prefix}/signup"`))
  })

  test(`${code}: protected routes redirect to the matching login`, async () => {
    for (const path of ['/', '/catalogue/products?tab=general', '/shop/custom-fields']) {
      const response = await fetch(new URL(`${prefix}${path}`, baseUrl), {
        redirect: 'manual',
        signal: AbortSignal.timeout(20000)
      })
      assert.equal(response.status, 302)
      const location = new URL(response.headers.get('location'), baseUrl)
      assert.equal(location.pathname, `${prefix}/login`)
    }
  })
}

test('unprefixed URL stays Bosnian despite an English browser/cookie preference', async () => {
  const response = await fetch(new URL('/login', baseUrl), {
    redirect: 'manual',
    headers: {
      'Accept-Language': 'en-US,en;q=0.9',
      Cookie: 'steelcode_locale=en'
    },
    signal: AbortSignal.timeout(20000)
  })
  assert.equal(response.status, 200)
  const html = await response.text()
  assert.equal(html.match(/<html[^>]*lang="([^"]*)"/)?.[1], 'bs')
})
