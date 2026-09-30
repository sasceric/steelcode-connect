<script setup lang="ts">
import type { Member } from '~/types'

const { t } = useI18n()
const auth = useAuth()
const q = ref('')
const memberName = computed(() => {
  const user = auth.user.value
  if (!user) {
    return '—'
  }

  return [user.firstName, user.lastName].filter(Boolean).join(' ') || user.email
})
const members = computed<Member[]>(() => [
  {
    name: memberName.value,
    username: auth.user.value?.email || '—',
    role: 'owner',
    avatar: { alt: memberName.value }
  },
  {
    name: t('settings.previewWarehouseOperator'),
    username: 'operator@example.invalid',
    role: 'warehouse_operator',
    avatar: { alt: t('settings.previewWarehouseOperator') }
  },
  {
    name: t('settings.previewPurchasing'),
    username: 'purchasing@example.invalid',
    role: 'purchasing',
    avatar: { alt: t('settings.previewPurchasing') }
  }
])
const filteredMembers = computed(() => {
  const search = q.value.trim().toLocaleLowerCase()
  return search
    ? members.value.filter(member => `${member.name} ${member.username}`.toLocaleLowerCase().includes(search))
    : members.value
})
</script>

<template>
  <div class="space-y-4">
    <UPageCard
      :title="t('nav.members')"
      :description="t('settings.membersDescription')"
      variant="naked"
      orientation="horizontal"
    >
      <UButton
        :label="t('settings.invitePeople')"
        color="neutral"
        class="w-fit lg:ms-auto"
        disabled
      />
    </UPageCard>

    <UAlert
      icon="i-lucide-shield-alert"
      color="warning"
      variant="subtle"
      :title="t('settings.membersNotReady')"
      :description="t('settings.membersNotReadyDescription')"
    />

    <UPageCard
      variant="subtle"
      :ui="{
        container: 'p-0 sm:p-0 gap-y-0',
        wrapper: 'items-stretch',
        header: 'p-4 mb-0 border-b border-default'
      }"
    >
      <template #header>
        <UInput
          v-model="q"
          icon="i-lucide-search"
          :placeholder="t('settings.searchMembers')"
          class="w-full"
        />
      </template>

      <SettingsMembersList :members="filteredMembers" />
    </UPageCard>
  </div>
</template>
