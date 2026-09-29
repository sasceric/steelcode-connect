<script setup lang="ts">
import { localeFlag } from '~/composables/useLocaleFlag'

type LocaleItem = {
  code: string
  label: string
}

const props = defineProps<{
  modelValue: string
  options: LocaleItem[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const items = computed(() =>
  props.options.map((locale) => ({
    label: `${localeFlag(locale.code)} ${locale.label}`,
    value: locale.code,
  })),
)
</script>

<template>
  <USelect
    :model-value="modelValue"
    :items="items"
    v-bind="$attrs"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
