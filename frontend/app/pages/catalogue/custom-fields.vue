<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type CustomFieldSet = {
  id: string
  technicalName: string
  labels: Record<string, string>
  relations: string[]
  position: number
  fields: unknown[]
}

const { t } = useI18n()
const toast = useAppToast()
const auth = useAuth()
const route = useRoute()
const customFieldsPath = computed(() =>
  route.path.startsWith('/shop/')
    ? '/shop/custom-fields'
    : '/catalogue/custom-fields'
)
const createOpen = ref(false)
const saving = ref(false)
const selectedLocale = ref(auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const defaultLocale = computed(() => auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const validation = useFormValidation()
const validationErrors = validation.errors
const form = reactive({
  technicalName: '',
  labels: {} as Record<string, string>,
  position: '0',
  relations: ['product'] as string[]
})

const { data, status, refresh } = await useAsyncData('custom-field-sets', () =>
  apiFetch<{ sets: CustomFieldSet[] }>('/custom-field-sets')
)
const { data: localesData } = await useAsyncData('custom-field-list-locales', () =>
  apiFetch<{ locales: { code: string, label: string }[] }>('/property-groups/locales')
)
const sets = computed(() => data.value?.sets ?? [])
const {
  page: currentPage,
  paginatedItems: paginatedSets,
  pagination,
  reset: resetPagination
} = useClientPagination(() => sets.value)
watch(
  () => pagination.value.pageSize,
  resetPagination
)
const locales = computed(() => localesData.value?.locales ?? [])
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const relationItems = computed(() =>
  ['product', 'category', 'manufacturer', 'customer', 'order', 'property_group', 'property', 'media'].map(value => ({
    value,
    label: t(`customFieldsExtra.relations.${value}`)
  }))
)
const setLabel = (set: CustomFieldSet) =>
  set.labels[selectedLocale.value] || set.labels[defaultLocale.value] || set.technicalName
const setColumns: TableColumn<CustomFieldSet>[] = [
  {
    id: 'label',
    header: () => t('customFields.label'),
    cell: ({ row }) =>
      h(
        'button',
        {
          class: 'cursor-pointer font-medium text-highlighted hover:text-primary',
          onClick: () => navigateTo(`${customFieldsPath.value}/${row.original.id}`)
        },
        setLabel(row.original)
      )
  },
  {
    accessorKey: 'technicalName',
    header: () => t('customFields.technicalName')
  },
  {
    id: 'fields',
    header: () => t('customFields.fields'),
    cell: ({ row }) => row.original.fields.length
  },
  {
    id: 'relations',
    header: () => t('customFieldsExtra.assignTo'),
    cell: ({ row }) => h(
      'div',
      { class: 'flex flex-wrap gap-1' },
      row.original.relations.map(relation => h(
        'span',
        { class: 'rounded bg-elevated px-1.5 py-0.5 text-xs text-muted' },
        t(`customFieldsExtra.relations.${relation}`)
      ))
    )
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
        h(
          UDropdownMenu,
          {
            items: [
              [
                {
                  label: t('common.edit'),
                  icon: 'i-lucide-pencil',
                  onSelect: () => navigateTo(`${customFieldsPath.value}/${row.original.id}`)
                }
              ]
            ],
            content: { align: 'end' }
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
]
const openCreate = () => {
  selectedLocale.value = auth.tenant.value?.defaultSnippetLocale || 'en-GB'
  form.technicalName = ''
  form.labels = {}
  form.position = '0'
  form.relations = ['product']
  validation.clear()
  createOpen.value = true
}

const create = async () => {
  if (
    !validation.requireFields(
      [
        {
          field: 'label',
          value: form.labels[selectedLocale.value],
          label: t('customFields.label'),
          message: t('common.requiredField')
        },
        {
          field: 'technicalName',
          value: form.technicalName,
          label: t('customFields.technicalName'),
          message: t('common.requiredField')
        },
        {
          field: 'relations',
          value: form.relations,
          label: t('customFieldsExtra.assignTo'),
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
    const response = await apiFetch<{ set: CustomFieldSet }>('/custom-field-sets', {
      method: 'POST',
      body: form
    })
    await refresh()
    createOpen.value = false
    await navigateTo(`${customFieldsPath.value}/${response.set.id}`)
  } catch (error: any) {
    validation.notifyApiError(error, toast, t('common.error'), t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}

watch(
  () => route.params.id,
  (setId, previousSetId) => {
    if (!setId && previousSetId) {
      void refresh()
    }
  }
)
</script>

<template>
  <div v-if="!$route.params.id" class="space-y-4">
    <div v-if="status === 'pending'" class="py-12 text-center text-muted">
      {{ t('common.loading') }}
    </div>
    <UPageCard
      v-else-if="!sets.length"
      :title="t('customFields.emptyTitle')"
      :description="t('customFields.emptyDescription')"
    >
      <template #footer>
        <UButton :label="t('customFields.newSet')" @click="openCreate" />
      </template>
    </UPageCard>
    <AppDataTable
      v-else
      :data="paginatedSets"
      :columns="setColumns"
      table-key="shop-custom-field-sets"
      :column-labels="{
        label: t('customFields.label'),
        technicalName: t('customFields.technicalName'),
        fields: t('customFields.fields'),
        relations: t('customFieldsExtra.assignTo'),
        position: t('customFieldsExtra.position')
      }"
    >
      <template #header>
        <div class="flex w-full flex-wrap items-center justify-between gap-3">
          <p class="text-sm font-medium text-highlighted">
            {{ t('customFields.title') }} ({{ sets.length }})
          </p>
          <div class="flex flex-wrap items-center gap-2">
            <LocaleSelect
              v-model="selectedLocale"
              :options="locales"
              class="w-full sm:w-56"
            />
            <UButton :label="t('customFields.newSet')" @click="openCreate" />
          </div>
        </div>
      </template>

      <template #footer>
        <TablePaginationFooter
          v-model:page="currentPage"
          v-model:page-size="pagination.pageSize"
          :total="sets.length"
          class="border-t-0 pt-0"
        />
      </template>
    </AppDataTable>
  </div>

  <NuxtPage v-else />

  <UModal
    v-model:open="createOpen"
    :title="t('customFields.newSet')"
    :description="t('customFieldsExtra.setDescription')"
  >
    <template #body>
      <UForm class="space-y-5" @submit.prevent="create">
        <ModalLocaleField
          v-model="selectedLocale"
          :options="locales"
          :default-locale="defaultLocale"
          :active="createOpen"
          creating
        />
        <UFormField :label="t('customFields.label')" :error="validationErrors.label" required>
          <UInput
            v-model="form.labels[selectedLocale]"
            class="w-full"
            @update:model-value="validation.clear('label')"
          />
        </UFormField>
        <UFormField
          :label="t('customFields.technicalName')"
          :error="validationErrors.technicalName"
          required
        >
          <UInput
            v-model="form.technicalName"
            class="w-full"
            @update:model-value="validation.clear('technicalName')"
          />
        </UFormField>
        <div class="grid gap-5 sm:grid-cols-2">
          <UFormField :label="t('customFieldsExtra.position')">
            <UInput
              v-model="form.position"
              type="number"
              min="0"
              class="w-full"
            />
          </UFormField>
          <UFormField
            :label="t('customFieldsExtra.assignTo')"
            :error="validationErrors.relations"
            required
          >
            <USelect
              v-model="form.relations"
              :items="relationItems"
              multiple
              class="w-full"
              @update:model-value="validation.clear('relations')"
            />
          </UFormField>
        </div>
        <div class="flex justify-end gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            @click="createOpen = false"
          />
          <UButton :label="t('common.save')" type="submit" :loading="saving" />
        </div>
      </UForm>
    </template>
  </UModal>
</template>
