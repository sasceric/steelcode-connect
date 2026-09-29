<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type CustomFieldOption = {
  id: string
  technicalValue: string
  labels: Record<string, string>
}
type CustomField = {
  id: string
  technicalName: string
  type: string
  labels: Record<string, string>
  config: Record<string, any>
  position: number
  options: CustomFieldOption[]
}
type CustomFieldSet = {
  id: string
  technicalName: string
  labels: Record<string, string>
  relations: string[]
  position: number
  fields: CustomField[]
}
type Locale = { code: string, label: string }

const route = useRoute()
const { t } = useI18n()
const customFieldsPath = computed(() =>
  route.path.startsWith('/shop/')
    ? '/shop/custom-fields'
    : '/catalogue/custom-fields'
)
const notify = useAppToast()
const auth = useAuth()
const selectedLocale = ref(auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const defaultLocale = computed(() => auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const setValidation = useFormValidation()
const setValidationErrors = setValidation.errors
const fieldValidation = useFormValidation()
const fieldValidationErrors = fieldValidation.errors
const search = ref('')
const open = ref(false)
const saving = ref(false)
const setSaving = ref(false)
const editing = ref<CustomField | null>(null)
const rowSelection = ref<Record<string, boolean>>({})
const deleteOpen = ref(false)
const form = reactive({
  technicalName: '',
  labels: {} as Record<string, string>,
  helpText: {} as Record<string, string>,
  type: 'text',
  position: '0',
  required: false,
  searchable: false,
  multiSelect: false,
  entity: 'product',
  options: [{ technicalValue: '', labels: {} as Record<string, string> }]
})
const setForm = reactive({
  technicalName: '',
  labels: {} as Record<string, string>,
  position: '0',
  relations: [] as string[]
})

const { data, status, refresh } = await useAsyncData(`custom-field-set-${route.params.id}`, () =>
  apiFetch<{ set: CustomFieldSet }>(`/custom-field-sets/${route.params.id}`)
)
const { data: localesData } = await useAsyncData('custom-field-detail-locales', () =>
  apiFetch<{ locales: Locale[] }>('/property-groups/locales')
)
const set = computed(() => data.value?.set)
const locales = computed(() => localesData.value?.locales ?? [])
const fields = computed(() => set.value?.fields ?? [])
watch(selectedLocale, () => {
  setValidation.clear()
  fieldValidation.clear()
})
watch(
  set,
  (value) => {
    if (!value) return

    setForm.technicalName = value.technicalName
    setForm.labels = { ...value.labels }
    setForm.position = String(value.position)
    setForm.relations = [...value.relations]
  },
  { immediate: true }
)
const filteredFields = computed(() =>
  fields.value.filter(field =>
    `${field.technicalName} ${field.labels[selectedLocale.value] || field.labels[defaultLocale.value] || ''}`
      .toLowerCase()
      .includes(search.value.toLowerCase())
  )
)
const {
  page: currentPage,
  paginatedItems: paginatedFields,
  pagination,
  reset: resetPagination
} = useClientPagination(() => filteredFields.value)
const typeItems = computed(() =>
  [
    'text',
    'editor',
    'number',
    'date',
    'checkbox',
    'switch',
    'select',
    'entity',
    'media',
    'color',
    'price'
  ].map(value => ({ value, label: t(`customFieldTypes.${value}`) }))
)
const entityItems = computed(() =>
  ['product', 'category', 'manufacturer', 'customer', 'order', 'property_group', 'property', 'media'].map(value => ({
    value,
    label: t(`customFieldsExtra.relations.${value}`)
  }))
)
const relationItems = computed(() =>
  ['product', 'category', 'manufacturer', 'property_group', 'property', 'media'].map(
    value => ({
      value,
      label: t(`customFieldsExtra.relations.${value}`)
    })
  )
)
const selectedIds = computed(() =>
  Object.entries(rowSelection.value)
    .filter(([, selected]) => selected)
    .map(([id]) => id)
)
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')

const label = (field: CustomField) =>
  field.labels[selectedLocale.value] || field.labels[defaultLocale.value] || field.technicalName
const openField = (field?: CustomField) => {
  fieldValidation.clear()
  if (!field) {
    selectedLocale.value = auth.tenant.value?.defaultSnippetLocale || 'en-GB'
  }
  editing.value = field || null
  Object.assign(
    form,
    field
      ? {
          technicalName: field.technicalName,
          labels: { ...field.labels },
          helpText: { ...(field.config.helpText || {}) },
          type: field.type,
          position: String(field.position),
          required: Boolean(field.config.required),
          searchable: Boolean(field.config.searchable),
          multiSelect: Boolean(field.config.multiSelect),
          entity: field.config.entity || 'product',
          options: field.options.map(option => ({
            technicalValue: option.technicalValue,
            labels: { ...option.labels }
          }))
        }
      : {
          technicalName: '',
          labels: {},
          helpText: {},
          type: 'text',
          position: String(fields.value.length),
          required: false,
          searchable: false,
          multiSelect: false,
          entity: 'product',
          options: [{ technicalValue: '', labels: {} }]
        }
  )
  open.value = true
}

const addSelectOption = () => {
  form.options.push({ technicalValue: '', labels: {} })
  fieldValidation.clear('options')
}

const submit = async () => {
  if (!set.value) return
  const fields = [
    {
      field: 'label',
      value: form.labels[selectedLocale.value],
      label: t('customFields.label'),
      message: t('common.requiredField')
    },
    ...(editing.value
      ? []
      : [
          {
            field: 'technicalName',
            value: form.technicalName,
            label: t('customFields.technicalName'),
            message: t('common.requiredField')
          }
        ]),
    ...(form.type === 'select'
      ? [
          {
            field: 'options',
            value: form.options.filter(
              option => option.technicalValue && option.labels[selectedLocale.value]
            ),
            label: t('customFields.addOption'),
            message: t('common.requiredField')
          }
        ]
      : [])
  ]
  if (
    !fieldValidation.requireFields(
      fields,
      notify,
      t('common.error'),
      labels => t('common.requiredFields', { fields: labels.join(', ') })
    )
  ) {
    return
  }

  saving.value = true
  try {
    const body = {
      technicalName: form.technicalName,
      labels: form.labels,
      type: form.type,
      position: form.position,
      config: {
        helpText: form.helpText,
        required: form.required,
        searchable: form.searchable,
        multiSelect: ['select', 'entity', 'media'].includes(form.type) && form.multiSelect,
        entity: form.entity
      },
      options:
        form.type === 'select'
          ? form.options.filter(
              option => option.technicalValue && option.labels[selectedLocale.value]
            )
          : []
    }
    await apiFetch(
      editing.value
        ? `/custom-field-sets/${set.value.id}/fields/${editing.value.id}`
        : `/custom-field-sets/${set.value.id}/fields`,
      {
        method: editing.value ? 'PATCH' : 'POST',
        body
      }
    )
    await refresh()
    open.value = false
    notify.success(t('customFields.fieldCreated'), t('common.changesSaved'))
  } catch (error: any) {
    fieldValidation.notifyApiError(error, notify, t('common.error'), t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}
const saveSet = async () => {
  if (!set.value) return
  const fields = [
    {
      field: 'label',
      value: setForm.labels[selectedLocale.value],
      label: t('customFields.label'),
      message: t('common.requiredField')
    },
    ...(selectedLocale.value === defaultLocale.value
      ? [
          {
            field: 'relations',
            value: setForm.relations,
            label: t('customFieldsExtra.assignTo'),
            message: t('common.requiredField')
          }
        ]
      : [])
  ]
  if (
    !setValidation.requireFields(
      fields,
      notify,
      t('common.error'),
      labels => t('common.requiredFields', { fields: labels.join(', ') })
    )
  ) {
    return
  }

  setSaving.value = true
  try {
    await apiFetch(`/custom-field-sets/${set.value.id}`, {
      method: 'PATCH',
      body: {
        technicalName: setForm.technicalName,
        labels: setForm.labels,
        position: setForm.position,
        relations: setForm.relations
      }
    })
    await refresh()
    notify.success(t('common.saved'), t('common.changesSaved'))
  } catch (error: any) {
    setValidation.notifyApiError(error, notify, t('common.error'), t('common.tryAgain'))
  } finally {
    setSaving.value = false
  }
}
const remove = async () => {
  if (!set.value || !selectedIds.value.length) return
  try {
    await apiFetch(`/custom-field-sets/${set.value.id}/fields`, {
      method: 'DELETE',
      body: { ids: selectedIds.value }
    })
    rowSelection.value = {}
    deleteOpen.value = false
    await refresh()
    notify.success(t('customFields.deleted'), t('common.changesSaved'))
  } catch (error: any) {
    notify.error(t('common.error'), error?.data?.message || t('common.tryAgain'))
  }
}
const fieldActions = (field: CustomField) => [
  [
    {
      label: t('common.edit'),
      icon: 'i-lucide-pencil',
      onSelect: () => openField(field)
    }
  ],
  [
    {
      label: t('common.delete'),
      icon: 'i-lucide-trash-2',
      color: 'error' as const,
      onSelect: () => {
        rowSelection.value = { [field.id]: true }
        deleteOpen.value = true
      }
    }
  ]
]
const columns: TableColumn<CustomField>[] = [
  {
    id: 'select',
    header: ({ table }) =>
      h(resolveComponent('UCheckbox'), {
        'modelValue': table.getIsSomePageRowsSelected()
          ? 'indeterminate'
          : table.getIsAllPageRowsSelected(),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
          table.toggleAllPageRowsSelected(Boolean(value)),
        'aria-label': t('common.selectAll')
      }),
    cell: ({ row }) =>
      h(resolveComponent('UCheckbox'), {
        'modelValue': row.getIsSelected(),
        'onUpdate:modelValue': (value: boolean) => row.toggleSelected(value),
        'aria-label': t('common.selectRow')
      })
  },
  {
    id: 'label',
    header: () => t('customFields.label'),
    cell: ({ row }) =>
      h(
        'button',
        {
          class: 'cursor-pointer font-medium text-highlighted hover:text-primary',
          onClick: () => openField(row.original)
        },
        label(row.original)
      )
  },
  {
    accessorKey: 'technicalName',
    header: () => t('customFields.technicalName')
  },
  {
    id: 'type',
    header: () => t('customFields.type'),
    cell: ({ row }) => t(`customFieldTypes.${row.original.type}`)
  },
  { accessorKey: 'position', header: () => t('customFieldsExtra.position') },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) =>
      h(
        'div',
        { class: 'flex justify-end' },
        h(UDropdownMenu, { items: fieldActions(row.original), content: { align: 'end' } }, () =>
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

watch([search, () => pagination.value.pageSize], () => {
  resetPagination()
})
</script>

<template>
  <div v-if="status === 'pending'" class="py-12 text-center text-muted">
    {{ t('common.loading') }}
  </div>
  <div v-else-if="set" class="space-y-6">
    <DetailPageHeader
      :title="set.labels[selectedLocale] || set.labels[defaultLocale] || set.technicalName"
      :subtitle="set.technicalName"
      :back-to="customFieldsPath"
    >
      <template #actions>
        <LocaleSelect
          v-model="selectedLocale"
          :options="locales"
          class="w-full sm:w-56"
        />
        <UButton :label="t('common.save')" :loading="setSaving" @click="saveSet" />
      </template>
    </DetailPageHeader>

    <UPageCard :title="t('customFields.title')">
      <UForm class="space-y-5" @submit.prevent="saveSet">
        <div class="grid gap-5 sm:grid-cols-2">
          <UFormField :label="t('customFields.label')" :error="setValidationErrors.label" required>
            <UInput
              v-model="setForm.labels[selectedLocale]"
              class="w-full"
              @update:model-value="setValidation.clear('label')"
            />
          </UFormField>
          <UFormField :label="t('customFields.technicalName')">
            <UInput v-model="setForm.technicalName" disabled class="w-full" />
          </UFormField>
          <UFormField :label="t('customFieldsExtra.position')">
            <UInput
              v-model="setForm.position"
              type="number"
              min="0"
              class="w-full"
            />
          </UFormField>
          <UFormField
            :label="t('customFieldsExtra.assignTo')"
            :error="setValidationErrors.relations"
            required
          >
            <USelect
              v-model="setForm.relations"
              :items="relationItems"
              multiple
              class="w-full"
              @update:model-value="setValidation.clear('relations')"
            />
          </UFormField>
        </div>
      </UForm>
    </UPageCard>

    <UPageCard :title="t('customFields.fields')">
      <template #footer>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex gap-2">
            <UButton
              v-if="selectedIds.length"
              :label="t('customFields.bulkDelete', { count: selectedIds.length })"
              color="error"
              variant="soft"
              @click="deleteOpen = true"
            />
          </div>
          <div class="ml-auto flex w-full gap-2 sm:w-auto">
            <UInput
              v-model="search"
              icon="i-lucide-search"
              :placeholder="t('customFields.search')"
              class="w-full sm:w-72"
            />
            <UButton :label="t('customFields.addField')" @click="openField()" />
          </div>
        </div>
      </template>
      <div v-if="!fields.length" class="py-10 text-center text-muted">
        {{ t('customFields.noFields') }}
      </div>
      <AppDataTable
        v-else
        v-model:row-selection="rowSelection"
        :data="paginatedFields"
        :columns="columns"
        :get-row-id="(row) => row.id"
        :max-height="null"
        table-key="shop-custom-fields"
        :column-labels="{
          label: t('customFields.label'),
          technicalName: t('customFields.technicalName'),
          type: t('customFields.type'),
          position: t('customFieldsExtra.position')
        }"
      >
        <template #footer>
          <TablePaginationFooter
            v-model:page="currentPage"
            v-model:page-size="pagination.pageSize"
            :total="filteredFields.length"
            class="border-t-0 pt-0"
          />
        </template>
      </AppDataTable>
    </UPageCard>
  </div>

  <UModal v-model:open="open" :title="editing ? t('common.edit') : t('customFields.addField')">
    <template #body>
      <UForm class="space-y-5" @submit.prevent="submit">
        <ModalLocaleField
          v-model="selectedLocale"
          :options="locales"
          :default-locale="defaultLocale"
          :active="open"
          :creating="!editing"
        />
        <div class="grid gap-5 sm:grid-cols-2">
          <UFormField :label="t('customFields.type')">
            <USelect v-model="form.type" :items="typeItems" class="w-full" />
          </UFormField>
          <UFormField :label="t('customFieldsExtra.position')">
            <UInput
              v-model="form.position"
              type="number"
              min="0"
              class="w-full"
            />
          </UFormField>
        </div>
        <UFormField :label="t('customFields.label')" :error="fieldValidationErrors.label" required>
          <UInput
            v-model="form.labels[selectedLocale]"
            class="w-full"
            @update:model-value="fieldValidation.clear('label')"
          />
        </UFormField>
        <UFormField
          :label="t('customFields.technicalName')"
          :error="fieldValidationErrors.technicalName"
          required
        >
          <UInput
            v-model="form.technicalName"
            :disabled="Boolean(editing)"
            class="w-full"
            @update:model-value="fieldValidation.clear('technicalName')"
          />
        </UFormField>
        <UFormField :label="t('customFieldConfig.helpText')">
          <UInput v-model="form.helpText[selectedLocale]" class="w-full" />
        </UFormField>
        <UFormField
          v-if="form.type === 'select'"
          :label="t('customFields.addOption')"
          :error="fieldValidationErrors.options"
        >
          <USwitch v-model="form.multiSelect" :label="t('customFields.multiSelect')" />
          <div
            v-for="(option, index) in form.options"
            :key="index"
            class="grid gap-3 sm:grid-cols-2"
          >
            <UInput
              v-model="option.technicalValue"
              :placeholder="t('customFields.technicalValue')"
              @update:model-value="fieldValidation.clear('options')"
            />
            <UInput
              v-model="option.labels[selectedLocale]"
              :placeholder="t('customFields.label')"
              @update:model-value="fieldValidation.clear('options')"
            />
          </div>
          <UButton
            :label="t('customFields.addOption')"
            color="neutral"
            variant="subtle"
            @click="addSelectOption"
          />
        </UFormField>
        <template v-if="['entity', 'media'].includes(form.type)">
          <USwitch v-model="form.multiSelect" :label="t('customFields.multiSelect')" />
        </template>
        <UFormField v-if="form.type === 'entity'" :label="t('customFieldsExtra.entity')">
          <USelect v-model="form.entity" :items="entityItems" class="w-full" />
        </UFormField>
        <div class="space-y-3 rounded-md border border-default p-3">
          <USwitch v-model="form.required" :label="t('customFieldConfig.required')" />
          <USwitch v-model="form.searchable" :label="t('customFieldConfig.searchable')" />
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
    :description="t('customFields.deleteDescription', { count: selectedIds.length })"
    :confirm-label="t('common.delete')"
    @confirm="remove"
  />
</template>
