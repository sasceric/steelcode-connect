<script setup lang="ts">
type Option = {
  technicalValue: string
  labels: Record<string, string>
}

type FieldConfig = {
  required?: boolean
  helpText?: Record<string, string>
  placeholder?: Record<string, string>
  min?: number
  max?: number
  step?: number
  dateType?: string
  multiSelect?: boolean
}

type Field = {
  id: string
  technicalName: string
  type: string
  labels: Record<string, string>
  config: FieldConfig
  options: Option[]
}

const props = defineProps<{
  modelValue: Record<string, unknown>
  fields: Field[]
  locale: string
  fallbackLocale: string
  datePlaceholder: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, unknown>]
}>()

const label = (field: Field) =>
  field.labels[props.locale] || field.labels[props.fallbackLocale] || field.technicalName

const optionLabel = (option: Option) =>
  option.labels[props.locale] || option.labels[props.fallbackLocale] || option.technicalValue

const selectValue = (technicalName: string): string | string[] | undefined => {
  const value = props.modelValue[technicalName]

  if (typeof value === 'string' || Array.isArray(value)) {
    return value
  }

  return undefined
}

const update = (technicalName: string, value: unknown) => {
  emit('update:modelValue', {
    ...props.modelValue,
    [technicalName]: value
  })
}
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2">
    <UFormField
      v-for="field in fields"
      :key="field.id"
      :label="label(field)"
      :name="field.technicalName"
      :required="Boolean(field.config.required)"
      :class="field.type === 'editor' ? 'sm:col-span-2' : ''"
    >
      <template #label>
        <span class="text-primary">{{ label(field) }}</span>
      </template>
      <template v-if="field.config.helpText?.[locale] || field.config.helpText?.[fallbackLocale]" #hint>
        <UTooltip :text="field.config.helpText?.[locale] || field.config.helpText?.[fallbackLocale]">
          <UIcon name="i-lucide-circle-help" class="size-4 cursor-help text-primary" />
        </UTooltip>
      </template>
      <UInput
        v-if="field.type === 'text'"
        :model-value="modelValue[field.technicalName] as string"
        class="w-full"
        :placeholder="field.config.placeholder?.[locale] || field.config.placeholder?.[fallbackLocale]"
        @update:model-value="update(field.technicalName, $event)"
      />
      <UInput
        v-else-if="field.type === 'number'"
        :model-value="modelValue[field.technicalName] as string"
        type="number"
        inputmode="decimal"
        :min="field.config.min"
        :max="field.config.max"
        :step="field.config.step || 'any'"
        class="w-full"
        @update:model-value="update(field.technicalName, $event)"
      />
      <CustomFieldDateInput
        v-else-if="field.type === 'date'"
        :model-value="modelValue[field.technicalName] as string"
        :with-time="field.config.dateType !== 'date'"
        :placeholder="datePlaceholder"
        class="w-full"
        @update:model-value="update(field.technicalName, $event)"
      />
      <ColorPickerField
        v-else-if="field.type === 'color'"
        :model-value="modelValue[field.technicalName] as string"
        @update:model-value="update(field.technicalName, $event)"
      />
      <RichTextEditor
        v-else-if="field.type === 'editor'"
        :model-value="modelValue[field.technicalName] as string"
        class="w-full"
        @update:model-value="update(field.technicalName, $event)"
      />
      <UCheckbox
        v-else-if="field.type === 'checkbox'"
        :model-value="Boolean(modelValue[field.technicalName])"
        :label="label(field)"
        @update:model-value="update(field.technicalName, $event)"
      />
      <USwitch
        v-else-if="field.type === 'switch'"
        :model-value="Boolean(modelValue[field.technicalName])"
        @update:model-value="update(field.technicalName, $event)"
      />
      <USelect
        v-else-if="field.type === 'select'"
        :model-value="selectValue(field.technicalName)"
        :items="field.options.map((option) => ({ label: optionLabel(option), value: option.technicalValue }))"
        :multiple="Boolean(field.config.multiSelect)"
        class="w-full"
        @update:model-value="update(field.technicalName, $event)"
      />
      <UInput
        v-else
        :model-value="modelValue[field.technicalName] as string"
        :placeholder="field.technicalName"
        class="w-full"
        @update:model-value="update(field.technicalName, $event)"
      />
    </UFormField>
  </div>
</template>
