<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { SortingState } from '@tanstack/table-core'

type Supplier = {
  id: string
  name: string
  code: string
  email: string | null
  phone: string | null
  contactName: string | null
  street: string | null
  postalCode: string | null
  city: string | null
  country: string | null
  active: boolean
}

const { t } = useI18n()
const notify = useAppToast()
const validation = useFormValidation()
const validationErrors = validation.errors
const search = ref('')
const debouncedSearch = ref('')
const sorting = ref<SortingState>([])
const page = ref(1)
const pagination = reactive({ pageSize: 25 })
const modalOpen = ref(false)
const saving = ref(false)
const editing = ref<Supplier | null>(null)
const form = reactive({
  name: '',
  code: '',
  email: '',
  phone: '',
  contactName: '',
  street: '',
  postalCode: '',
  city: '',
  country: '',
  active: true
})
const UBadge = resolveComponent('UBadge')
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')

const listUrl = computed(() => {
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(pagination.pageSize)
  })

  if (debouncedSearch.value) params.set('search', debouncedSearch.value)
  const currentSort = sorting.value[0]
  if (currentSort && ['name', 'code', 'email', 'active'].includes(currentSort.id)) {
    params.set('sort', currentSort.id)
    params.set('direction', currentSort.desc ? 'DESC' : 'ASC')
  }

  return `/inventory/suppliers?${params.toString()}`
})
const { data, status, error, refresh } = await useAsyncData('inventory-suppliers-page', () =>
  apiFetch<{ suppliers: Supplier[], pagination: { total: number } }>(listUrl.value)
)
const suppliers = computed(() => data.value?.suppliers ?? [])
const total = computed(() => data.value?.pagination?.total ?? 0)

const openSupplier = (supplier?: Supplier) => {
  validation.clear()
  editing.value = supplier ?? null
  Object.assign(form, {
    name: supplier?.name ?? '',
    code: supplier?.code ?? '',
    email: supplier?.email ?? '',
    phone: supplier?.phone ?? '',
    contactName: supplier?.contactName ?? '',
    street: supplier?.street ?? '',
    postalCode: supplier?.postalCode ?? '',
    city: supplier?.city ?? '',
    country: supplier?.country ?? '',
    active: supplier?.active ?? true
  })
  modalOpen.value = true
}

const saveSupplier = async () => {
  const valid = validation.requireFields(
    [
      {
        field: 'name',
        value: form.name,
        label: t('suppliers.name'),
        message: t('common.requiredField')
      },
      {
        field: 'code',
        value: form.code,
        label: t('inventory.code'),
        message: t('common.requiredField')
      }
    ],
    notify,
    t('suppliers.saveFailed'),
    labels => t('common.requiredFields', { fields: labels.join(', ') })
  )
  if (!valid) return

  saving.value = true
  try {
    await apiFetch(
      editing.value
        ? `/inventory/suppliers/${editing.value.id}`
        : '/inventory/suppliers',
      {
        method: editing.value ? 'PATCH' : 'POST',
        body: form
      }
    )
    modalOpen.value = false
    await refresh()
    notify.success(t('suppliers.saved'), t('common.changesSaved'))
  } catch (saveError: unknown) {
    validation.notifyApiError(
      saveError,
      notify,
      t('suppliers.saveFailed'),
      t('common.tryAgain')
    )
  } finally {
    saving.value = false
  }
}

const actions = (supplier: Supplier) => [[{
  label: t('common.edit'),
  icon: 'i-lucide-pencil',
  onSelect: () => openSupplier(supplier)
}]]
const columns: TableColumn<Supplier>[] = [
  {
    accessorKey: 'name',
    header: () => t('suppliers.name'),
    cell: ({ row }) => h(
      'button',
      {
        class: 'cursor-pointer text-left font-medium text-highlighted hover:text-primary',
        onClick: () => openSupplier(row.original)
      },
      row.original.name
    )
  },
  { accessorKey: 'code', header: () => t('inventory.code') },
  {
    accessorKey: 'email',
    header: () => t('common.email'),
    cell: ({ row }) => row.original.email || '—'
  },
  {
    accessorKey: 'phone',
    header: () => t('common.phone'),
    cell: ({ row }) => row.original.phone || '—',
    enableSorting: false
  },
  {
    accessorKey: 'active',
    header: () => t('inventory.status'),
    cell: ({ row }) => h(
      UBadge,
      { color: row.original.active ? 'success' : 'neutral', variant: 'subtle' },
      () => row.original.active ? t('inventory.active') : t('inventory.inactive')
    )
  },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) => h(
      'div',
      { class: 'flex justify-end' },
      h(
        UDropdownMenu,
        { items: actions(row.original), content: { align: 'end' } },
        () => h(UButton, {
          icon: 'i-lucide-ellipsis-vertical',
          color: 'neutral',
          variant: 'ghost',
          'aria-label': t('common.edit')
        })
      )
    )
  }
]

let searchDebounce: ReturnType<typeof setTimeout> | undefined
watch(search, (value) => {
  page.value = 1
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    debouncedSearch.value = value.trim()
  }, 300)
})
watch(sorting, () => { page.value = 1 })
watch(() => pagination.pageSize, () => { page.value = 1 })
watch(listUrl, () => { void refresh() })
onBeforeUnmount(() => clearTimeout(searchDebounce))
</script>

<template>
  <AppDataTable
    v-model:sorting="sorting"
    :data="suppliers"
    :columns="columns"
    :get-row-id="(row) => row.id"
    :loading="status === 'pending'"
    server-sorting
    table-key="inventory-suppliers"
    :column-labels="{
      name: t('suppliers.name'),
      code: t('inventory.code'),
      email: t('common.email'),
      phone: t('common.phone'),
      active: t('inventory.status')
    }"
  >
    <template #header>
      <div class="flex w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 flex-wrap items-center gap-3">
          <p class="whitespace-nowrap text-sm font-medium text-highlighted">
            {{ t('nav.suppliers') }} ({{ total }})
          </p>
          <UInput
            v-model="search"
            icon="i-lucide-search"
            :placeholder="t('suppliers.search')"
            class="w-full sm:w-72"
          />
        </div>
        <UButton :label="t('suppliers.add')" @click="openSupplier()" />
      </div>
    </template>
    <template #empty>
      <AppEmptyState
        :title="error ? t('suppliers.loadFailed') : t('suppliers.empty')"
        :description="error ? t('common.tryAgain') : t('suppliers.emptyDescription')"
        icon="i-lucide-truck"
      />
    </template>
    <template #footer>
      <TablePaginationFooter
        v-model:page="page"
        v-model:page-size="pagination.pageSize"
        :total="total"
        class="border-t-0 pt-0"
      />
    </template>
  </AppDataTable>

  <UModal
    v-model:open="modalOpen"
    :title="editing ? t('suppliers.edit') : t('suppliers.add')"
  >
    <template #body>
      <UForm class="space-y-5" @submit.prevent="saveSupplier">
        <UFormField
          :label="t('suppliers.name')"
          :error="validationErrors.name"
          required
        >
          <UInput
            v-model="form.name"
            class="w-full"
            @update:model-value="validation.clear('name')"
          />
        </UFormField>
        <UFormField
          :label="t('inventory.code')"
          :error="validationErrors.code"
          required
        >
          <UInput
            v-model="form.code"
            class="w-full"
            @update:model-value="validation.clear('code')"
          />
        </UFormField>
        <UFormField :label="t('common.email')">
          <UInput v-model="form.email" type="email" class="w-full" />
        </UFormField>
        <UFormField :label="t('common.phone')">
          <UInput v-model="form.phone" type="tel" class="w-full" />
        </UFormField>
        <UFormField :label="t('suppliers.contactName')">
          <UInput v-model="form.contactName" class="w-full" />
        </UFormField>
        <UFormField :label="t('suppliers.street')">
          <UInput v-model="form.street" class="w-full" />
        </UFormField>
        <div class="grid grid-cols-2 gap-3">
          <UFormField :label="t('suppliers.postalCode')">
            <UInput v-model="form.postalCode" class="w-full" />
          </UFormField>
          <UFormField :label="t('suppliers.city')">
            <UInput v-model="form.city" class="w-full" />
          </UFormField>
        </div>
        <UFormField :label="t('suppliers.country')">
          <UInput v-model="form.country" class="w-full" />
        </UFormField>
        <UFormField
          v-if="editing"
          :label="t('inventory.active')"
          class="flex items-center justify-between rounded-md border border-default px-3 py-2"
        >
          <USwitch v-model="form.active" />
        </UFormField>
        <div class="flex justify-end gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            @click="modalOpen = false"
          />
          <UButton
            :label="t('common.save')"
            type="submit"
            :loading="saving"
          />
        </div>
      </UForm>
    </template>
  </UModal>
</template>
