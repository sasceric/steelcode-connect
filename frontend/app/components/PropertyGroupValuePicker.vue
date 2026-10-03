<script setup lang="ts">
type PropertyValue = {
  id: string
  propertyGroupId: string
  name: string
  labels?: Record<string, string>
  code: string
  colorHex: string | null
  position: number
}
type PropertyGroup = { id: string, name: string }

const props = withDefaults(defineProps<{
  groups: PropertyGroup[]
  activeGroupId: string
  selectedIds: string[]
  selectionCounts?: Record<string, number>
  selectedValues?: (Omit<PropertyValue, 'propertyGroupId'> & { propertyGroupId?: string })[]
  locale: string
  description: string
}>(), {
  selectionCounts: () => ({}),
  selectedValues: () => []
})
const emit = defineEmits<{
  'update:activeGroupId': [id: string]
  'toggle': [property: PropertyValue, selected: boolean]
  'valuesLoaded': [groupId: string, properties: PropertyValue[]]
}>()
const { t } = useI18n()
const activeGroup = computed(() => props.groups.find(group => group.id === props.activeGroupId))
const endpoint = computed(() => `/property-groups/${props.activeGroupId}/properties`)
const parameters = computed(() => ({ locale: props.locale }))
const enabled = computed(() => Boolean(activeGroup.value))
const { records, searchTerm, loading, hasMore, failed, loadMore } = usePagedSelectOptions<PropertyValue>({
  endpoint,
  parameters,
  enabled,
  responseKey: 'properties',
  selectedItems: computed(() => []),
  option: property => ({ value: property.id, label: property.labels?.[props.locale] || property.name })
})
const selected = computed(() => new Set(props.selectedIds))
const groupsViewport = useTemplateRef<HTMLElement>('groupsViewport')
const activeSelectedValues = computed(() => props.selectedValues
  .filter(property => property.propertyGroupId === props.activeGroupId && selected.value.has(property.id))
  .map(property => ({ ...property, propertyGroupId: props.activeGroupId }))
)

watch(records, properties => {
  if (properties.length) emit('valuesLoaded', props.activeGroupId, properties)
})
function revealActiveGroup() {
  groupsViewport.value?.querySelector<HTMLElement>('button[aria-pressed="true"]')?.scrollIntoView({
    block: 'nearest'
  })
}
watch(() => props.activeGroupId, revealActiveGroup, { flush: 'post' })
onMounted(revealActiveGroup)

function selectGroup(id: string) {
  searchTerm.value = ''
  emit('update:activeGroupId', id)
}

function toggleProperty(property: PropertyValue, value: boolean | 'indeterminate') {
  emit('toggle', property, value === true)
}

function loadNextPage(event: Event) {
  const viewport = event.currentTarget as HTMLElement
  if (viewport.scrollHeight - viewport.scrollTop - viewport.clientHeight <= 80 && hasMore.value) {
    void loadMore()
  }
}

function retry() {
  void loadMore(true)
}
</script>

<template>
  <div class="property-group-value-picker">
    <aside class="property-group-value-picker__panel border-r border-default">
      <p class="shrink-0 p-4 text-sm text-muted">
        {{ description }}
      </p>
      <div
        ref="groupsViewport"
        data-testid="property-group-scroll"
        class="property-group-value-picker__scroll space-y-1 px-4 pb-4"
      >
        <button
          v-for="group in groups"
          :key="group.id"
          type="button"
          :aria-pressed="activeGroupId === group.id"
          class="flex w-full cursor-pointer items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm"
          :class="activeGroupId === group.id ? 'bg-elevated font-medium text-highlighted' : 'text-muted hover:bg-elevated/60'"
          @click="selectGroup(group.id)"
        >
          <span class="truncate" :title="group.name">
            {{ group.name }}
          </span>
          <span v-if="selectionCounts[group.id]" class="shrink-0 text-xs">
            {{ selectionCounts[group.id] }}
          </span>
        </button>
      </div>
    </aside>
    <section class="property-group-value-picker__panel">
      <div class="shrink-0 space-y-3 border-b border-default p-4">
        <UInput
          v-model="searchTerm"
          icon="i-lucide-search"
          :placeholder="t('productProperties.searchValues')"
          class="w-full"
        />
        <p v-if="activeGroup" class="truncate text-sm font-medium text-highlighted">
          {{ activeGroup.name }}
        </p>
        <div v-if="activeSelectedValues.length" class="flex max-h-20 flex-wrap gap-2 overflow-y-auto">
          <UBadge
            v-for="property in activeSelectedValues"
            :key="property.id"
            color="primary"
            variant="subtle"
          >
            {{ property.labels?.[locale] || property.name }}
            <UButton
              icon="i-lucide-x"
              color="primary"
              variant="link"
              size="xs"
              :aria-label="t('productVariantGeneration.deselect', { name: property.labels?.[locale] || property.name })"
              @click="toggleProperty(property, false)"
            />
          </UBadge>
        </div>
      </div>
      <div
        data-testid="property-value-scroll"
        class="property-group-value-picker__scroll space-y-2 p-4"
        @scroll="loadNextPage"
      >
        <UCheckbox
          v-for="property in records"
          :key="property.id"
          :label="property.labels?.[locale] || property.name"
          :model-value="selected.has(property.id)"
          class="flex rounded-md px-3 py-2 hover:bg-elevated/60"
          @update:model-value="toggleProperty(property, $event)"
        />
        <p v-if="loading" role="status" class="py-4 text-center text-sm text-muted">
          {{ t('common.loading') }}
        </p>
        <div v-else-if="failed" class="space-y-3 py-4 text-center">
          <p class="text-sm text-muted">
            {{ t('common.loadOptionsFailed') }}
          </p>
          <UButton
            :label="t('common.retrySearch')"
            color="neutral"
            variant="outline"
            @click="retry"
          />
        </div>
        <p v-else-if="!records.length" class="py-8 text-center text-sm text-muted">
          {{ t('productProperties.noValues') }}
        </p>
      </div>
    </section>
  </div>
</template>

<style scoped src="./property-group-value-picker.css" />
