<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { Row, RowSelectionState, SortingState } from '@tanstack/table-core'

type Manufacturer = {
  id: string
  name: string
  seoUrl: string
  website: string | null
  mediaId: string | null
  translations: Record<string, { name: string, seoUrl: string | null }>
}

const localePath = useLocalePath()
const { t } = useI18n()
const auth = useAuth()
const toast = useAppToast()
const route = useRoute()
const selectedLocale = ref(auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const defaultLocale = computed(() => auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const open = ref(false)
const saving = ref(false)
const name = ref('')
const nameFilter = ref('')
const debouncedNameFilter = ref('')
const manufacturerSorting = ref<SortingState>([])
const manufacturerRowSelection = ref<RowSelectionState>({})
const bulkDeleteOpen = ref(false)
const deleting = ref<Manufacturer | null>(null)
const currentPage = ref(1)
const pagination = reactive({ pageSize: 25 })
const validation = useFormValidation()
const validationErrors = validation.errors
const deleteOpen = computed({
  get: () => deleting.value !== null,
  set: (value: boolean) => {
    if (!value) deleting.value = null
  }
})
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')

const serverSorting = computed(() => {
  const sorting = manufacturerSorting.value[0]

  return ['name', 'website'].includes(sorting?.id || '')
    ? sorting
    : undefined
})
const manufacturerListUrl = computed(() => {
  const params = new URLSearchParams({
    page: String(currentPage.value),
    limit: String(pagination.pageSize)
  })

  if (debouncedNameFilter.value) {
    params.set('search', debouncedNameFilter.value)
  }
  if (serverSorting.value) {
    params.set('sort', serverSorting.value.id)
    params.set('direction', serverSorting.value.desc ? 'DESC' : 'ASC')
  }

  return `/manufacturers?${params.toString()}`
})
const { data, status, refresh } = await useAsyncData('manufacturers', () =>
  apiFetch<{ manufacturers: Manufacturer[], pagination: { total: number } }>(manufacturerListUrl.value)
)
const { data: localesData } = await useAsyncData('manufacturer-locales', () =>
  apiFetch<{ locales: { code: string, label: string }[] }>('/products/locales')
)
const locales = computed(() => localesData.value?.locales ?? [])
const manufacturers = computed(() =>
  (data.value?.manufacturers ?? []).map((manufacturer) => {
    const translation
      = manufacturer.translations[selectedLocale.value]
        || manufacturer.translations[defaultLocale.value]
    return {
      ...manufacturer,
      name: translation?.name || manufacturer.name,
      seoUrl: translation?.seoUrl || manufacturer.seoUrl
    }
  })
)
const selectedManufacturerIds = computed(() =>
  Object.entries(manufacturerRowSelection.value)
    .filter(([, selected]) => selected)
    .map(([id]) => id)
)
const paginatedManufacturers = manufacturers
const totalResults = computed(() => data.value?.pagination?.total ?? 0)
const getActions = (row: Row<Manufacturer>) => [
  [
    {
      label: t('products.open'),
      icon: 'i-lucide-arrow-up-right',
      onSelect: () => navigateTo(localePath(`/catalogue/manufacturers/${row.original.id}`))
    }
  ],
  [
    {
      label: t('common.delete'),
      icon: 'i-lucide-trash-2',
      color: 'error' as const,
      onSelect: () => (deleting.value = row.original)
    }
  ]
]
const columns: TableColumn<Manufacturer>[] = [
  {
    accessorKey: 'name',
    header: () => t('manufacturers.name'),
    cell: ({ row }) =>
      h(
        'div',
        { class: 'flex min-w-0 items-center gap-3' },
        [
          h('img', {
            src: row.original.mediaId
              ? `/api/v1/media/${row.original.mediaId}/file`
              : '/placeholder-light.webp',
            alt: row.original.name,
            class: 'size-8 shrink-0 rounded-md border border-default object-cover'
          }),
          h(
            'button',
            {
              class: 'cursor-pointer truncate text-left font-medium text-highlighted hover:text-primary',
              onClick: () => navigateTo(localePath(`/catalogue/manufacturers/${row.original.id}`))
            },
            row.original.name
          )
        ]
      )
  },
  { accessorKey: 'website', header: () => t('common.website'), cell: ({ row }) => row.original.website || '—' },
  {
    accessorKey: 'seoUrl',
    enableSorting: false,
    header: () => t('productSeo.url')
  },
  {
    id: 'actions',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) =>
      h(
        'div',
        { class: 'flex justify-end' },
        h(UDropdownMenu, { items: getActions(row), content: { align: 'end' } }, () =>
          h(UButton, {
            'icon': 'i-lucide-ellipsis-vertical',
            'color': 'neutral',
            'variant': 'ghost',
            'aria-label': t('common.edit')
          })
        )
      )
  }
]
const openCreate = () => {
  selectedLocale.value = defaultLocale.value
  name.value = ''
  validation.clear()
  open.value = true
}

const create = async () => {
  if (
    !validation.requireFields(
      [
        {
          field: 'name',
          value: name.value,
          label: t('manufacturers.name'),
          message: t('common.requiredField')
        }
      ],
      toast,
      t('manufacturers.createFailed'),
      fields => t('common.requiredFields', { fields: fields.join(', ') })
    )
  ) {
    return
  }

  saving.value = true
  try {
    const { manufacturer } = await apiFetch<{ manufacturer: Manufacturer }>('/manufacturers', {
      method: 'POST',
      body: { name: name.value }
    })
    open.value = false
    name.value = ''
    await navigateTo(localePath(`/catalogue/manufacturers/${manufacturer.id}`))
  } catch (error: unknown) {
    validation.notifyApiError(error, toast, t('manufacturers.createFailed'), t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}

const remove = async () => {
  if (!deleting.value) return
  try {
    await apiFetch(`/manufacturers/${deleting.value.id}`, { method: 'DELETE' })
    deleting.value = null
    await refresh()
    toast.success(t('common.saved'), t('common.changesSaved'))
  } catch (error: unknown) {
    const message = (error as { data?: { message?: unknown } })?.data?.message

    toast.error(
      t('common.error'),
      typeof message === 'string' && message !== '' ? message : t('common.tryAgain')
    )
  }
}

const removeSelectedManufacturers = async () => {
  if (!selectedManufacturerIds.value.length) {
    return
  }

  try {
    await Promise.all(
      selectedManufacturerIds.value.map(id =>
        apiFetch(`/manufacturers/${id}`, { method: 'DELETE' })
      )
    )
    manufacturerRowSelection.value = {}
    bulkDeleteOpen.value = false
    await refresh()
    toast.success(t('common.saved'), t('common.changesSaved'))
  } catch (error: unknown) {
    const message = (error as { data?: { message?: unknown } })?.data?.message

    toast.error(
      t('common.error'),
      typeof message === 'string' && message !== '' ? message : t('common.tryAgain')
    )
  }
}

let searchDebounce: ReturnType<typeof setTimeout> | undefined

watch(nameFilter, (value) => {
  currentPage.value = 1
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    debouncedNameFilter.value = value.trim()
  }, 300)
})
watch(manufacturerSorting, () => {
  currentPage.value = 1
})
watch(
  () => pagination.pageSize,
  () => {
    currentPage.value = 1
  }
)
watch(manufacturerListUrl, () => {
  void refresh()
})

watch(
  () => route.params.id,
  (manufacturerId, previousManufacturerId) => {
    if (!manufacturerId && previousManufacturerId) void refresh()
  }
)
</script>

<template>
  <NuxtPage v-if="route.params.id" />

  <div v-else>
    <AppDataTable
      v-model:row-selection="manufacturerRowSelection"
      v-model:sorting="manufacturerSorting"
      :data="paginatedManufacturers"
      :columns="columns"
      :get-row-id="(row) => row.id"
      :loading="status === 'pending'"
      server-sorting
      selectable
      table-key="catalogue-manufacturers"
      :column-labels="{
        name: t('manufacturers.name'),
        website: t('common.website'),
        seoUrl: t('productSeo.url')
      }"
    >
      <template #header>
        <div class="flex w-full flex-wrap items-center justify-between gap-3">
          <div class="flex min-w-0 flex-wrap items-center gap-2">
            <p class="whitespace-nowrap text-sm font-medium text-highlighted">
              {{ t('nav.manufacturers') }} ({{ totalResults }})
            </p>
            <UInput
              v-model="nameFilter"
              icon="i-lucide-search"
              :placeholder="t('manufacturers.search')"
              class="w-full sm:w-72"
            />
            <UButton
              v-if="selectedManufacturerIds.length"
              :label="t('products.bulkDelete', { count: selectedManufacturerIds.length })"
              color="error"
              variant="outline"
              @click="bulkDeleteOpen = true"
            />
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <LocaleSelect
              v-model="selectedLocale"
              :options="locales"
              class="w-full sm:w-56"
            />
            <UButton :label="t('manufacturers.add')" @click="openCreate" />
          </div>
        </div>
      </template>

      <template #footer>
        <TablePaginationFooter
          v-model:page="currentPage"
          v-model:page-size="pagination.pageSize"
          :total="totalResults"
          class="border-t-0 pt-0"
        />
      </template>
    </AppDataTable>
  </div>

  <UModal v-model:open="open" :title="t('manufacturers.add')">
    <template #body>
      <UForm class="space-y-4" @submit.prevent="create">
        <ModalLocaleField
          v-model="selectedLocale"
          :options="locales"
          :default-locale="defaultLocale"
          :active="open"
          creating
        />
        <UFormField :label="t('manufacturers.name')" :error="validationErrors.name" required>
          <UInput
            v-model="name"
            autofocus
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

  <ConfirmationModal
    v-model:open="deleteOpen"
    :title="t('common.delete')"
    :description="deleting?.name || ''"
    :confirm-label="t('common.delete')"
    :loading="false"
    @confirm="remove"
  />
  <ConfirmationModal
    v-model:open="bulkDeleteOpen"
    :title="t('common.delete')"
    :description="t('common.totalResults', { count: selectedManufacturerIds.length })"
    :confirm-label="t('common.delete')"
    @confirm="removeSelectedManufacturers"
  />
</template>
