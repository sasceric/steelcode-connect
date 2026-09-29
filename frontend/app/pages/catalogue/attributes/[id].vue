<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { SortingState } from '@tanstack/table-core'

type PropertyValue = {
  id: string
  name: string
  code: string
  colorHex: string | null
  position: number
  mediaId?: string | null
}
type Locale = { code: string, label: string }
type PropertyGroup = {
  id: string
  name: string
  code: string
  displayType: 'text' | 'color' | 'image' | 'dropdown'
  isFilterable: boolean
  displayOnProductDetail: boolean
  sorting: 'custom' | 'alphanumeric'
  position: number
  properties: PropertyValue[]
}
const route = useRoute()
const { t } = useI18n()
const notify = useAppToast()
const auth = useAuth()
const open = ref(false)
const editing = ref<PropertyValue | null>(null)
const propertyToDelete = ref<PropertyValue | null>(null)
const deleteOpen = computed({
  get: () => propertyToDelete.value !== null,
  set: (value) => {
    if (!value) propertyToDelete.value = null
  }
})
const saving = ref(false)
const translationSaving = ref(false)
const defaultLocale = computed(() => auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const selectedLocale = ref(defaultLocale.value)
const groupValidation = useFormValidation()
const groupValidationErrors = groupValidation.errors
const propertyValidation = useFormValidation()
const propertyValidationErrors = propertyValidation.errors
const propertySearch = ref('')
const propertySorting = ref<SortingState>([])
const form = reactive({
  name: '',
  code: '',
  colorHex: '',
  position: '0',
  mediaId: ''
})
const groupForm = reactive({
  name: '',
  code: '',
  displayType: 'text',
  isFilterable: true,
  displayOnProductDetail: true,
  sorting: 'custom',
  position: '0'
})
const translationForm = reactive({
  name: '',
  properties: {} as Record<string, string>
})
const { data, status, refresh } = await useAsyncData(`property-group-${route.params.id}`, () =>
  apiFetch<{ propertyGroup: PropertyGroup }>(`/property-groups/${route.params.id}`)
)
const { data: localesData } = await useAsyncData('property-group-locales', () =>
  apiFetch<{ locales: Locale[] }>('/property-groups/locales')
)
const group = computed(() => data.value?.propertyGroup)
const locales = computed(() => localesData.value?.locales ?? [])
const isTranslation = computed(() => selectedLocale.value !== defaultLocale.value)
const defaultLocaleLabel = computed(
  () => locales.value.find(locale => locale.code === defaultLocale.value)?.label || defaultLocale.value
)
const displayedGroupName = computed({
  get: () => (isTranslation.value ? translationForm.name : groupForm.name),
  set: (value: string) => {
    if (isTranslation.value) {
      translationForm.name = value
      return
    }

    groupForm.name = value
  }
})
const displayedGroupTitle = computed(() => displayedGroupName.value || group.value?.name || '')
const displayProperties = computed(() =>
  (group.value?.properties ?? []).map(property =>
    selectedLocale.value === defaultLocale.value
      ? property
      : {
          ...property,
          name: translationForm.properties[property.id] || property.name
        }
  )
)
const filteredProperties = computed(() =>
  displayProperties.value.filter(property =>
    `${property.name} ${property.code}`.toLowerCase().includes(propertySearch.value.toLowerCase())
  )
)
const sortedProperties = computed(() => {
  const sorting = propertySorting.value[0]

  if (!sorting) {
    return filteredProperties.value
  }

  return [...filteredProperties.value].sort((left, right) => {
    const comparison = String(left[sorting.id as keyof PropertyValue] ?? '').localeCompare(
      String(right[sorting.id as keyof PropertyValue] ?? ''),
      undefined,
      { numeric: true }
    )

    return sorting.desc ? -comparison : comparison
  })
})
const {
  page: currentPage,
  paginatedItems: paginatedProperties,
  pagination,
  reset: resetPagination,
  total: totalProperties
} = useClientPagination(() => sortedProperties.value)
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const propertyActions = (property: PropertyValue) => [
  [
    {
      label: t('common.edit'),
      icon: 'i-lucide-pencil',
      onSelect: () => edit(property)
    }
  ],
  ...(selectedLocale.value === defaultLocale.value
    ? [
        [
          {
            label: t('common.delete'),
            icon: 'i-lucide-trash-2',
            color: 'error' as const,
            onSelect: () => (propertyToDelete.value = property)
          }
        ]
      ]
    : [])
]
const propertyColumns: TableColumn<PropertyValue>[] = [
  {
    accessorKey: 'name',
    header: () => t('propertyGroups.name'),
    cell: ({ row }) =>
      h(
        'button',
        {
          class: 'cursor-pointer font-medium text-highlighted hover:text-primary',
          onClick: () => edit(row.original)
        },
        row.original.name
      )
  },
  {
    accessorKey: 'position',
    header: 'Position',
    cell: ({ row }) => String(row.original.position)
  },
  { accessorKey: 'code', header: () => t('propertyGroups.code') },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) =>
      h(
        'div',
        { class: 'flex justify-end' },
        h(UDropdownMenu, { items: propertyActions(row.original), content: { align: 'end' } }, () =>
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
watch(
  group,
  (value) => {
    if (value)
      Object.assign(groupForm, {
        name: value.name,
        code: value.code,
        displayType: value.displayType,
        isFilterable: value.isFilterable,
        displayOnProductDetail: value.displayOnProductDetail,
        sorting: value.sorting,
        position: String(value.position)
      })
  },
  { immediate: true }
)
watch([propertySearch, () => pagination.value.pageSize], () => {
  resetPagination()
})
watch(propertySorting, () => {
  resetPagination()
})
const syncEditingPropertyName = () => {
  if (!open.value || !editing.value) return

  const property = displayProperties.value.find(item => item.id === editing.value?.id)
  if (property) form.name = property.name
}
const loadTranslation = async () => {
  if (!group.value) return

  if (selectedLocale.value === defaultLocale.value) {
    syncEditingPropertyName()
    return
  }

  const response = await apiFetch<{
    translation: {
      name: string | null
      properties: { id: string, name: string | null }[]
    }
  }>(`/property-groups/${group.value.id}/translations/${selectedLocale.value}`)
  translationForm.name = response.translation.name || group.value.name
  translationForm.properties = Object.fromEntries(
    response.translation.properties.map(property => [
      property.id,
      property.name || group.value?.properties.find(item => item.id === property.id)?.name || ''
    ])
  )
  syncEditingPropertyName()
}
watch(selectedLocale, () => {
  groupValidation.clear()
  propertyValidation.clear()
  void loadTranslation()
})
const edit = (property?: PropertyValue) => {
  propertyValidation.clear()
  editing.value = property || null
  Object.assign(
    form,
    property
      ? {
          name: property.name,
          code: property.code,
          colorHex: property.colorHex || '',
          position: String(property.position || 0),
          mediaId: property.mediaId || ''
        }
      : {
          name: '',
          code: '',
          colorHex: '',
          position: String((group.value?.properties.length || 0) + 1),
          mediaId: ''
        }
  )
  open.value = true
}
const remove = async (property: PropertyValue) => {
  if (!group.value) return
  try {
    await apiFetch(`/property-groups/${group.value.id}/properties/${property.id}`, {
      method: 'DELETE'
    })
    await refresh()
    notify.success(t('propertyGroups.propertyDeleted'), t('common.changesSaved'))
    propertyToDelete.value = null
  } catch (error: unknown) {
    const message = (error as { data?: { message?: unknown } })?.data?.message

    notify.error(
      t('propertyGroups.createFailed'),
      typeof message === 'string' && message !== '' ? message : t('common.tryAgain')
    )
  }
}
const submit = async () => {
  if (!group.value) return
  if (
    !propertyValidation.requireFields(
      [
        {
          field: 'name',
          value: form.name,
          label: t('propertyGroups.name'),
          message: t('common.requiredField')
        }
      ],
      notify,
      t('propertyGroups.createFailed'),
      fields => t('common.requiredFields', { fields: fields.join(', ') })
    )
  ) {
    return
  }

  if (selectedLocale.value !== defaultLocale.value) {
    if (!editing.value) return
    translationForm.properties[editing.value.id] = form.name
    await saveTranslation()
    open.value = false
    return
  }
  saving.value = true
  try {
    if (editing.value)
      await apiFetch(`/property-groups/${group.value.id}/properties/${editing.value.id}`, {
        method: 'PATCH',
        body: form
      })
    else
      await apiFetch(`/property-groups/${group.value.id}/properties`, {
        method: 'POST',
        body: form
      })
    await refresh()
    open.value = false
    notify.success(t('propertyGroups.propertyCreated'), t('common.changesSaved'))
  } catch (error: unknown) {
    propertyValidation.notifyApiError(
      error,
      notify,
      t('propertyGroups.createFailed'),
      t('common.tryAgain')
    )
  } finally {
    saving.value = false
  }
}
const saveGroup = async () => {
  if (!group.value) return
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
      notify,
      t('propertyGroups.createFailed'),
      fields => t('common.requiredFields', { fields: fields.join(', ') })
    )
  ) {
    return
  }

  saving.value = true
  try {
    await apiFetch(`/property-groups/${group.value.id}`, {
      method: 'PATCH',
      body: groupForm
    })
    await refresh()
    notify.success(t('products.updatedSuccess'), t('common.changesSaved'))
  } catch (error: unknown) {
    groupValidation.notifyApiError(
      error,
      notify,
      t('propertyGroups.createFailed'),
      t('common.tryAgain')
    )
  } finally {
    saving.value = false
  }
}
const saveTranslation = async () => {
  if (!group.value || selectedLocale.value === defaultLocale.value) return
  if (
    !groupValidation.requireFields(
      [
        {
          field: 'name',
          value: translationForm.name,
          label: t('propertyGroups.name'),
          message: t('common.requiredField')
        }
      ],
      notify,
      t('propertyGroups.createFailed'),
      fields => t('common.requiredFields', { fields: fields.join(', ') })
    )
  ) {
    return
  }

  translationSaving.value = true
  try {
    await apiFetch(`/property-groups/${group.value.id}/translations/${selectedLocale.value}`, {
      method: 'PUT',
      body: {
        name: translationForm.name,
        properties: group.value.properties.map(property => ({
          id: property.id,
          name: translationForm.properties[property.id] || property.name
        }))
      }
    })
    notify.success(t('products.updatedSuccess'), t('common.changesSaved'))
  } catch (error: unknown) {
    groupValidation.notifyApiError(
      error,
      notify,
      t('propertyGroups.createFailed'),
      t('common.tryAgain')
    )
  } finally {
    translationSaving.value = false
  }
}
const save = async () => {
  if (isTranslation.value) {
    await saveTranslation()
    return
  }

  await saveGroup()
}
</script>

<template>
  <div v-if="status === 'pending'" class="py-12 text-center text-muted">
    {{ t('common.loading') }}
  </div>
  <div v-else-if="group" class="space-y-6">
    <DetailPageHeader
      :title="displayedGroupTitle"
      :subtitle="group.code"
      back-to="/catalogue/attributes"
    >
      <template #actions>
        <LocaleSelect
          v-model="selectedLocale"
          :options="locales"
          class="w-full sm:w-56"
        />
        <UButton
          :label="t('common.saveChanges')"
          :loading="saving || translationSaving"
          @click="save"
        />
      </template>
    </DetailPageHeader>
    <UPageCard title="Basic information">
      <UForm
        class="space-y-5"
        @submit.prevent="isTranslation ? saveTranslation() : saveGroup()"
      >
        <div class="grid gap-5 sm:grid-cols-2">
          <UFormField :label="t('propertyGroups.name')" :error="groupValidationErrors.name" required>
            <UInput
              v-model="displayedGroupName"
              class="w-full"
              @update:model-value="groupValidation.clear('name')"
            />
          </UFormField>
          <UFormField :label="t('propertyGroups.code')">
            <UInput v-model="groupForm.code" :disabled="isTranslation" class="w-full" />
          </UFormField>
        </div>
        <div class="grid gap-5 sm:grid-cols-3">
          <UFormField :label="t('propertyGroups.displayType')">
            <USelect
              v-model="groupForm.displayType"
              :disabled="isTranslation"
              :items="[
                { label: t('propertyGroups.text'), value: 'text' },
                { label: t('propertyGroups.color'), value: 'color' },
                { label: t('propertyGroups.image'), value: 'image' },
                { label: 'Dropdown', value: 'dropdown' }
              ]"
              class="w-full"
            />
          </UFormField>
          <UFormField label="Sorting">
            <USelect
              v-model="groupForm.sorting"
              :disabled="isTranslation"
              :items="[
                { label: 'Custom', value: 'custom' },
                { label: 'Alphanumeric', value: 'alphanumeric' }
              ]"
              class="w-full"
            />
          </UFormField>
          <UFormField label="Position">
            <UInput
              v-model="groupForm.position"
              :disabled="isTranslation"
              type="number"
              min="0"
              class="w-full"
            />
          </UFormField>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField
            :label="t('propertyGroups.filterable')"
            class="flex items-center justify-between rounded-md border border-default px-3 py-2"
          >
            <USwitch v-model="groupForm.isFilterable" :disabled="isTranslation" />
          </UFormField>
          <UFormField
            label="Display on product detail page"
            class="flex items-center justify-between rounded-md border border-default px-3 py-2"
          >
            <USwitch v-model="groupForm.displayOnProductDetail" :disabled="isTranslation" />
          </UFormField>
        </div>
      </UForm>
    </UPageCard>
    <UPageCard>
      <div v-if="!group.properties.length" class="py-8 text-center text-muted">
        {{ t('propertyGroups.noProperties') }}
      </div>
      <AppDataTable
        v-else
        v-model:sorting="propertySorting"
        :data="paginatedProperties"
        :columns="propertyColumns"
        table-key="catalogue-property-values"
        max-height="h-[462px]"
        :column-labels="{
          name: t('propertyGroups.name'),
          position: 'Position',
          code: t('propertyGroups.code')
        }"
      >
        <template #header>
          <div class="flex w-full flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 flex-wrap items-center gap-3">
              <p class="whitespace-nowrap text-sm font-medium text-highlighted">
                Property values ({{ totalProperties }})
              </p>
              <UInput
                v-model="propertySearch"
                icon="i-lucide-search"
                :placeholder="t('products.search')"
                class="w-full sm:w-72"
              />
            </div>
            <UButton
              :label="t('propertyGroups.addProperty')"
              :disabled="isTranslation"
              @click="edit()"
            />
          </div>
          <TranslationRestrictionNotice
            v-if="isTranslation"
            :message="t('propertyGroups.switchToDefaultToAdd', { language: defaultLocaleLabel })"
          />
        </template>

        <template #footer>
          <TablePaginationFooter
            v-model:page="currentPage"
            v-model:page-size="pagination.pageSize"
            :total="totalProperties"
            class="border-t-0 pt-0"
          />
        </template>
      </AppDataTable>
    </UPageCard>
  </div>
  <UModal
    v-model:open="open"
    :title="editing ? form.name : t('propertyGroups.addProperty')"
    size="md"
  >
    <template #body>
      <UForm class="space-y-5" @submit.prevent="submit">
        <ModalLocaleField
          v-model="selectedLocale"
          :options="locales"
          :default-locale="defaultLocale"
          :active="open"
          :creating="!editing"
        />
        <UFormField :label="t('propertyGroups.name')" :error="propertyValidationErrors.name" required>
          <UInput
            v-model="form.name"
            class="w-full"
            @update:model-value="propertyValidation.clear('name')"
          />
        </UFormField>
        <template v-if="selectedLocale === defaultLocale">
          <UFormField label="Position">
            <UInput
              v-model="form.position"
              type="number"
              min="0"
              class="w-full"
            />
          </UFormField>
          <UFormField v-if="group?.displayType === 'color'" :label="t('propertyGroups.colorHex')">
            <ColorPickerField v-model="form.colorHex" />
          </UFormField>
          <UFormField v-if="group?.displayType === 'image'" label="Default image">
            <MediaSelectionField v-model="form.mediaId" />
          </UFormField>
          <UFormField :label="t('propertyGroups.code')">
            <UInput v-model="form.code" disabled class="w-full" />
          </UFormField>
        </template>
        <div class="flex justify-end gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            @click="open = false"
          />
          <UButton
            :label="t('common.save')"
            type="submit"
            :loading="saving || translationSaving"
          />
        </div>
      </UForm>
    </template>
  </UModal>
  <ConfirmationModal
    v-model:open="deleteOpen"
    :title="t('common.delete')"
    :description="t('propertyGroups.confirmDelete', { name: propertyToDelete?.name || '' })"
    :confirm-label="t('common.delete')"
    @confirm="propertyToDelete && remove(propertyToDelete)"
  />
</template>
