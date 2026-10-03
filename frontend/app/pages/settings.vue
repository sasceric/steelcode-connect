<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const localePath = useLocalePath()
const { t } = useI18n()

const links = computed(
  () =>
    [
      [
        {
          label: t('nav.general'),
          icon: 'i-lucide-settings-2',
          to: localePath('/settings'),
          exact: true
        },
        {
          label: t('nav.profile'),
          icon: 'i-lucide-user',
          to: localePath('/settings/profile')
        },
        {
          label: t('nav.translations'),
          icon: 'i-lucide-languages',
          to: localePath('/settings/translations')
        },
        {
          label: t('nav.members'),
          icon: 'i-lucide-users',
          to: localePath('/settings/members')
        },
        {
          label: t('nav.notifications'),
          icon: 'i-lucide-bell',
          to: localePath('/settings/notifications')
        },
        {
          label: t('nav.security'),
          icon: 'i-lucide-shield',
          to: localePath('/settings/security')
        }
      ],
      [
        {
          label: 'Documentation',
          icon: 'i-lucide-book-open',
          to: 'https://ui.nuxt.com/docs/getting-started/installation/nuxt',
          target: '_blank'
        }
      ]
    ] satisfies NavigationMenuItem[][]
)
</script>

<template>
  <UDashboardPanel id="settings" :ui="{ body: 'lg:py-12' }">
    <template #header>
      <UDashboardNavbar :title="t('settings.title')">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar>
        <!-- NOTE: The `-mx-1` class is used to align with the `DashboardSidebarCollapse` button here. -->
        <UNavigationMenu :items="links" highlight class="-mx-1 flex-1" />
      </UDashboardToolbar>
    </template>

    <template #body>
      <div class="mx-auto flex w-full flex-col gap-4 sm:gap-6 lg:gap-12 lg:max-w-2xl">
        <NuxtPage />
      </div>
    </template>
  </UDashboardPanel>
</template>
