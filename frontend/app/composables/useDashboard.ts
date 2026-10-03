import { createSharedComposable } from '@vueuse/core'

const _useDashboard = () => {
  const route = useRoute()
  const router = useRouter()
  const localePath = useLocalePath()
  const isNotificationsSlideoverOpen = ref(false)

  defineShortcuts({
    'g-h': () => router.push(localePath('/')),
    'g-i': () => router.push(localePath('/inbox')),
    'g-c': () => router.push(localePath('/customers')),
    'g-s': () => router.push(localePath('/settings')),
    n: () => (isNotificationsSlideoverOpen.value = !isNotificationsSlideoverOpen.value),
  })

  watch(
    () => route.fullPath,
    () => {
      isNotificationsSlideoverOpen.value = false
    },
  )

  return {
    isNotificationsSlideoverOpen,
  }
}

export const useDashboard = createSharedComposable(_useDashboard)
