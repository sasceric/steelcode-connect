<script setup lang="ts">
type Category = {
  id: string
  parentId: string | null
  name: string
  position: number
  translations: Record<string, { name: string }>
}

type CategoryRow = Category & {
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
const rows = computed<CategoryRow[]>(() => {
  const children = new Map<string | null, Category[]>()
  for (const category of props.categories) {
    const siblings = children.get(category.parentId) ?? []
    siblings.push(category)
    children.set(category.parentId, siblings)
  }
  for (const siblings of children.values()) {
    siblings.sort((left, right) => left.position - right.position)
  }

  const tree: CategoryRow[] = []
  const addRows = (parentId: string | null, depth: number) => {
    for (const category of children.get(parentId) ?? []) {
      tree.push({
        ...category,
        depth,
        label:
          category.translations[props.locale]?.name ||
          category.translations[props.fallbackLocale]?.name ||
          category.name,
      })
      addRows(category.id, depth + 1)
    }
  }
  addRows(null, 0)

  const query = search.value.trim().toLocaleLowerCase()
  return query
    ? tree.filter((category) => category.label.toLocaleLowerCase().includes(query))
    : tree
})

const toggle = (categoryId: string, selected: boolean | 'indeterminate') => {
  emit(
    'update:modelValue',
    selected
      ? [...new Set([...props.modelValue, categoryId])]
      : props.modelValue.filter((id) => id !== categoryId),
  )
}
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
          <p v-if="!rows.length" class="p-3 text-sm text-muted">
            {{ emptyLabel }}
          </p>
          <label
            v-for="category in rows"
            :key="category.id"
            class="flex cursor-pointer items-center gap-3 rounded px-2 py-2 hover:bg-elevated"
            :style="{ paddingLeft: `${category.depth * 24 + 8}px` }"
          >
            <UCheckbox
              :model-value="modelValue.includes(category.id)"
              @update:model-value="
                (value: boolean | 'indeterminate') => toggle(category.id, value)
              "
            />
            <UIcon name="i-lucide-folder" class="size-4 text-muted" />
            <span class="text-sm text-highlighted">{{ category.label }}</span>
          </label>
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
