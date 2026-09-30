<script setup lang="ts">
const props = defineProps<{
  title: string
  fields: Record<string, unknown> | null | undefined
}>()

const entries = computed(() => Object.entries(props.fields || {})
  .filter(([, value]) => value !== null && value !== undefined && value !== ''))

function displayValue(value: unknown): string {
  if (typeof value === 'object') {
    return JSON.stringify(value, null, 2)
  }

  return String(value)
}

function summary(value: object): string {
  return Array.isArray(value) ? `[${value.length}]` : `{${Object.keys(value).length}}`
}
</script>

<template>
  <UCard>
    <template #header>
      {{ title }}
    </template>
    <dl v-if="entries.length" class="max-h-96 space-y-3 overflow-auto text-sm">
      <div
        v-for="[name, value] in entries"
        :key="name"
        class="grid gap-1 border-b border-default pb-3 last:border-b-0 last:pb-0 sm:grid-cols-[minmax(9rem,35%)_1fr]"
      >
        <dt class="break-all text-muted">
          {{ name }}
        </dt>
        <dd class="min-w-0 whitespace-pre-wrap break-words font-mono text-xs text-highlighted">
          <details
            v-if="value !== null && typeof value === 'object'"
            class="group"
          >
            <summary class="cursor-pointer text-primary hover:underline">
              {{ summary(value) }}
            </summary>
            <pre class="mt-2 max-h-64 overflow-auto rounded-md bg-elevated p-2 text-xs">{{ displayValue(value) }}</pre>
          </details>
          <template v-else>
            {{ displayValue(value) }}
          </template>
        </dd>
      </div>
    </dl>
    <p v-else class="text-sm text-muted">
      —
    </p>
  </UCard>
</template>
