<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: string | string[]
  connectionId: string
  side: 'local' | 'target'
  type: string
  multiple?: boolean
  disabled?: boolean
  allOptionLabel?: string
  allSelected?: boolean
  placeholder: string
}>(), {
  multiple: false
})
const emit = defineEmits<{
  'update:modelValue': [value: string | string[]]
  'selected': [items: { value: string, label: string }[]]
  'selectAll': []
}>()
type Reference = { id: string, label?: string, name?: string, sku?: string, variantCombination?: string | null }
const usesProductOptions = computed(() => props.side === 'local' && props.type === 'product')
const selectedLabels = ref<Record<string, string>>({})
const selectedItems = computed(() => {
  const values = Array.isArray(props.modelValue) ? props.modelValue : [props.modelValue]
  return values.filter(Boolean).map(value => ({ value, label: selectedLabels.value[value] || value }))
})
const { items, records, searchTerm, loading, hasMore, loadMore } = usePagedSelectOptions<Reference>({
  endpoint: computed(() => usesProductOptions.value ? '/products' : `/integrations/${props.connectionId}/exports/references/${props.side}/${props.type}`),
  parameters: computed((): Record<string, string> => usesProductOptions.value ? { view: 'options', includeVariants: 'true' } : {}),
  responseKey: computed(() => usesProductOptions.value ? 'products' : 'items'),
  selectedItems,
  option: item => ({
    value: item.id,
    label: item.name || item.label || item.sku || item.id,
    ...(props.type === 'product' ? { productName: item.name || item.label, productNumber: item.sku, variantCombination: item.variantCombination } : {})
  })
})
function updateValue(value: string | string[]) {
  emit('update:modelValue', value)
  const values = Array.isArray(value) ? value : [value]
  emit('selected', values.map((id) => {
    const item = records.value.find(record => record.id === id)
    return { value: id, label: item?.name || item?.label || id }
  }))
}
function selectAll() {
  emit('selectAll')
}
async function loadSelectedLabels() {
  if (props.multiple || !props.modelValue || Array.isArray(props.modelValue)) return
  const response = await apiFetch<{ items: Reference[] }>(`/integrations/${props.connectionId}/exports/references/${props.side}/${props.type}?ids=${props.modelValue}`)
  for (const item of response.items) selectedLabels.value[item.id] = item.label || item.id
}
onMounted(() => {
  void loadSelectedLabels().catch(() => undefined)
})
watch(() => [props.modelValue, props.type], () => {
  void loadSelectedLabels().catch(() => undefined)
})
</script>

<template>
  <SearchableSelect
    v-model:search-term="searchTerm"
    :model-value="modelValue"
    :items="items"
    :multiple="multiple"
    :disabled="disabled"
    :show-placeholder-when-selected="multiple"
    :all-option-label="allOptionLabel"
    :all-selected="allSelected"
    :has-more="hasMore"
    :loading="loading"
    :load-more="loadMore"
    :placeholder="placeholder"
    :search-placeholder="placeholder"
    @update:model-value="updateValue"
    @select-all="selectAll"
  />
</template>
