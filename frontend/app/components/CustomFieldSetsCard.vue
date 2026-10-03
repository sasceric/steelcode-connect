<script setup lang="ts">
type Field = {
  technicalName: string
  labels: Record<string, string>
  config: { entityType?: string }
}

type FieldSet = {
  id: string
  technicalName: string
  labels: Record<string, string>
  relations: string[]
  fields: Field[]
}

const props = defineProps<{
  title: string
  entityType: string
  fields: Record<string, unknown> | null | undefined
}>()

const { locale } = useI18n()
const { tenant } = useAuth()
const definitionsKey = computed(() => `custom-field-card-definitions:${tenant.value?.id || 'none'}`)
const { data } = await useAsyncData(definitionsKey, () =>
  tenant.value
    ? apiFetch<{ sets: FieldSet[] }>('/custom-field-sets')
    : Promise.resolve({ sets: [] as FieldSet[] }))

const cards = computed(() => {
  const values = props.fields || {}
  const assigned = new Set<string>()
  const result: { id: string, title: string, fields: Record<string, unknown> }[] = []

  for (const set of data.value?.sets || []) {
    if (!set.relations.includes(props.entityType)) continue
    const fields: Record<string, unknown> = {}
    for (const field of set.fields) {
      if (field.config.entityType && field.config.entityType !== props.entityType) continue
      if (!(field.technicalName in values)) continue
      assigned.add(field.technicalName)
      const label = field.labels[locale.value] || field.labels['en-GB'] || field.technicalName
      // Keep fields from separate connections visible even if labels match.
      fields[label in fields ? `${label} (${field.technicalName.slice(4, 16)})` : label] = values[field.technicalName]
    }
    if (Object.keys(fields).length) {
      result.push({
        id: set.id,
        title: set.labels[locale.value] || set.labels['en-GB'] || set.technicalName,
        fields
      })
    }
  }
  const other = Object.fromEntries(Object.entries(values).filter(([key]) => !assigned.has(key)))
  if (Object.keys(other).length || !result.length) {
    result.push({ id: 'other', title: props.title, fields: other })
  }
  return result
})
</script>

<template>
  <div class="space-y-4">
    <SourceFieldsCard
      v-for="card in cards"
      :key="card.id"
      :title="card.title"
      :fields="card.fields"
    />
  </div>
</template>
