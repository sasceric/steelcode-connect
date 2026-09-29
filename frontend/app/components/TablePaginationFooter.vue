<script setup lang="ts">
defineProps<{
  total: number
}>()
const page = defineModel<number>('page', { required: true })
const pageSize = defineModel<number>('pageSize', { required: true })

const { t } = useI18n()

const pageSizeItems = [25, 50, 100].map(value => ({
  label: String(value),
  value
}))
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-3 border-t border-default !py-2">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted">
      <span>{{ t('common.rowsPerPage') }}</span>
      <USelect
        v-model="pageSize"
        :items="pageSizeItems"
        class="w-22"
      />
    </div>

    <UPagination
      v-model:page="page"
      :items-per-page="pageSize"
      :total="total"
      :disabled="total === 0"
    />
  </div>
</template>
