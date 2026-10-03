export default defineNuxtRouteMiddleware(async (to) => {
  const publicRoutes = new Set(['login', 'signup', 'forgot-password', 'reset-password'])
  const routeBaseName = useRouteBaseName()
  const localePath = useLocalePath()
  const { localeCodes } = useNuxtApp().$i18n
  const requestedLocale = to.path.split('/')[1]
  const targetLocale = unref(localeCodes).find(code => code === requestedLocale) || 'bs'
  const isPublic = publicRoutes.has(String(routeBaseName(to) || ''))
  const auth = useAuth()

  await auth.restore()

  if (!auth.isAuthenticated.value && !isPublic) {
    return navigateTo(localePath('/login', targetLocale))
  }
  if (auth.isAuthenticated.value && isPublic) {
    return navigateTo(localePath('/', targetLocale))
  }
})
