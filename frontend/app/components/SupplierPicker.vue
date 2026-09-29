<script setup lang="ts">
type Supplier = { id: string, name: string, active: boolean }

const props = withDefaults(defineProps<{
  modelValue: string
  initialLabel?: string
  disabled?: boolean
}>(), {
  initialLabel: '',
  disabled: false
})
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const { t } = useI18n()
const searchTerm = ref('')
const debouncedSearch = ref('')
const page = ref(1)
const suppliers = ref<Supplier[]>([])
const hasMore = ref(false)
const loading = ref(false)
const selectedLabel = ref(props.initialLabel)
let debounceTimer: ReturnType<typeof setTimeout> | undefined
let requestId = 0

const items = computed(() => {
  const options = suppliers.value.map(supplier => ({ label: supplier.name, value: supplier.id }))
  if (props.modelValue && !options.some(option => option.value === props.modelValue) && selectedLabel.value) {
    options.unshift({ label: selectedLabel.value, value: props.modelValue })
  }
  return options
})
const updateValue = (value: string | string[]) => {
  const id = typeof value === 'string' ? value : ''
  selectedLabel.value = suppliers.value.find(supplier => supplier.id === id)?.name ?? selectedLabel.value
  emit('update:modelValue', id)
}
const updateSearchTerm = (value: string) => {
  searchTerm.value = value
}
const loadMore = async (reset = false) => {
  if (loading.value && !reset) return
  if (reset) {
    requestId += 1
    page.value = 1
    suppliers.value = []
  }
  const currentRequest = requestId
  loading.value = true
  try {
    const params = new URLSearchParams({ page: String(page.value), limit: '25', active: '1' })
    if (debouncedSearch.value) params.set('search', debouncedSearch.value)
    const response = await apiFetch<{ suppliers: Supplier[], pagination: { hasMore: boolean } }>(`/inventory/suppliers?${params}`)
    if (currentRequest !== requestId) return
    suppliers.value = [...suppliers.value, ...response.suppliers]
    hasMore.value = response.pagination.hasMore
    page.value += 1
  } finally {
    if (currentRequest === requestId) loading.value = false
  }
}
watch(searchTerm, (value) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    debouncedSearch.value = value.trim()
  }, 250)
})
watch(debouncedSearch, () => { void loadMore(true) })
watch(() => props.initialLabel, value => { selectedLabel.value = value })
onMounted(() => { void loadMore(true) })
onBeforeUnmount(() => clearTimeout(debounceTimer))
</script>

<template>
  <SearchableSelect
    :model-value="modelValue"
    :items="items"
    :disabled="disabled"
    :has-more="hasMore"
    :loading="loading"
    :load-more="loadMore"
    :search-term="searchTerm"
    :placeholder="t('purchasing.selectSupplier')"
    :search-placeholder="t('suppliers.search')"
    @update:model-value="updateValue"
    @update:search-term="updateSearchTerm"
  />
</template>
