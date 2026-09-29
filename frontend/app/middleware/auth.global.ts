export default defineNuxtRouteMiddleware(async (to) => {
  const publicPaths = new Set(['/login', '/signup', '/forgot-password', '/reset-password'])
  const auth = useAuth()

  await auth.restore()

  if (!auth.isAuthenticated.value && !publicPaths.has(to.path)) return navigateTo('/login')
  if (auth.isAuthenticated.value && publicPaths.has(to.path)) return navigateTo('/')
})
