<script setup lang="ts">
type SearchableSelectItem = {
  label: string
  value: string
  productName?: string
  productNumber?: string | null
  variantCombination?: string | null
}

const props = withDefaults(
  defineProps<{
    modelValue: string | string[]
    items: SearchableSelectItem[]
    placeholder?: string
    searchPlaceholder: string
    multiple?: boolean
    showPlaceholderWhenSelected?: boolean
    hasMore?: boolean
    loading?: boolean
    loadMore?: () => Promise<void> | void
    searchTerm?: string
  }>(),
  {
    multiple: false
  }
)

const emit = defineEmits<{
  'update:modelValue': [value: string | string[]]
  'update:searchTerm': [value: string]
}>()

const updateModelValue = (value: string | string[]) => {
  emit('update:modelValue', value)
}

const updateSearchTerm = (value: string) => {
  emit('update:searchTerm', value)
}

const isProductItem = (item: SearchableSelectItem) => Boolean(
  item.productName || item.productNumber || item.variantCombination,
)
const productTitle = (item: SearchableSelectItem) => item.productName ?? item.label
const productMeta = (item: SearchableSelectItem) => [item.productNumber, item.variantCombination]
  .filter((value): value is string => Boolean(value))
  .join(' · ')
const productTooltip = (item: SearchableSelectItem) => [productTitle(item), productMeta(item)]
  .filter(Boolean)
  .join(' · ')

const selectId = `searchable-select-${useId()}`
const open = ref(false)
const loadingMore = ref(false)
let viewport: HTMLElement | null = null

const loadMore = async () => {
  if (!open.value || !props.hasMore || !props.loadMore || loadingMore.value || props.loading) {
    return
  }

  loadingMore.value = true
  try {
    await props.loadMore()
  } finally {
    loadingMore.value = false
  }
}

const updateOpen = (value: boolean) => {
  open.value = value

  if (!value) {
    detachViewportListener()
    return
  }

  void attachViewportListener()
}

const handleViewportScroll = (event: Event) => {
  const target = event.currentTarget as HTMLElement
  const remainingScroll = target.scrollHeight - target.scrollTop - target.clientHeight

  if (remainingScroll <= 8) {
    void loadMore()
  }
}

const attachViewportListener = async () => {
  await nextTick()
  const trigger = document.querySelector(`[data-searchable-select="${selectId}"]`)
  const contentId = trigger?.getAttribute('aria-controls')
  const content = contentId ? document.getElementById(contentId) : null

  viewport = content?.querySelector<HTMLElement>('[data-slot="viewport"]') ?? null
  viewport?.addEventListener('scroll', handleViewportScroll)
}

const detachViewportListener = () => {
  viewport?.removeEventListener('scroll', handleViewportScroll)
  viewport = null
}

onBeforeUnmount(detachViewportListener)
</script>

<template>
  <USelectMenu
    :id="selectId"
    :model-value="modelValue"
    :items="items"
    :data-searchable-select="selectId"
    value-key="value"
    :multiple="multiple"
    :placeholder="placeholder"
    :search-input="{
      icon: 'i-lucide-search',
      placeholder: searchPlaceholder
    }"
    :search-term="searchTerm"
    :filter-fields="['label', 'productName', 'productNumber', 'variantCombination']"
    :ui="{
      base: 'w-[320px] max-w-full cursor-pointer',
      content: 'w-[320px] max-w-[calc(100vw-2rem)]',
      item: 'cursor-pointer data-disabled:cursor-not-allowed hover:before:bg-elevated/70 data-highlighted:before:bg-elevated/70'
    }"
    v-bind="$attrs"
    @update:model-value="updateModelValue"
    @update:search-term="updateSearchTerm"
    @update:open="updateOpen"
  >
    <template v-if="showPlaceholderWhenSelected" #default>
      <span class="text-muted">
        {{ placeholder }}
      </span>
    </template>
    <template #item-label="{ item }">
      <UTooltip
        v-if="isProductItem(item)"
        :delay-duration="300"
        :ui="{
          content: 'h-auto max-w-[360px] bg-slate-900 px-0 py-0 text-white shadow-xl ring-slate-700',
          arrow: 'fill-slate-900 stroke-slate-700'
        }"
      >
        <span class="block w-full min-w-0">
          <span class="block truncate font-semibold text-highlighted">
            {{ productTitle(item) }}
          </span>
          <span
            v-if="productMeta(item)"
            class="block truncate text-xs leading-4 text-muted"
          >
            {{ productMeta(item) }}
          </span>
        </span>
        <template #content>
          <div class="max-w-[360px] whitespace-normal break-words px-3 py-2 text-sm leading-5 text-white">
            <p class="font-semibold">
              {{ productTitle(item) }}
            </p>
            <p v-if="item.productNumber" class="text-slate-300">
              {{ item.productNumber }}
            </p>
            <p v-if="item.variantCombination" class="text-slate-300">
              {{ item.variantCombination }}
            </p>
          </div>
        </template>
      </UTooltip>
      <span v-else>
        {{ item.label }}
      </span>
    </template>
  </USelectMenu>
</template>

<style scoped>
:deep([data-slot='base']) {
  cursor: pointer !important;
}

</style>
