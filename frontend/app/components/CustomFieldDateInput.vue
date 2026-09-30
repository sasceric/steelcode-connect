<script setup lang="ts">
import { CalendarDate } from '@internationalized/date'

const model = defineModel<string>({ default: '' })
const props = withDefaults(defineProps<{ withTime?: boolean; placeholder?: string; disabled?: boolean }>(), {
  withTime: true,
  placeholder: '',
  disabled: false,
})
const calendar = computed<CalendarDate | undefined>({
  get: () => {
    if (!model.value) return undefined
    const date = model.value.split('T')[0] ?? ''
    const [year, month, day] = date.split('-').map(Number)
    return year && month && day ? new CalendarDate(year, month, day) : undefined
  },
  set: (value) => {
    if (!value) {
      model.value = ''
      return
    }
    const date = `${value.year.toString().padStart(4, '0')}-${value.month.toString().padStart(2, '0')}-${value.day.toString().padStart(2, '0')}`
    model.value = props.withTime ? `${date}T${time.value || '00:00'}` : date
  },
})
const time = computed({
  get: () => (model.value.includes('T') ? (model.value.split('T')[1] ?? '').slice(0, 5) : ''),
  set: (value: string) => {
    if (calendar.value) model.value = `${model.value.split('T')[0]}T${value || '00:00'}`
  },
})
const label = computed(() =>
  model.value
    ? new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: props.withTime ? 'short' : undefined,
      }).format(new Date(props.withTime ? model.value : `${model.value}T00:00:00`))
    : props.placeholder,
)
</script>

<template>
  <UPopover :content="{ align: 'start' }" :modal="true" :disabled="disabled">
    <UButton
      :label="label"
      icon="i-lucide-calendar-clock"
      color="neutral"
      variant="outline"
      class="w-full justify-start font-normal"
      :disabled="disabled"
    />
    <template #content>
      <div class="space-y-3 p-3">
        <UCalendar v-model="calendar" />
        <UFormField v-if="withTime" label="Time">
          <UInput v-model="time" type="time" class="w-full" />
        </UFormField>
      </div>
    </template>
  </UPopover>
</template>
