<script setup lang="ts">
type Category = {
  id: string
  parentId: string | null
  name: string
  position: number
  hasChildren?: boolean
  translations: Record<string, { name: string }>
}

type CategoryNode = Category & {
  children: CategoryNode[]
  childrenLoaded: boolean
  childrenLoading: boolean
}

type CategoryRow = CategoryNode & {
  depth: number
  label: string
}

const props = defineProps<{
  open: boolean
  modelValue: string[]
  categories: Category[]
  locale: string
  fallbackLocale: string
  title: string
  searchPlaceholder: string
  emptyLabel: string
  saveLabel: string
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'update:modelValue': [value: string[]]
}>()

const search = ref('')
const debouncedSearch = ref('')
const searchResults = ref<Category[]>([])
const searchLoading = ref(false)
const tree = ref<CategoryNode[]>([])
const expandedIds = ref<string[]>([])
let searchDebounce: ReturnType<typeof setTimeout> | undefined

const categoryNode = (category: Category): CategoryNode => ({
  ...category,
  children: [],
  childrenLoaded: false,
  childrenLoading: false,
})

const categoryLabel = (category: Category) =>
  category.translations[props.locale]?.name
  || category.translations[props.fallbackLocale]?.name
  || category.name

const findNode = (nodes: CategoryNode[], categoryId: string): CategoryNode | undefined => {
  for (const node of nodes) {
    if (node.id === categoryId) {
      return node
    }

    const child = findNode(node.children, categoryId)
    if (child) {
      return child
    }
  }
}

const loadRootCategories = async () => {
  if (tree.value.length) {
    return
  }

  const { categories } = await apiFetch<{ categories: Category[] }>('/categories?parentId=')
  tree.value = categories.map(categoryNode)
}

const loadChildren = async (categoryId: string) => {
  const node = findNode(tree.value, categoryId)
  if (!node || node.childrenLoaded || node.childrenLoading) {
    return
  }

  node.childrenLoading = true
  try {
    const { categories } = await apiFetch<{ categories: Category[] }>(
      `/categories?parentId=${encodeURIComponent(categoryId)}`
    )
    node.children = categories.map(categoryNode)
    node.childrenLoaded = true
  } finally {
    node.childrenLoading = false
  }
}

const toggleExpanded = (categoryId: string) => {
  const expanded = expandedIds.value.includes(categoryId)
  expandedIds.value = expanded
    ? expandedIds.value.filter(id => id !== categoryId)
    : [...expandedIds.value, categoryId]

  if (!expanded) {
    void loadChildren(categoryId)
  }
}

const treeRows = computed<CategoryRow[]>(() => {
  const rows: CategoryRow[] = []
  const append = (nodes: CategoryNode[], depth: number) => {
    for (const node of nodes) {
      rows.push({ ...node, depth, label: categoryLabel(node) })
      if (expandedIds.value.includes(node.id)) {
        append(node.children, depth + 1)
      }
    }
  }

  append(tree.value, 0)
  return rows
})

const rows = computed<CategoryRow[]>(() => {
  if (!debouncedSearch.value) {
    return treeRows.value
  }

  return searchResults.value.map(category => ({
    ...categoryNode(category),
    depth: 0,
    label: categoryLabel(category),
  }))
})

const toggle = (categoryId: string, selected: boolean | 'indeterminate') => {
  emit(
    'update:modelValue',
    selected
      ? [...new Set([...props.modelValue, categoryId])]
      : props.modelValue.filter(id => id !== categoryId),
  )
}

watch(
  () => props.categories,
  (categories) => {
    if (!categories.length || tree.value.length) {
      return
    }

    tree.value = categories.map(categoryNode)
  },
  { immediate: true },
)

watch(search, (value) => {
  if (searchDebounce) {
    clearTimeout(searchDebounce)
  }

  searchDebounce = setTimeout(() => {
    debouncedSearch.value = value.trim()
  }, 250)
})

watch(debouncedSearch, async (value) => {
  if (!value) {
    searchResults.value = []
    return
  }

  searchLoading.value = true
  try {
    const { categories } = await apiFetch<{ categories: Category[] }>(
      `/categories?search=${encodeURIComponent(value)}`
    )
    searchResults.value = categories
  } finally {
    searchLoading.value = false
  }
})

watch(
  () => props.open,
  (open) => {
    if (open) {
      void loadRootCategories()
    }
  },
)

onBeforeUnmount(() => {
  if (searchDebounce) {
    clearTimeout(searchDebounce)
  }
})
</script>

<template>
  <UModal :open="open" :title="title" @update:open="emit('update:open', $event)">
    <template #body>
      <div class="space-y-4">
        <UInput
          v-model="search"
          icon="i-lucide-search"
          :placeholder="searchPlaceholder"
          class="w-full"
        />
        <div class="max-h-96 space-y-1 overflow-y-auto rounded-md border border-default p-2">
          <p v-if="searchLoading" class="p-3 text-sm text-muted">
            {{ $t('common.loading') }}
          </p>
          <p v-else-if="!rows.length" class="p-3 text-sm text-muted">
            {{ emptyLabel }}
          </p>
          <div
            v-for="category in rows"
            :key="category.id"
            class="flex min-h-10 items-center gap-2 rounded px-2 hover:bg-elevated"
            :style="{ paddingLeft: `${category.depth * 24 + 8}px` }"
          >
            <UButton
              v-if="!debouncedSearch && category.hasChildren"
              :icon="category.childrenLoading ? 'i-lucide-loader-circle' : expandedIds.includes(category.id) ? 'i-lucide-chevron-down' : 'i-lucide-chevron-right'"
              color="neutral"
              variant="ghost"
              size="xs"
              :class="category.childrenLoading ? 'animate-spin' : ''"
              :aria-label="expandedIds.includes(category.id) ? $t('categories.collapse') : $t('categories.expand')"
              @click="toggleExpanded(category.id)"
            />
            <span v-else class="size-6 shrink-0" />
            <UCheckbox
              :model-value="modelValue.includes(category.id)"
              :aria-label="category.label"
              @update:model-value="toggle(category.id, $event)"
            />
            <UIcon name="i-lucide-folder" class="size-4 shrink-0 text-muted" />
            <span class="min-w-0 truncate text-sm text-highlighted">
              {{ category.label }}
            </span>
          </div>
        </div>
      </div>
    </template>
    <template #footer>
      <div class="flex justify-end">
        <UButton :label="saveLabel" @click="emit('update:open', false)" />
      </div>
    </template>
  </UModal>
</template>
