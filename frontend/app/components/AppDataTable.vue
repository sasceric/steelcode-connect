<script setup lang="ts" generic="T extends TableData">
import type {
  TableColumn,
  TableData
} from '@nuxt/ui'
import type {
  ColumnPinningState,
  HeaderContext,
  RowSelectionState,
  SortingState,
  VisibilityState
} from '@tanstack/table-core'

const props = withDefaults(
  defineProps<{
    tableKey: string
    data: T[]
    columns: TableColumn<T>[]
    columnLabels?: Record<string, string>
    getRowId?: (row: T, index: number) => string
    loading?: boolean
    selectable?: boolean
    sortable?: boolean
    serverSorting?: boolean
    maxHeight?: string | null
  }>(),
  {
    columnLabels: () => ({}),
    loading: false,
    selectable: false,
    sortable: true,
    serverSorting: false,
    maxHeight: 'h-[calc(100dvh-14.375rem)]'
  }
)

const rowSelection = defineModel<RowSelectionState>('rowSelection', { default: () => ({}) })
const sorting = defineModel<SortingState>('sorting', { default: () => [] })
const { t } = useI18n()
const columnVisibility = useLocalStorage<VisibilityState>(
  `steelcode-table:${props.tableKey}:columns`,
  {}
)
const UIcon = resolveComponent('UIcon')
const UCheckbox = resolveComponent('UCheckbox')
const columnPinning = computed<ColumnPinningState>(() =>
  props.columns.some(column => column.id === 'actions')
    ? { right: ['actions'] }
    : {}
)

const visibleColumns = computed(() =>
  props.columns.flatMap((column) => {
    const accessorKey = (column as { accessorKey?: string }).accessorKey
    const columnId = typeof column.id === 'string'
      ? column.id
      : typeof accessorKey === 'string'
        ? accessorKey
        : null

    if (!columnId || column.enableHiding === false) {
      return []
    }

    return [{
      id: columnId,
      label: props.columnLabels[columnId] || columnId
    }]
  })
)

const columns = computed<TableColumn<T>[]>(() => {
  const selectionColumn: TableColumn<T> = {
    id: 'select',
    enableHiding: false,
    enableSorting: false,
    header: ({ table }) =>
      h(UCheckbox, {
        'modelValue': table.getIsSomePageRowsSelected()
          ? 'indeterminate'
          : table.getIsAllPageRowsSelected(),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
          table.toggleAllPageRowsSelected(Boolean(value)),
        'ariaLabel': t('productProperties.selectAll')
      }),
    cell: ({ row }) =>
      h(UCheckbox, {
        'modelValue': row.getIsSelected(),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
          row.toggleSelected(Boolean(value)),
        'ariaLabel': t('productProperties.selectRow')
      })
  }
  const definitions = props.selectable
    ? [selectionColumn, ...props.columns]
    : props.columns

  return definitions.map((definition) => {
    const accessorKey = (definition as { accessorKey?: string }).accessorKey
    const isActionsColumn = definition.id === 'actions'

    if (isActionsColumn) {
      return {
        ...definition,
        size: 82,
        minSize: 82,
        maxSize: 82
      } as TableColumn<T>
    }

    if (!props.sortable || typeof accessorKey !== 'string' || definition.enableSorting === false) {
      return definition
    }

    const header = definition.header

    return {
      ...definition,
      header: (context: HeaderContext<T, unknown>) => {
        const direction = context.column.getIsSorted()
        const label = typeof header === 'function' ? header(context) : header

        return h(
          'button',
          {
            class: 'inline-flex cursor-pointer items-center gap-1 text-left font-medium',
            onClick: context.column.getToggleSortingHandler()
          },
          [
            h('span', label),
            h(UIcon, {
              name: direction === 'asc'
                ? 'i-lucide-arrow-up'
                : direction === 'desc'
                  ? 'i-lucide-arrow-down'
                  : 'i-lucide-arrow-up-down',
              class: 'size-3.5 text-dimmed'
            })
          ]
        )
      }
    } as TableColumn<T>
  })
})

const updateColumnVisibility = (columnId: string, visible: boolean | 'indeterminate') => {
  columnVisibility.value = {
    ...columnVisibility.value,
    [columnId]: Boolean(visible)
  }
}
</script>

<template>
  <div class="overflow-hidden rounded-lg border border-default">
    <div
      v-if="$slots.header"
      class="flex flex-wrap items-center justify-between gap-3 border-b border-default bg-default px-3 py-2"
    >
      <div class="min-w-0 flex-1">
        <slot name="header" />
      </div>
    </div>

    <div :class="[maxHeight, 'overflow-auto']">
      <UTable
        v-model:row-selection="rowSelection"
        v-model:sorting="sorting"
        v-model:column-visibility="columnVisibility"
        :column-pinning="columnPinning"
        :data="data"
        :columns="columns"
        :get-row-id="getRowId"
        :loading="loading"
        :sorting-options="{ manualSorting: serverSorting }"
        sticky="header"
        :ui="{
          root: 'overflow-visible',
          base: 'table-fixed border-separate border-spacing-0',
          thead: '[&>tr]:bg-elevated/95 [&>tr]:after:content-none',
          tbody: '[&>tr]:last:[&>td]:border-b-0',
          th: 'h-[42px] border-y border-default px-3 py-1 text-xs first:border-l last:border-r data-[pinned=right]:w-[82px] data-[pinned=right]:text-center data-[pinned=right]:!bg-default',
          td: 'h-[42px] border-b border-default bg-default px-3 py-1 text-sm data-[pinned=right]:w-[82px] data-[pinned=right]:text-center data-[pinned=right]:!bg-default',
          separator: 'h-0'
        }"
      >
        <template v-if="columnPinning.right?.length" #actions-header>
          <UPopover>
            <UButton
              :aria-label="$t('table.columns')"
              :title="$t('table.columns')"
              icon="i-lucide-sliders-horizontal"
              color="neutral"
              variant="outline"
              size="sm"
            />
            <template #content>
              <div class="min-w-52 space-y-1 p-2">
                <label
                  v-for="column in visibleColumns"
                  :key="column.id"
                  class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 hover:bg-elevated"
                >
                  <UCheckbox
                    :model-value="columnVisibility[column.id] !== false"
                    @update:model-value="updateColumnVisibility(column.id, $event)"
                  />
                  <span class="text-sm text-highlighted">
                    {{ column.label }}
                  </span>
                </label>
              </div>
            </template>
          </UPopover>
        </template>
        <template v-if="$slots.empty" #empty>
          <slot name="empty" />
        </template>
      </UTable>
    </div>

    <div
      v-if="$slots.footer"
      class="border-t border-default bg-default px-3 py-2"
    >
      <slot name="footer" />
    </div>
  </div>
</template>
