<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { SortingState } from '@tanstack/table-core'

type Unit = {
  id: string
  code: string
  symbol: string
  labels: Record<string, string>
}

const { t } = useI18n()
const auth = useAuth()
const toast = useAppToast()
const open = ref(false)
const saving = ref(false)
const selectedLocale = ref('en-GB')
const editing = ref<Unit | null>(null)
const deleting = ref<Unit | null>(null)
const validation = useFormValidation()
const validationErrors = validation.errors
const deleteOpen = computed({
  get: () => deleting.value !== null,
  set: (value: boolean) => {
    if (!value) deleting.value = null
  }
})
const form = reactive({ code: '', symbol: '', label: '' })
const search = ref('')
const debouncedSearch = ref('')
const sorting = ref<SortingState>([])
const currentPage = ref(1)
const pagination = reactive({ pageSize: 25 })
const unitsUrl = computed(() => {
  const params = new URLSearchParams({
    type: 'units',
    page: String(currentPage.value),
    limit: String(pagination.pageSize)
  })
  if (debouncedSearch.value) {
    params.set('search', debouncedSearch.value)
  }
  const activeSorting = sorting.value[0]
  if (activeSorting && ['code', 'symbol'].includes(activeSorting.id)) {
    params.set('sort', activeSorting.id)
    params.set('direction', activeSorting.desc ? 'DESC' : 'ASC')
  }
  return `/catalogue/references?${params.toString()}`
})
const { data, refresh } = await useAsyncData('shop-units', () =>
  apiFetch<{ units: Unit[], pagination: { total: number } }>(unitsUrl.value)
)
const units = computed(() => data.value?.units ?? [])
const paginatedUnits = units
const totalResults = computed(() => data.value?.pagination?.total ?? 0)
const { data: localesData } = await useAsyncData('shop-units-locales', () =>
  apiFetch<{ locales: { code: string, label: string }[] }>('/property-groups/locales')
)
const locales = computed(() => localesData.value?.locales ?? [])
const defaultLocale = computed(() => auth.tenant.value?.defaultSnippetLocale || 'en-GB')
let searchDebounce: ReturnType<typeof setTimeout> | undefined
watch(search, (value) => {
  currentPage.value = 1
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => { debouncedSearch.value = value.trim() }, 300)
})
watch(
  () => pagination.pageSize,
  () => {
    currentPage.value = 1
  }
)
watch(sorting, () => {
  currentPage.value = 1
})
watch(unitsUrl, () => {
  void refresh()
})
watch(defaultLocale, (locale) => {
  if (!editing.value) selectedLocale.value = locale
}, { immediate: true })
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const columns = computed<TableColumn<Unit>[]>(() => [
  {
    id: 'label',
    header: () => t('shopReferences.name'),
    cell: ({ row }) =>
      h(
        'button',
        {
          class: 'cursor-pointer text-left font-medium text-highlighted hover:text-primary',
          onClick: () => edit(row.original)
        },
        row.original.labels[selectedLocale.value]
        || row.original.labels[defaultLocale.value]
        || row.original.code
      )
  },
  {
    accessorKey: 'code',
    header: () => t('shopReferences.code')
  },
  {
    accessorKey: 'symbol',
    header: () => t('shopReferences.symbol')
  },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) =>
      h(
        'div',
        { class: 'flex justify-end' },
        h(
          UDropdownMenu,
          {
            content: { align: 'end' },
            items: [
              [
                {
                  label: t('common.edit'),
                  icon: 'i-lucide-pencil',
                  onSelect: () => edit(row.original)
                }
              ],
              [
                {
                  label: t('common.delete'),
                  icon: 'i-lucide-trash-2',
                  color: 'error',
                  onSelect: () => (deleting.value = row.original)
                }
              ]
            ]
          },
          () =>
            h(UButton, {
              'icon': 'i-lucide-ellipsis-vertical',
              'color': 'neutral',
              'variant': 'ghost',
              'aria-label': t('common.edit')
            })
        )
      )
  }
])
const reset = () => {
  editing.value = null
  form.code = ''
  form.symbol = ''
  form.label = ''
  validation.clear()
}
const openCreate = () => {
  reset()
  selectedLocale.value = defaultLocale.value
  open.value = true
}
const edit = (unit: Unit) => {
  editing.value = unit
  form.code = unit.code
  form.symbol = unit.symbol
  form.label = unit.labels[selectedLocale.value] || unit.labels[defaultLocale.value] || ''
  open.value = true
}
const create = async () => {
  if (
    !validation.requireFields(
      [
        { field: 'code', value: form.code, label: t('shopReferences.code'), message: t('common.requiredField') },
        { field: 'symbol', value: form.symbol, label: t('shopReferences.symbol'), message: t('common.requiredField') },
        { field: 'label', value: form.label, label: t('shopReferences.name'), message: t('common.requiredField') }
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
    await apiFetch(
      editing.value
        ? `/catalogue/references/units/${editing.value.id}`
        : '/catalogue/references/units',
      {
        method: editing.value ? 'PATCH' : 'POST',
        body: {
          code: form.code,
          symbol: form.symbol,
          labels: { [selectedLocale.value]: form.label }
        }
      }
    )
    await refresh()
    open.value = false
    reset()
    toast.success(t('common.saved'), t('common.changesSaved'))
  } catch (error: any) {
    validation.notifyApiError(error, toast, t('common.error'), t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}
const remove = async () => {
  if (!deleting.value) return
  try {
    await apiFetch(`/catalogue/references/units/${deleting.value.id}`, { method: 'DELETE' })
    deleting.value = null
    await refresh()
    toast.success(t('common.deleted'), t('common.changesSaved'))
  } catch (error: any) {
    toast.error(t('common.error'), error?.data?.message || t('common.tryAgain'))
  }
}
</script>

<template>
  <div>
    <AppDataTable
      v-model:sorting="sorting"
      :data="paginatedUnits"
      :columns="columns"
      server-sorting
      table-key="shop-units"
      :column-labels="{
        label: t('shopReferences.name'),
        code: t('shopReferences.code'),
        symbol: t('shopReferences.symbol')
      }"
    >
      <template #header>
        <div class="flex w-full flex-wrap items-center justify-between gap-3">
          <div class="flex min-w-0 flex-wrap items-center gap-2">
            <p class="whitespace-nowrap text-sm font-medium text-highlighted">
              {{ t('nav.units') }} ({{ totalResults }})
            </p>
            <UInput
              v-model="search"
              icon="i-lucide-search"
              :placeholder="t('shopReferences.searchUnits')"
              class="w-full sm:w-72"
            />
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <LocaleSelect
              v-model="selectedLocale"
              :options="locales"
              class="w-full sm:w-56"
            />
            <UButton :label="t('common.add')" @click="openCreate" />
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

  <UModal v-model:open="open" :title="editing ? t('common.edit') : t('common.add')">
    <template #body>
      <UForm class="space-y-4" @submit.prevent="create">
        <ModalLocaleField
          v-model="selectedLocale"
          :options="locales"
          :default-locale="defaultLocale"
          :active="open"
          :creating="!editing"
        />
        <UFormField :label="t('shopReferences.code')" :error="validationErrors.code" required>
          <UInput v-model="form.code" class="w-full" @update:model-value="validation.clear('code')" />
        </UFormField>
        <UFormField :label="t('shopReferences.symbol')" :error="validationErrors.symbol" required>
          <UInput v-model="form.symbol" class="w-full" @update:model-value="validation.clear('symbol')" />
        </UFormField>
        <UFormField :label="t('shopReferences.name')" :error="validationErrors.label" required>
          <UInput v-model="form.label" class="w-full" @update:model-value="validation.clear('label')" />
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
    :description="deleting?.code"
    :confirm-label="t('common.delete')"
    @confirm="remove"
  />
</template>
