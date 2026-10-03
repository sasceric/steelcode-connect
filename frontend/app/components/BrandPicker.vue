<script setup lang="ts">
type Brand = { id: string, name: string, labels: Record<string, string> }

const props = withDefaults(defineProps<{
  modelValue: string[]
  initialBrands?: Brand[]
  locale?: string
}>(), {
  initialBrands: () => [],
  locale: ''
})
const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()
const { t } = useI18n()
const selectedItems = computed(() => props.modelValue.map(id => {
  const brand = props.initialBrands.find(item => item.id === id)
  return { value: id, label: brand?.labels[props.locale] || brand?.name || id }
}))
const { items, searchTerm, loading, hasMore, loadMore } = usePagedSelectOptions<Brand>({
  endpoint: '/brands',
  responseKey: 'brands',
  selectedItems,
  option: brand => ({ value: brand.id, label: brand.labels[props.locale] || brand.name })
})

function updateValue(value: string | string[]) {
  emit('update:modelValue', Array.isArray(value) ? value : [])
}
</script>

<template>
  <SearchableSelect
    :model-value="modelValue"
    :items="items"
    :multiple="true"
    :has-more="hasMore"
    :loading="loading"
    :load-more="loadMore"
    v-model:search-term="searchTerm"
    :placeholder="t('products.selectBrands')"
    :search-placeholder="t('products.searchBrands')"
    @update:model-value="updateValue"
  />
</template>
