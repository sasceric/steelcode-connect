<script setup lang="ts">
type LocaleOption = {
  code: string
  label: string
}

const props = defineProps<{
  modelValue: string
  options: LocaleOption[]
  defaultLocale: string
  active: boolean
  creating: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const locale = computed({
  get: () => (props.creating ? props.defaultLocale : props.modelValue),
  set: (value: string) => emit('update:modelValue', value),
})

watch(
  () => ({
    active: props.active,
    creating: props.creating,
    defaultLocale: props.defaultLocale,
  }),
  ({ active, creating, defaultLocale }) => {
    if (active && creating && props.modelValue !== defaultLocale) {
      emit('update:modelValue', defaultLocale)
    }
  },
  { immediate: true },
)
</script>

<template>
  <UFormField :label="$t('common.language')">
    <LocaleSelect
      v-model="locale"
      :options="options"
      :disabled="creating"
      class="w-full"
    />
  </UFormField>
</template>
