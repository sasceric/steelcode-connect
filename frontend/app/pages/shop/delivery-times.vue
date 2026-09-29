<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { SortingState } from '@tanstack/table-core'

type DeliveryTime = {
  id: string
  labels: Record<string, string>
  min: number
  max: number
  unit: string
}

const { t } = useI18n()
const auth = useAuth()
const toast = useAppToast()
const open = ref(false)
const saving = ref(false)
const selectedLocale = ref('en-GB')
const editing = ref<DeliveryTime | null>(null)
const deleting = ref<DeliveryTime | null>(null)
const validation = useFormValidation()
const validationErrors = validation.errors
const deleteOpen = computed({
  get: () => deleting.value !== null,
  set: (value: boolean) => {
    if (!value) deleting.value = null
  }
})
const deleteDescription = computed(() => {
  if (!deleting.value) return ''

  return (
    deleting.value.labels[selectedLocale.value]
    || deleting.value.labels[defaultLocale.value]
    || ''
  )
})
const form = reactive({ label: '', min: '', max: '', unit: 'day' })
const search = ref('')
const debouncedSearch = ref('')
const sorting = ref<SortingState>([])
const currentPage = ref(1)
const pagination = reactive({ pageSize: 25 })
const deliveryTimesUrl = computed(() => {
  const params = new URLSearchParams({
    type: 'delivery-times',
    page: String(currentPage.value),
    limit: String(pagination.pageSize)
  })
  if (debouncedSearch.value) {
    params.set('search', debouncedSearch.value)
  }
  const activeSorting = sorting.value[0]
  if (activeSorting && ['min', 'max', 'unit'].includes(activeSorting.id)) {
    params.set('sort', activeSorting.id)
    params.set('direction', activeSorting.desc ? 'DESC' : 'ASC')
  }
  return `/catalogue/references?${params.toString()}`
})
const { data, refresh } = await useAsyncData('shop-delivery-times', () =>
  apiFetch<{ deliveryTimes: DeliveryTime[], pagination: { total: number } }>(deliveryTimesUrl.value)
)
const deliveryTimes = computed(() => data.value?.deliveryTimes ?? [])
const paginatedDeliveryTimes = deliveryTimes
const totalResults = computed(() => data.value?.pagination?.total ?? 0)
const { data: localesData } = await useAsyncData('shop-delivery-times-locales', () =>
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
watch(deliveryTimesUrl, () => {
  void refresh()
})
watch(defaultLocale, (locale) => {
  if (!editing.value) selectedLocale.value = locale
}, { immediate: true })
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const columns = computed<TableColumn<DeliveryTime>[]>(() => [
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
        row.original.labels[selectedLocale.value] || row.original.labels[defaultLocale.value] || '—'
      )
  },
  {
    accessorKey: 'min',
    header: () => t('shopReferences.min')
  },
  {
    accessorKey: 'max',
    header: () => t('shopReferences.max')
  },
  {
    accessorKey: 'unit',
    header: () => t('shopReferences.unit')
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
  form.label = ''
  form.min = ''
  form.max = ''
  form.unit = 'day'
  validation.clear()
}
const openCreate = () => {
  reset()
  selectedLocale.value = defaultLocale.value
  open.value = true
}
const edit = (deliveryTime: DeliveryTime) => {
  editing.value = deliveryTime
  form.label = deliveryTime.labels[selectedLocale.value] || deliveryTime.labels[defaultLocale.value] || ''
  form.min = String(deliveryTime.min)
  form.max = String(deliveryTime.max)
  form.unit = deliveryTime.unit
  open.value = true
}
const create = async () => {
  if (
    !validation.requireFields(
      [
        { field: 'label', value: form.label, label: t('shopReferences.name'), message: t('common.requiredField') },
        { field: 'min', value: form.min, label: t('shopReferences.min'), message: t('common.requiredField') },
        { field: 'max', value: form.max, label: t('shopReferences.max'), message: t('common.requiredField') },
        { field: 'unit', value: form.unit, label: t('shopReferences.unit'), message: t('common.requiredField') }
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
        ? `/catalogue/references/delivery-times/${editing.value.id}`
        : '/catalogue/references/delivery-times',
      {
        method: editing.value ? 'PATCH' : 'POST',
        body: {
          labels: { [selectedLocale.value]: form.label },
          min: Number(form.min),
          max: Number(form.max),
          unit: form.unit
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
    await apiFetch(`/catalogue/references/delivery-times/${deleting.value.id}`, {
      method: 'DELETE'
    })
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
      :data="paginatedDeliveryTimes"
      :columns="columns"
      server-sorting
      table-key="shop-delivery-times"
      :column-labels="{
        label: t('shopReferences.name'),
        min: t('shopReferences.min'),
        max: t('shopReferences.max'),
        unit: t('shopReferences.unit')
      }"
    >
      <template #header>
        <div class="flex w-full flex-wrap items-center justify-between gap-3">
          <div class="flex min-w-0 flex-wrap items-center gap-2">
            <p class="whitespace-nowrap text-sm font-medium text-highlighted">
              {{ t('nav.deliveryTimes') }} ({{ totalResults }})
            </p>
            <UInput
              v-model="search"
              icon="i-lucide-search"
              :placeholder="t('shopReferences.searchDeliveryTimes')"
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
        <UFormField :label="t('shopReferences.name')" :error="validationErrors.label" required>
          <UInput v-model="form.label" class="w-full" @update:model-value="validation.clear('label')" />
        </UFormField>
        <div class="grid gap-4 sm:grid-cols-3">
          <UFormField :label="t('shopReferences.min')" :error="validationErrors.min" required>
            <UInput
              v-model="form.min"
              inputmode="numeric"
              class="w-full"
              @update:model-value="validation.clear('min')"
            />
          </UFormField>
          <UFormField :label="t('shopReferences.max')" :error="validationErrors.max" required>
            <UInput
              v-model="form.max"
              inputmode="numeric"
              class="w-full"
              @update:model-value="validation.clear('max')"
            />
          </UFormField>
          <UFormField :label="t('shopReferences.unit')" :error="validationErrors.unit" required>
            <USelect
              v-model="form.unit"
              :items="['second', 'minute', 'hour', 'day', 'week']"
              class="w-full"
              @update:model-value="validation.clear('unit')"
            />
          </UFormField>
        </div>
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
    :description="deleteDescription"
    :confirm-label="t('common.delete')"
    @confirm="remove"
  />
</template>
