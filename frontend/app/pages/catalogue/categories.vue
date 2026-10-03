<script setup lang="ts">
import Sortable from 'sortablejs'
import type { SortableEvent } from 'sortablejs'
import CategoryTreeItem from '~/components/CategoryTreeItem.vue'

type Category = {
  id: string
  parentId: string | null
  name: string
  position: number
  active: boolean
  visible: boolean
  hasChildren?: boolean
  translations: Record<string, { name: string }>
}

type CategoryNode = Category & {
  hasChildren: boolean
  children: CategoryNode[]
  childrenLoaded: boolean
  childrenLoading: boolean
}

const localePath = useLocalePath()
const { t } = useI18n()
const route = useRoute()
const auth = useAuth()
const toast = useAppToast()
const open = ref(false)
const saving = ref(false)
const deleting = ref(false)
const name = ref('')
const createParentId = ref<string | null>(null)
const createPosition = ref<number | null>(null)
const selectedCategoryIds = ref<string[]>([])
const expandedCategoryIds = ref<string[]>([])
const bulkDeleteOpen = ref(false)
const selectedLocale = ref(auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const defaultLocale = computed(() => auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const validation = useFormValidation()
const validationErrors = validation.errors

const { data, status, refresh } = await useAsyncData('category-roots', () =>
  apiFetch<{ categories: Category[] }>('/categories?parentId=')
)
const { data: localesData } = await useAsyncData('category-locales', () =>
  apiFetch<{ locales: { code: string, label: string }[] }>('/property-groups/locales')
)

const locales = computed(() => localesData.value?.locales ?? [])
const tree = ref<CategoryNode[]>([])
const rootTree = ref<HTMLElement | null>(null)
let rootSortable: Sortable | null = null
const categoryNode = (category: Category): CategoryNode => ({
  ...category,
  hasChildren: Boolean(category.hasChildren),
  children: [],
  childrenLoaded: false,
  childrenLoading: false
})
const findNode = (nodes: CategoryNode[], id: string): CategoryNode | null => {
  for (const node of nodes) {
    if (node.id === id) return node
    const child = findNode(node.children, id)
    if (child) return child
  }
  return null
}

const loadChildren = async (categoryId: string) => {
  const node = findNode(tree.value, categoryId)
  if (!node || node.childrenLoaded || node.childrenLoading) return

  node.childrenLoading = true
  try {
    const { categories } = await apiFetch<{ categories: Category[] }>(
      `/categories?parentId=${encodeURIComponent(categoryId)}`
    )
    node.children = categories.map(categoryNode)
    node.childrenLoaded = true
    if (selectedCategoryIds.value.includes(categoryId)) {
      selectedCategoryIds.value = Array.from(new Set([
        ...selectedCategoryIds.value,
        ...node.children.map(child => child.id),
      ]))
    }
  } catch {
    toast.error(t('common.error'), t('common.tryAgain'))
  } finally {
    node.childrenLoading = false
  }
}

const create = async () => {
  if (
    !validation.requireFields(
      [
        {
          field: 'name',
          value: name.value,
          label: t('products.name'),
          message: t('common.requiredField')
        }
      ],
      toast,
      t('common.error'),
      fields => t('common.requiredFields', { fields: fields.join(', ') })
    )
  ) {
    return
  }

  saving.value = true
  try {
    const { category } = await apiFetch<{ category: Category }>('/categories', {
      method: 'POST',
      body: {
        name: name.value,
        parentId: createParentId.value,
        position: createPosition.value
      }
    })
    await refresh()
    open.value = false
    name.value = ''
    await navigateTo(localePath(`/catalogue/categories/${category.id}`))
  } catch (error: any) {
    validation.notifyApiError(error, toast, t('common.error'), t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}

const containsNode = (node: CategoryNode, id: string): boolean =>
  node.id === id || node.children.some(child => containsNode(child, id))

const childList = (parentId: string | null): CategoryNode[] | null => {
  if (parentId === null) return tree.value
  return findNode(tree.value, parentId)?.children ?? null
}

const normalizePositions = (nodes: CategoryNode[]) => {
  nodes.forEach((node, index) => {
    node.position = index
  })
}

const moveInTree = (payload: { categoryId: string, parentId: string | null, position: number | null }): boolean => {
  const node = findNode(tree.value, payload.categoryId)
  if (!node || (payload.parentId && containsNode(node, payload.parentId))) return false

  const source = childList(node.parentId)
  const target = childList(payload.parentId)
  if (!source || !target) return false

  const sourceIndex = source.findIndex(item => item.id === node.id)
  if (sourceIndex < 0) return false

  source.splice(sourceIndex, 1)
  const position = Math.min(Math.max(payload.position ?? target.length, 0), target.length)
  node.parentId = payload.parentId
  target.splice(position, 0, node)
  normalizePositions(source)
  if (source !== target) normalizePositions(target)

  return true
}

const move = async (payload: { categoryId: string, parentId: string | null, position: number | null }) => {
  if (!moveInTree(payload)) {
    await refresh()
    return
  }

  try {
    await apiFetch(`/categories/${payload.categoryId}/move`, {
      method: 'POST',
      body: payload
    })
    toast.success(t('common.saved'), t('common.changesSaved'))
  } catch (error: any) {
    await refresh()
    toast.error(t('common.error'), error?.data?.message || t('common.tryAgain'))
  }
}

const destroyRootSortable = () => {
  rootSortable?.destroy()
  rootSortable = null
}

const initializeRootSortable = () => {
  destroyRootSortable()
  if (!rootTree.value) return

  rootSortable = Sortable.create(rootTree.value, {
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

      void move({
        categoryId,
        parentId: event.to.dataset.parentId || null,
        position: event.newIndex
      })
    }
  })
}

const deleteSelected = async () => {
  deleting.value = true
  try {
    await apiFetch('/categories/bulk', {
      method: 'DELETE',
      body: { ids: selectedCategoryIds.value }
    })
    selectedCategoryIds.value = []
    bulkDeleteOpen.value = false
    await refresh()
    toast.success(t('categories.deleted'), t('common.changesSaved'))
  } catch {
    toast.error(t('common.error'), t('categories.deleteFailed'))
  } finally {
    deleting.value = false
  }
}

const openCreate = (placement?: { parentId: string | null, position: number | null }) => {
  selectedLocale.value = auth.tenant.value?.defaultSnippetLocale || 'en-GB'
  name.value = ''
  validation.clear()
  createParentId.value = placement?.parentId || null
  createPosition.value = placement?.position ?? null
  open.value = true
}

watch(
  data,
  (value) => {
    tree.value = (value?.categories ?? []).map(categoryNode)
    selectedCategoryIds.value = []
  },
  { immediate: true }
)

watch(tree, () => void nextTick(initializeRootSortable))

onMounted(() => {
  void nextTick(initializeRootSortable)
})

onBeforeUnmount(destroyRootSortable)

watch(
  () => route.params.id,
  (categoryId, previousCategoryId) => {
    if (!categoryId && previousCategoryId) {
      void refresh()
    }
  }
)
</script>

<template>
  <NuxtPage v-if="$route.params.id" />

  <div v-else class="space-y-6">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold text-highlighted">
          {{ t('nav.categories') }}
        </h1>
        <p class="mt-1 text-sm text-muted">
          {{ t('categories.description') }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <LocaleSelect
          v-model="selectedLocale"
          :options="locales"
          class="w-56"
        />
        <UButton :label="t('common.add')" @click="openCreate()" />
      </div>
    </div>

    <div v-if="selectedCategoryIds.length" class="flex flex-wrap items-center gap-3">
      <UButton
        :label="t('categories.bulkDelete', { count: selectedCategoryIds.length })"
        color="error"
        variant="outline"
        @click="bulkDeleteOpen = true"
      />
    </div>

    <div v-if="status === 'pending'" class="py-12 text-center text-muted">
      {{ t('common.loading') }}
    </div>
    <div v-else class="grid items-start gap-6 lg:grid-cols-2">
      <UPageCard v-if="!tree.length" :title="t('categories.emptyTitle')">
        <template #footer>
          <UButton :label="t('common.add')" @click="openCreate()" />
        </template>
      </UPageCard>
      <UPageCard v-else>
        <ul ref="rootTree" data-parent-id="" class="space-y-1">
          <CategoryTreeItem
            v-for="node in tree"
            :key="node.id"
            :node="node"
            :selected-locale="selectedLocale"
            :default-locale="defaultLocale"
            v-model:selected-ids="selectedCategoryIds"
            v-model:expanded-ids="expandedCategoryIds"
            @move="move"
            @create="openCreate"
            @load="loadChildren"
          />
        </ul>
      </UPageCard>

      <UPageCard class="overflow-hidden">
        <div class="flex flex-col items-center justify-center px-6 py-10 text-center">
          <div class="mb-5 rounded-full bg-primary/10 p-5">
            <UIcon name="i-lucide-git-branch" class="size-12 text-primary" />
          </div>
          <h2 class="text-lg font-semibold text-highlighted">
            {{ t('categories.treeTipTitle') }}
          </h2>
          <p class="mt-2 max-w-sm text-sm leading-6 text-muted">
            {{ t('categories.treeTipDescription') }}
          </p>
        </div>
      </UPageCard>
    </div>
  </div>

  <UModal v-model:open="open" :title="t('categories.newCategory')">
    <template #body>
      <UForm class="space-y-5" @submit.prevent="create">
        <ModalLocaleField
          v-model="selectedLocale"
          :options="locales"
          :default-locale="defaultLocale"
          :active="open"
          creating
        />
        <UFormField :label="t('products.name')" :error="validationErrors.name" required>
          <UInput
            v-model="name"
            class="w-full"
            @update:model-value="validation.clear('name')"
          />
        </UFormField>
        <div class="flex justify-end gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            @click="open = false"
          />
          <UButton :label="t('common.save')" type="submit" :loading="saving" />
        </div>
      </UForm>
    </template>
  </UModal>

  <UModal v-model:open="bulkDeleteOpen" :title="t('categories.deleteTitle')">
    <template #body>
      <div class="space-y-5">
        <p class="text-sm text-muted">
          {{ t('categories.deleteDescription', { count: selectedCategoryIds.length }) }}
        </p>
        <div class="flex justify-end gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            :disabled="deleting"
            @click="bulkDeleteOpen = false"
          />
          <UButton
            :label="t('categories.bulkDelete', { count: selectedCategoryIds.length })"
            color="error"
            :loading="deleting"
            @click="deleteSelected"
          />
        </div>
      </div>
    </template>
  </UModal>
</template>
