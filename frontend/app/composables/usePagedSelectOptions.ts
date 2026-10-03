import type { MaybeRefOrGetter } from 'vue'

export type SelectOption = { label: string, value: string }

export function usePagedSelectOptions<T>(config: {
  endpoint: MaybeRefOrGetter<string>
  parameters?: MaybeRefOrGetter<Record<string, string>>
  enabled?: MaybeRefOrGetter<boolean>
  responseKey: MaybeRefOrGetter<string>
  option: (record: T) => SelectOption
  selectedItems: Ref<SelectOption[]>
}) {
  const { t } = useI18n()
  const notify = useAppToast()
  const records = ref<T[]>([]) as Ref<T[]>
  const searchTerm = ref('')
  const loading = ref(false)
  const hasMore = ref(false)
  const failed = ref(false)
  const knownRecords = new Map<string, T>()
  let page = 1
  let requestId = 0
  let debounceTimer: ReturnType<typeof setTimeout> | undefined
  let stopped = false

  const items = computed(() => {
    const options = new Map(records.value.map((record) => {
      const option = config.option(record)
      return [option.value, option] as const
    }))
    for (const selected of config.selectedItems.value) {
      const known = knownRecords.get(selected.value)
      options.set(selected.value, known ? config.option(known) : selected)
    }
    return [...options.values()]
  })

  async function loadMore(reset = false) {
    if (stopped || toValue(config.enabled) === false || (loading.value && !reset) || (!reset && !hasMore.value)) return
    if (reset) {
      requestId += 1
      page = 1
      records.value = []
      const selectedIds = new Set(config.selectedItems.value.map(item => item.value))
      for (const id of knownRecords.keys()) {
        if (!selectedIds.has(id)) knownRecords.delete(id)
      }
    }
    const currentRequest = requestId
    loading.value = true
    failed.value = false
    try {
      const params = new URLSearchParams({ ...toValue(config.parameters), page: String(page), limit: '25' })
      if (searchTerm.value.trim()) params.set('search', searchTerm.value.trim())
      const response = await apiFetch<Record<string, unknown>>(`${toValue(config.endpoint)}?${params}`)
      if (stopped || currentRequest !== requestId) return
      const incoming = response[toValue(config.responseKey)] as T[]
      for (const record of incoming) {
        knownRecords.set(config.option(record).value, record)
      }
      records.value = [...records.value, ...incoming]
      hasMore.value = (response.pagination as { hasMore: boolean }).hasMore
      page += 1
    } catch {
      if (!stopped && currentRequest === requestId) {
        failed.value = true
        notify.error(t('common.loadOptionsFailed'), t('common.retrySearch'))
      }
    } finally {
      if (currentRequest === requestId) loading.value = false
    }
  }

  watch(searchTerm, () => {
    // Immediately invalidate the old request; a slow response cannot append to a new search.
    requestId += 1
    hasMore.value = false
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      void loadMore(true)
    }, 250)
  })
  watch(
    () => [toValue(config.endpoint), JSON.stringify(toValue(config.parameters)), toValue(config.enabled)],
    () => {
      requestId += 1
      clearTimeout(debounceTimer)
      if (toValue(config.enabled) === false) {
        records.value = []
        loading.value = false
        hasMore.value = false
      }
      void loadMore(true)
    }
  )
  onMounted(() => {
    void loadMore(true)
  })
  onBeforeUnmount(() => {
    stopped = true
    requestId += 1
    clearTimeout(debounceTimer)
  })

  return { items, records, searchTerm, loading, hasMore, failed, loadMore }
}
