<script setup lang="ts">
import type { Member } from '~/types'

defineProps<{
  members: Member[]
}>()

const { t } = useI18n()
const roleOptions = computed(() => [
  { label: t('settings.memberRoles.owner'), value: 'owner' },
  { label: t('settings.memberRoles.warehouseManager'), value: 'warehouse_manager' },
  { label: t('settings.memberRoles.warehouseOperator'), value: 'warehouse_operator' },
  { label: t('settings.memberRoles.purchasing'), value: 'purchasing' },
  { label: t('settings.memberRoles.salesSupport'), value: 'sales_support' },
  { label: t('settings.memberRoles.viewer'), value: 'viewer' },
])
</script>

<template>
  <ul role="list" class="divide-y divide-default">
    <li
      v-for="(member, index) in members"
      :key="index"
      class="flex items-center justify-between gap-3 py-3 px-4 sm:px-6"
    >
      <div class="flex items-center gap-3 min-w-0">
        <UAvatar v-bind="member.avatar" size="md" />

        <div class="text-sm min-w-0">
          <p class="text-highlighted font-medium truncate">
            {{ member.name }}
          </p>
          <p class="text-muted truncate">
            {{ member.username }}
          </p>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <USelect
          :model-value="member.role"
          :items="roleOptions"
          value-key="value"
          label-key="label"
          color="neutral"
          disabled
        />

        <UButton
          icon="i-lucide-ellipsis-vertical"
          color="neutral"
          variant="ghost"
          disabled
        />
      </div>
    </li>
  </ul>
</template>
