export const useRouteTab = (defaultTab: string) => {
  const route = useRoute()
  const router = useRouter()
  const initialTab = route.query.tab
  const tab = ref(typeof initialTab === 'string' ? initialTab : defaultTab)

  watch(tab, (value) => {
    if (route.query.tab === value) {
      return
    }

    void router.replace({
      query: {
        ...route.query,
        tab: value
      }
    })
  })

  watch(
    () => route.query.tab,
    (value) => {
      if (typeof value === 'string' && value !== tab.value) {
        tab.value = value
      }
    }
  )

  return tab
}
