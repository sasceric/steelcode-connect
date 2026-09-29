export type AuthUser = {
  id: string
  email: string
  firstName: string | null
  lastName: string | null
  phone: string | null
  title: string | null
  active: boolean
  avatarId: string | null
  locale: string
  roles: string[]
}

export type AuthTenant = {
  id: string
  name: string
  role: string
  oib: string | null
  pdv: string | null
  phone: string | null
  email: string | null
  website: string | null
  defaultSnippetLocale: string
  enabledSnippetLocales: string[]
}

type AuthPayload = {
  user: AuthUser
  tenant: AuthTenant
}

type ApiFetchOptions = NonNullable<Parameters<typeof $fetch>[1]>

export const apiFetch = <T>(path: string, options: ApiFetchOptions = {}) => {
  if (import.meta.server) {
    const config = useRuntimeConfig()

    return $fetch<T>(`${config.apiInternalBase}/api/v1${path}`, {
      ...options,
      headers: {
        ...useRequestHeaders(['cookie']),
        ...options.headers,
      },
    })
  }

  return $fetch<T>(`/api/v1${path}`, {
    credentials: 'include',
    ...options,
  })
}

export const useAuth = () => {
  const user = useState<AuthUser | null>('auth:user', () => null)
  const tenant = useState<AuthTenant | null>('auth:tenant', () => null)
  const restored = useState('auth:restored', () => false)

  const apply = (payload: AuthPayload) => {
    user.value = payload.user
    tenant.value = payload.tenant
  }

  const restore = async () => {
    if (restored.value) {
      return user.value
    }

    try {
      apply(await apiFetch<AuthPayload>('/auth/me'))
    } catch {
      user.value = null
      tenant.value = null
    } finally {
      restored.value = true
    }

    return user.value
  }

  const login = async (email: string, password: string) => {
    apply(
      await apiFetch<AuthPayload>('/auth/login', {
        method: 'POST',
        body: { email, password },
      }),
    )

    restored.value = true
  }

  const register = async (tenantName: string, email: string, password: string) => {
    await apiFetch<AuthPayload>('/auth/register', {
      method: 'POST',
      body: { tenantName, email, password },
    })

    await login(email, password)
  }

  const logout = async () => {
    await apiFetch('/auth/logout', { method: 'POST' })

    user.value = null
    tenant.value = null
    restored.value = true
  }

  return {
    user,
    tenant,
    restored,
    isAuthenticated: computed(() => !!user.value),
    restore,
    login,
    register,
    logout,
  }
}
