<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

const auth = useAuth()
const toast = useToast()
const { setLocale, t } = useI18n()
const saving = ref(false)

const profileSchema = z.object({
  firstName: z.string().max(100, 'Must be 100 characters or less'),
  lastName: z.string().max(100, 'Must be 100 characters or less'),
  title: z.string().max(100, 'Must be 100 characters or less'),
  phone: z.string().max(32, 'Must be 32 characters or less'),
  locale: z.enum(['en', 'bs', 'de']),
})

type ProfileSchema = z.output<typeof profileSchema>

const profile = reactive<ProfileSchema>({
  firstName: '',
  lastName: '',
  title: '',
  phone: '',
  locale: 'en',
})

const localeOptions = computed(() => [
  { label: t('languages.bs'), value: 'bs' },
  { label: t('languages.en'), value: 'en' },
  { label: t('languages.de'), value: 'de' },
])

const syncProfile = () => {
  Object.assign(profile, {
    firstName: auth.user.value?.firstName || '',
    lastName: auth.user.value?.lastName || '',
    title: auth.user.value?.title || '',
    phone: auth.user.value?.phone || '',
    locale:
      auth.user.value?.locale === 'en' || auth.user.value?.locale === 'de'
        ? auth.user.value.locale
        : 'bs',
  })
}

watch(auth.user, syncProfile, { immediate: true })

const onSubmit = async (event: FormSubmitEvent<ProfileSchema>) => {
  saving.value = true

  try {
    const { user } = await apiFetch<{ user: AuthUser }>('/auth/profile', {
      method: 'PATCH',
      body: event.data,
    })

    auth.user.value = user
    await setLocale(user.locale === 'en' || user.locale === 'de' ? user.locale : 'bs')
    toast.add({
      title: t('settings.profileUpdated'),
      description: t('settings.profileSaved'),
      icon: 'i-lucide-check',
      color: 'success',
    })
  } catch (error) {
    toast.add({
      title: t('common.error'),
      description: error instanceof Error ? error.message : t('common.tryAgain'),
      color: 'error',
    })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <UForm id="profile-settings" :schema="profileSchema" :state="profile" @submit="onSubmit">
    <UPageCard
      :title="t('settings.profile')"
      :description="t('settings.profileDescription')"
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    >
      <UButton
        form="profile-settings"
        :label="t('common.saveChanges')"
        color="neutral"
        type="submit"
        :loading="saving"
        class="w-fit lg:ms-auto"
      />
    </UPageCard>

    <UPageCard variant="subtle">
      <UFormField
        name="firstName"
        :label="t('settings.firstName')"
        :description="t('settings.identityDescription')"
        class="flex items-start justify-between gap-4 max-sm:flex-col"
      >
        <UInput v-model="profile.firstName" autocomplete="given-name" />
      </UFormField>

      <USeparator />

      <UFormField
        name="lastName"
        :label="t('settings.lastName')"
        :description="t('settings.identityDescription')"
        class="flex items-start justify-between gap-4 max-sm:flex-col"
      >
        <UInput v-model="profile.lastName" autocomplete="family-name" />
      </UFormField>

      <USeparator />

      <UFormField
        :label="t('common.email')"
        :description="t('settings.emailDescription')"
        class="flex items-start justify-between gap-4 max-sm:flex-col"
      >
        <UInput
          :model-value="auth.user.value?.email || ''"
          type="email"
          autocomplete="email"
          disabled
        />
      </UFormField>

      <USeparator />

      <UFormField
        name="title"
        :label="t('settings.jobTitle')"
        :description="t('settings.jobTitleDescription')"
        class="flex items-start justify-between gap-4 max-sm:flex-col"
      >
        <UInput v-model="profile.title" autocomplete="organization-title" />
      </UFormField>

      <USeparator />

      <UFormField
        name="phone"
        :label="t('common.phone')"
        :description="t('settings.phoneDescription')"
        class="flex items-start justify-between gap-4 max-sm:flex-col"
      >
        <UInput v-model="profile.phone" type="tel" autocomplete="tel" />
      </UFormField>

      <USeparator />

      <UFormField
        name="locale"
        :label="t('common.language')"
        :description="t('settings.languageDescription')"
        class="flex items-start justify-between gap-4 max-sm:flex-col"
      >
        <USelect v-model="profile.locale" :items="localeOptions" />
      </UFormField>
    </UPageCard>
  </UForm>
</template>
