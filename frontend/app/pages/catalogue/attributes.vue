<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { RowSelectionState, SortingState } from '@tanstack/table-core'

type PropertyValue = {
  id: string
  name: string
  code: string
  colorHex: string | null
  position: number
}
type PropertyGroup = {
  id: string
  name: string
  translations: Record<string, { name: string }>
  code: string
  displayType: 'text' | 'color' | 'image'
  isFilterable: boolean
  position: number
  propertyCount?: number | null
  properties: PropertyValue[]
}

const { t } = useI18n()
const route = useRoute()
const toast = useAppToast()
const auth = useAuth()
const groupOpen = ref(false)
const propertyOpen = ref(false)
const saving = ref(false)
const groupSorting = ref<SortingState>([])
const nameFilter = ref('')
const debouncedNameFilter = ref('')
const currentPage = ref(1)
const pagination = reactive({ pageSize: 25 })
const groupRowSelection = ref<RowSelectionState>({})
const bulkDeleteOpen = ref(false)
const selectedGroup = ref<PropertyGroup | null>(null)
const groupToDelete = ref<PropertyGroup | null>(null)
const deleteGroupOpen = computed({
  get: () => groupToDelete.value !== null,
  set: (value) => {
    if (!value) groupToDelete.value = null
  }
})
const editingProperty = ref<PropertyValue | null>(null)
const groupForm = reactive({
  name: '',
  code: '',
  displayType: 'text',
  isFilterable: true,
  displayOnProductDetail: true,
  sorting: 'custom',
  position: '0',
  values: ''
})
const propertyForm = reactive({ name: '', code: '', colorHex: '' })
const selectedLocale = ref(auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const defaultLocale = computed(() => auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const groupValidation = useFormValidation()
const groupValidationErrors = groupValidation.errors
const propertyValidation = useFormValidation()
const propertyValidationErrors = propertyValidation.errors
const serverSorting = computed(() => {
  const sorting = groupSorting.value[0]

  return ['name', 'code', 'displayType', 'position'].includes(sorting?.id || '')
    ? sorting
    : undefined
})
const propertyGroupListUrl = computed(() => {
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

  return `/property-groups?${params.toString()}`
})
const { data, status, refresh } = await useAsyncData('property-groups', () =>
  apiFetch<{ propertyGroups: PropertyGroup[], pagination: { total: number } }>(propertyGroupListUrl.value)
)
const { data: localesData } = await useAsyncData('property-group-create-locales', () =>
  apiFetch<{ locales: { code: string, label: string }[] }>('/property-groups/locales')
)
const groups = computed(() => data.value?.propertyGroups ?? [])
const locales = computed(() => localesData.value?.locales ?? [])
const localizedGroups = computed(() =>
  groups.value.map(group => ({
    ...group,
    name:
      group.translations[selectedLocale.value]?.name
      || group.translations[defaultLocale.value]?.name
      || group.name
  }))
)
const paginatedGroups = localizedGroups
const totalResults = computed(() => data.value?.pagination?.total ?? 0)
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const groupActions = (group: PropertyGroup) => [
  [
    {
      label: t('common.edit'),
      icon: 'i-lucide-pencil',
      onSelect: () => navigateTo(`/catalogue/attributes/${group.id}`)
    }
  ],
  [
    {
      label: t('common.delete'),
      icon: 'i-lucide-trash-2',
      color: 'error' as const,
      onSelect: () => (groupToDelete.value = group)
    }
  ]
]
const selectedGroupIds = computed(() =>
  Object.entries(groupRowSelection.value)
    .filter(([, selected]) => selected)
    .map(([id]) => id)
)
const groupColumns: TableColumn<PropertyGroup>[] = [
  {
    accessorKey: 'name',
    header: () => t('propertyGroups.name'),
    cell: ({ row }) =>
      h(
        'button',
        {
          class: 'cursor-pointer font-medium text-highlighted hover:text-primary',
          onClick: () => navigateTo(`/catalogue/attributes/${row.original.id}`)
        },
        row.original.name
      )
  },
  { accessorKey: 'code', header: () => t('propertyGroups.code') },
  { accessorKey: 'displayType', header: () => t('propertyGroups.displayType') },
  {
    id: 'properties',
    header: () => t('productProperties.title'),
    cell: ({ row }) => row.original.propertyCount ?? row.original.properties.length
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
        h(UDropdownMenu, { items: groupActions(row.original), content: { align: 'end' } }, () =>
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

const createGroup = async () => {
  if (
    !groupValidation.requireFields(
      [
        {
          field: 'name',
          value: groupForm.name,
          label: t('propertyGroups.name'),
          message: t('common.requiredField')
        }
      ],
      toast,
      t('propertyGroups.createFailed'),
      fields => t('common.requiredFields', { fields: fields.join(', ') })
    )
  ) {
    return
  }

  saving.value = true
  try {
    await apiFetch('/property-groups', {
      method: 'POST',
      body: {
        ...groupForm,
        properties: groupForm.values
          .split(',')
          .map(name => ({ name: name.trim() }))
          .filter(value => value.name)
      }
    })
    await refresh()
    Object.assign(groupForm, {
      name: '',
      code: '',
      displayType: 'text',
      isFilterable: true,
      displayOnProductDetail: true,
      sorting: 'custom',
      position: '0',
      values: ''
    })
    groupOpen.value = false
    toast.success(t('propertyGroups.created'), t('common.changesSaved'))
  } catch (error: unknown) {
    groupValidation.notifyApiError(
      error,
      toast,
      t('propertyGroups.createFailed'),
      t('common.tryAgain')
    )
  } finally {
    saving.value = false
  }
}

const removeGroup = async () => {
  if (!groupToDelete.value) return
  try {
    await apiFetch(`/property-groups/${groupToDelete.value.id}`, {
      method: 'DELETE'
    })
    await refresh()
    groupToDelete.value = null
    toast.success(t('propertyGroups.propertyDeleted'), t('common.changesSaved'))
  } catch (error: unknown) {
    const message = (error as { data?: { message?: unknown } })?.data?.message

    toast.error(
      t('propertyGroups.createFailed'),
      typeof message === 'string' && message !== '' ? message : t('common.tryAgain')
    )
  }
}

const removeSelectedGroups = async () => {
  if (!selectedGroupIds.value.length) {
    return
  }

  try {
    await Promise.all(
      selectedGroupIds.value.map(id =>
        apiFetch(`/property-groups/${id}`, { method: 'DELETE' })
      )
    )
    groupRowSelection.value = {}
    bulkDeleteOpen.value = false
    await refresh()
    toast.success(t('propertyGroups.propertyDeleted'), t('common.changesSaved'))
  } catch (error: unknown) {
    const message = (error as { data?: { message?: unknown } })?.data?.message

    toast.error(
      t('propertyGroups.createFailed'),
      typeof message === 'string' && message !== '' ? message : t('common.tryAgain')
    )
  }
}

const openCreateGroup = () => {
  selectedLocale.value = defaultLocale.value
  Object.assign(groupForm, {
    name: '',
    code: '',
    displayType: 'text',
    isFilterable: true,
    displayOnProductDetail: true,
    sorting: 'custom',
    position: '0',
    values: ''
  })
  groupValidation.clear()
  groupOpen.value = true
}

const _openProperty = (group: PropertyGroup) => {
  selectedGroup.value = group
  editingProperty.value = null
  Object.assign(propertyForm, { name: '', code: '', colorHex: '' })
  propertyValidation.clear()
  propertyOpen.value = true
}

const _editProperty = (group: PropertyGroup, property: PropertyValue) => {
  selectedGroup.value = group
  editingProperty.value = property
  Object.assign(propertyForm, {
    name: property.name,
    code: property.code,
    colorHex: property.colorHex || ''
  })
  propertyValidation.clear()
  propertyOpen.value = true
}

const createProperty = async () => {
  if (!selectedGroup.value) return
  if (
    !propertyValidation.requireFields(
      [
        {
          field: 'name',
          value: propertyForm.name,
          label: t('propertyGroups.name'),
          message: t('common.requiredField')
        }
      ],
      toast,
      t('propertyGroups.createFailed'),
      fields => t('common.requiredFields', { fields: fields.join(', ') })
    )
  ) {
    return
  }

  saving.value = true
  try {
    if (editingProperty.value)
      await apiFetch(
        `/property-groups/${selectedGroup.value.id}/properties/${editingProperty.value.id}`,
        { method: 'PATCH', body: propertyForm }
      )
    else
      await apiFetch(`/property-groups/${selectedGroup.value.id}/properties`, {
        method: 'POST',
        body: propertyForm
      })
    await refresh()
    propertyOpen.value = false
    toast.success(t('propertyGroups.propertyCreated'), t('common.changesSaved'))
  } catch (error: unknown) {
    propertyValidation.notifyApiError(
      error,
      toast,
      t('propertyGroups.createFailed'),
      t('common.tryAgain')
    )
  } finally {
    saving.value = false
  }
}

watch(
  () => route.params.id,
  (groupId, previousGroupId) => {
    if (!groupId && previousGroupId) {
      void refresh()
    }
  }
)

let searchDebounce: ReturnType<typeof setTimeout> | undefined

watch(nameFilter, (value) => {
  currentPage.value = 1
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    debouncedNameFilter.value = value.trim()
  }, 300)
})
watch(groupSorting, () => {
  currentPage.value = 1
})
watch(
  () => pagination.pageSize,
  () => {
    currentPage.value = 1
  }
)
watch(propertyGroupListUrl, () => {
  void refresh()
})
</script>

<template>
  <div v-if="!route.params.id" class="space-y-4">
    <div v-if="status === 'pending'" class="py-12 text-center text-muted">
      {{ t('common.loading') }}
    </div>
    <AppEmptyState
      v-else-if="!groups.length"
      :title="t('propertyGroups.empty')"
      :description="t('propertyGroups.emptyDescription')"
    >
      <UButton :label="t('propertyGroups.addGroup')" @click="openCreateGroup" />
    </AppEmptyState>
    <AppDataTable
      v-else
      v-model:row-selection="groupRowSelection"
      v-model:sorting="groupSorting"
      :data="paginatedGroups"
      :columns="groupColumns"
      :get-row-id="(row) => row.id"
      server-sorting
      selectable
      table-key="catalogue-property-groups"
      :column-labels="{
        name: t('propertyGroups.name'),
        code: t('propertyGroups.code'),
        displayType: t('propertyGroups.displayType'),
        properties: t('productProperties.title')
      }"
    >
      <template #header>
        <div class="flex w-full flex-wrap items-center justify-between gap-3">
          <div class="flex min-w-0 flex-wrap items-center gap-2">
            <p class="whitespace-nowrap text-sm font-medium text-highlighted">
              {{ t('propertyGroups.title') }} ({{ totalResults }})
            </p>
            <UInput
              v-model="nameFilter"
              icon="i-lucide-search"
              :placeholder="t('propertyGroups.search')"
              class="w-full sm:w-72"
            />
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <UButton
              v-if="selectedGroupIds.length"
              :label="t('products.bulkDelete', { count: selectedGroupIds.length })"
              color="error"
              variant="outline"
              @click="bulkDeleteOpen = true"
            />
            <LocaleSelect
              v-model="selectedLocale"
              :options="locales"
              class="w-full sm:w-56"
            />
            <UButton :label="t('propertyGroups.addGroup')" @click="openCreateGroup" />
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

  <NuxtPage v-else />

  <UModal v-model:open="groupOpen" :title="t('propertyGroups.addGroup')">
    <template #body>
      <UForm class="space-y-5" @submit.prevent="createGroup">
        <ModalLocaleField
          v-model="selectedLocale"
          :options="locales"
          :default-locale="defaultLocale"
          :active="groupOpen"
          creating
        />
        <UFormField :label="t('propertyGroups.name')" :error="groupValidationErrors.name" required>
          <UInput
            v-model="groupForm.name"
            class="w-full"
            @update:model-value="groupValidation.clear('name')"
          />
        </UFormField>
        <UFormField
          :label="t('propertyGroups.code')"
          :description="t('propertyGroups.codeDescription')"
        >
          <UInput v-model="groupForm.code" class="w-full" />
        </UFormField>
        <UFormField :label="t('propertyGroups.displayType')">
          <USelect
            v-model="groupForm.displayType"
            :items="[
              { label: t('propertyGroups.text'), value: 'text' },
              { label: t('propertyGroups.color'), value: 'color' },
              { label: t('propertyGroups.image'), value: 'image' },
              { label: 'Dropdown', value: 'dropdown' }
            ]"
            class="w-full"
          />
        </UFormField>
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField label="Sorting">
            <USelect
              v-model="groupForm.sorting"
              :items="[
                { label: 'Custom', value: 'custom' },
                { label: 'Alphanumeric', value: 'alphanumeric' }
              ]"
              class="w-full"
            />
          </UFormField><UFormField label="Position">
            <UInput
              v-model="groupForm.position"
              type="number"
              min="0"
              class="w-full"
            />
          </UFormField>
        </div>
        <UFormField
          :label="t('propertyGroups.initialProperties')"
          :description="t('propertyGroups.initialPropertiesDescription')"
        >
          <UInput v-model="groupForm.values" class="w-full" />
        </UFormField>
        <UFormField
          :label="t('propertyGroups.filterable')"
          class="flex items-center justify-between rounded-md border border-default px-3 py-2"
        >
          <USwitch v-model="groupForm.isFilterable" />
        </UFormField>
        <UFormField
          label="Display on product detail page"
          class="flex items-center justify-between rounded-md border border-default px-3 py-2"
        >
          <USwitch v-model="groupForm.displayOnProductDetail" />
        </UFormField>
        <div class="flex justify-end gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            @click="groupOpen = false"
          /><UButton :label="t('common.save')" type="submit" :loading="saving" />
        </div>
      </UForm>
    </template>
  </UModal>

  <UModal
    v-model:open="propertyOpen"
    :title="t('propertyGroups.addProperty')"
    :description="selectedGroup?.name"
  >
    <template #body>
      <UForm class="space-y-5" @submit.prevent="createProperty">
        <UFormField :label="t('propertyGroups.name')" :error="propertyValidationErrors.name" required>
          <UInput
            v-model="propertyForm.name"
            class="w-full"
            @update:model-value="propertyValidation.clear('name')"
          />
        </UFormField>
        <UFormField :label="t('propertyGroups.code')">
          <UInput v-model="propertyForm.code" class="w-full" />
        </UFormField>
        <UFormField
          v-if="selectedGroup?.displayType === 'color'"
          :label="t('propertyGroups.colorHex')"
        >
          <UInput v-model="propertyForm.colorHex" placeholder="#066AE0" class="w-full" />
        </UFormField>
        <div class="flex justify-end gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            @click="propertyOpen = false"
          /><UButton :label="t('common.save')" type="submit" :loading="saving" />
        </div>
      </UForm>
    </template>
  </UModal>
  <ConfirmationModal
    v-model:open="deleteGroupOpen"
    :title="t('common.delete')"
    :description="t('propertyGroups.confirmDelete', { name: groupToDelete?.name || '' })"
    :confirm-label="t('common.delete')"
    @confirm="removeGroup"
  />
  <ConfirmationModal
    v-model:open="bulkDeleteOpen"
    :title="t('common.delete')"
    :description="t('common.totalResults', { count: selectedGroupIds.length })"
    :confirm-label="t('common.delete')"
    @confirm="removeSelectedGroups"
  />
</template>
