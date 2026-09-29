<script setup lang="ts">
type SearchableSelectItem = {
  label: string
  value: string
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
    :ui="{ base: 'cursor-pointer' }"
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
  </USelectMenu>
</template>

<style scoped>
:deep([data-slot='base']) {
  cursor: pointer !important;
}
</style>
