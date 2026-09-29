<script setup lang="ts">
const model = defineModel<string>({ default: '' })

const normalized = computed(() => (/^#[0-9a-fA-F]{6}$/.test(model.value) ? model.value : '#066AE0'))
const updateFromPicker = (event: Event) => {
  model.value = (event.target as HTMLInputElement).value.toUpperCase()
}
const updateFromText = (value: string) => {
  model.value = value.startsWith('#') ? value.toUpperCase() : `#${value.toUpperCase()}`
}
</script>

<template>
  <div class="flex items-center gap-2">
    <label
      class="relative grid size-10 shrink-0 cursor-pointer place-items-center rounded-md border border-default bg-default shadow-xs"
    >
      <span
        class="size-5 rounded border border-black/10"
        :style="{ backgroundColor: normalized }"
      />
      <input
        type="color"
        :value="normalized"
        class="absolute inset-0 size-full cursor-pointer opacity-0"
        @input="updateFromPicker"
      />
    </label>
    <UInput
      :model-value="model"
      placeholder="#066AE0"
      class="w-full"
      @update:model-value="updateFromText"
    />
  </div>
</template>
