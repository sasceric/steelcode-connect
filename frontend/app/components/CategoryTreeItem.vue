<script setup lang="ts">
import Sortable from 'sortablejs'
import type { SortableEvent } from 'sortablejs'

defineOptions({
  name: 'CategoryTreeItem',
})

type CategoryNode = {
  id: string
  parentId: string | null
  name: string
  position: number
  active: boolean
  visible: boolean
  translations: Record<string, { name: string }>
  hasChildren: boolean
  children: CategoryNode[]
  childrenLoaded: boolean
  childrenLoading: boolean
}

const props = defineProps<{
  node: CategoryNode
  depth?: number
  selectedLocale: string
  defaultLocale: string
}>()

const emit = defineEmits<{
  move: [payload: { categoryId: string; parentId: string | null; position: number }]
  create: [payload: { parentId: string | null; position: number | null }]
  load: [categoryId: string]
  select: [payload: { id: string; selected: boolean }]
}>()

const { t } = useI18n()
const selectedIds = defineModel<string[]>('selectedIds', { required: true })
const expandedIds = defineModel<string[]>('expandedIds', { required: true })
const childrenList = ref<HTMLElement | null>(null)
let sortable: Sortable | null = null

const selected = computed(() => selectedIds.value.includes(props.node.id))
const expanded = computed({
  get: () => expandedIds.value.includes(props.node.id),
  set: (value: boolean) => {
    expandedIds.value = value
      ? Array.from(new Set([...expandedIds.value, props.node.id]))
      : expandedIds.value.filter(id => id !== props.node.id)
  },
})
const name = computed(() =>
  props.node.translations?.[props.selectedLocale]?.name
  || props.node.translations?.[props.defaultLocale]?.name
  || props.node.name
)
const actionItems = computed(() => [
  [
    {
      label: t('categories.addChild'),
      icon: 'i-lucide-folder-plus',
      onSelect: () =>
        emit('create', {
          parentId: props.node.id,
          position: props.node.childrenLoaded ? props.node.children.length : null,
        }),
    },
  ],
  [
    {
      label: t('categories.addBefore'),
      icon: 'i-lucide-arrow-up',
      onSelect: () =>
        emit('create', {
          parentId: props.node.parentId,
          position: props.node.position,
        }),
    },
    {
      label: t('categories.addAfter'),
      icon: 'i-lucide-arrow-down',
      onSelect: () =>
        emit('create', {
          parentId: props.node.parentId,
          position: props.node.position + 1,
        }),
    },
  ],
])

const nodeAndLoadedDescendantIds = (node: CategoryNode): string[] => [
  node.id,
  ...node.children.flatMap(nodeAndLoadedDescendantIds),
]

const updateSelection = (value: boolean) => {
  const ids = nodeAndLoadedDescendantIds(props.node)
  if (value) {
    selectedIds.value = Array.from(new Set([...selectedIds.value, ...ids]))
    return
  }
  selectedIds.value = selectedIds.value.filter(id => !ids.includes(id))
}

const toggle = () => {
  expanded.value = !expanded.value
  if (expanded.value && props.node.hasChildren && !props.node.childrenLoaded) {
    emit('load', props.node.id)
  }
}

const destroySortable = () => {
  sortable?.destroy()
  sortable = null
}

const initializeSortable = () => {
  destroySortable()
  if (!childrenList.value) return

  sortable = Sortable.create(childrenList.value, {
    group: 'category-tree',
    animation: 150,
    draggable: '.category-tree-node',
    handle: '.category-drag-handle',
    chosenClass: 'bg-elevated',
    ghostClass: 'opacity-35',
    fallbackClass: 'shadow-lg',
    forceFallback: true,
    fallbackOnBody: true,
    onEnd: (event: SortableEvent) => {
      if (event.from === event.to && event.oldIndex === event.newIndex) return

      const categoryId = event.item.dataset.categoryId
      if (!categoryId || event.newIndex === undefined) return

      emit('move', {
        categoryId,
        parentId: event.to.dataset.parentId || null,
        position: event.newIndex,
      })
    },
  })
}

onMounted(() => {
  void nextTick(initializeSortable)
})

watch(
  () => [expanded.value, props.node.childrenLoaded, props.node.children.length],
  ([isExpanded]) => {
    if (isExpanded && props.node.hasChildren && !props.node.childrenLoaded && !props.node.childrenLoading) {
      emit('load', props.node.id)
    }
    void nextTick(initializeSortable)
  },
  { immediate: true },
)

onBeforeUnmount(destroySortable)
</script>

<template>
  <li class="category-tree-node" :data-category-id="node.id">
    <div
      class="flex min-h-10 items-center gap-2 rounded-md px-2 hover:bg-elevated/60"
      :style="{ paddingLeft: `${(depth || 0) * 1.5 + 0.5}rem` }"
    >
      <button
        type="button"
        class="category-drag-handle flex shrink-0 cursor-grab text-muted active:cursor-grabbing"
        :aria-label="t('categories.dragCategory', { name })"
      >
        <UIcon name="i-lucide-grip-vertical" class="size-4" />
      </button>
      <UButton
        v-if="node.hasChildren"
        :icon="node.childrenLoading ? 'i-lucide-loader-circle' : expanded ? 'i-lucide-chevron-down' : 'i-lucide-chevron-right'"
        color="neutral"
        variant="ghost"
        size="xs"
        :class="node.childrenLoading ? 'animate-spin' : ''"
        :aria-label="expanded ? t('categories.collapse') : t('categories.expand')"
        @click.stop="toggle"
      />
      <span v-else class="size-6 shrink-0" />
      <UCheckbox
        :model-value="selected"
        :aria-label="t('categories.selectCategory', { name })"
        @click.stop
        @update:model-value="updateSelection($event === true)"
      />
      <UIcon name="i-lucide-folder" class="size-4 shrink-0 text-primary" />
      <NuxtLink
        :to="`/catalogue/categories/${node.id}`"
        class="min-w-0 flex-1 truncate font-medium text-highlighted hover:text-primary"
      >
        {{ name }}
      </NuxtLink>
      <UBadge v-if="!node.active" color="neutral" variant="subtle" size="xs">
        Draft
      </UBadge>
      <UBadge v-else-if="!node.visible" color="warning" variant="subtle" size="xs">
        Hidden
      </UBadge>
      <UDropdownMenu :items="actionItems" :content="{ align: 'end' }">
        <UButton
          icon="i-lucide-ellipsis-vertical"
          color="neutral"
          variant="ghost"
          size="xs"
          :aria-label="t('common.edit')"
        />
      </UDropdownMenu>
    </div>
    <div
      v-if="expanded && node.childrenLoading"
      class="py-2 text-sm text-muted"
      :style="{ paddingLeft: `${((depth || 0) + 1) * 1.5 + 2}rem` }"
    >
      {{ t('common.loading') }}
    </div>
    <ul
      v-if="expanded && node.children.length"
      ref="childrenList"
      :data-parent-id="node.id"
      class="space-y-1"
    >
      <CategoryTreeItem
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :depth="(depth || 0) + 1"
        :selected-locale="selectedLocale"
        :default-locale="defaultLocale"
        v-model:selected-ids="selectedIds"
        v-model:expanded-ids="expandedIds"
        @move="emit('move', $event)"
        @create="emit('create', $event)"
        @load="emit('load', $event)"
      />
    </ul>
  </li>
</template>
