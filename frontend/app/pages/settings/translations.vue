<script setup lang="ts">
const auth = useAuth()
const toast = useToast()
const { t } = useI18n()
const defaultSaving = ref(false)
const languagesSaving = ref(false)
const defaultSnippetLocale = ref('en-GB')
const enabledSnippetLocales = ref<string[]>([])

type SnippetLocale = {
  code: string
  label: string
  enabled: boolean
}

const {
  data: localesData,
  refresh: refreshSnippetLocales,
} = await useAsyncData('settings-snippet-locales', () =>
  apiFetch<{ locales: SnippetLocale[] }>('/tenant/locales'),
)

const snippetLocales = computed(() => localesData.value?.locales ?? [])
const activeDefaultSnippetLocale = computed(
  () => auth.tenant.value?.defaultSnippetLocale || 'en-GB',
)
const enabledLocaleOptions = computed(() =>
  snippetLocales.value
    .filter((locale) =>
      (auth.tenant.value?.enabledSnippetLocales || ['bs-BA', 'de-DE', 'en-GB']).includes(
        locale.code,
      ),
    )
    .map(({ code, label }) => ({ code, label })),
)
const defaultLocaleItems = computed(() =>
  enabledLocaleOptions.value.map((locale) => ({
    label: `${localeFlag(locale.code)} ${locale.label}`,
    value: locale.code,
  })),
)

watch(
  auth.tenant,
  (tenant) => {
    defaultSnippetLocale.value = tenant?.defaultSnippetLocale || 'en-GB'
    enabledSnippetLocales.value = tenant?.enabledSnippetLocales || [
      'bs-BA',
      'de-DE',
      'en-GB',
    ]
  },
  { immediate: true },
)

const toggleSnippetLocale = (code: string, enabled: boolean) => {
  if (code === activeDefaultSnippetLocale.value) return

  enabledSnippetLocales.value = enabled
    ? [...new Set([...enabledSnippetLocales.value, code])]
    : enabledSnippetLocales.value.filter((locale) => locale !== code)
}

const persist = async (
  defaultLocale: string,
  enabledLocales: string[],
): Promise<boolean> => {
  if (!auth.tenant.value) return false

  try {
    const { tenant } = await apiFetch<{ tenant: AuthTenant }>('/tenant', {
      method: 'PATCH',
      body: {
        name: auth.tenant.value.name,
        oib: auth.tenant.value.oib || '',
        pdv: auth.tenant.value.pdv || '',
        phone: auth.tenant.value.phone || '',
        email: auth.tenant.value.email || '',
        website: auth.tenant.value.website || '',
        defaultSnippetLocale: defaultLocale,
        enabledSnippetLocales: enabledLocales,
      },
    })

    auth.tenant.value = tenant
    await refreshSnippetLocales()
    clearNuxtData([
      'catalogue-product-locales',
      'product-locales',
      'manufacturer-locales',
      'manufacturer-detail-locales',
      'category-locales',
      'category-detail-locales',
      'property-group-locales',
      'custom-field-list-locales',
      'custom-field-detail-locales',
      'shop-units-locales',
      'shop-taxes-locales',
      'shop-delivery-times-locales',
    ])
    toast.add({
      title: t('common.saved'),
      description: t('common.changesSaved'),
      icon: 'i-lucide-check',
      color: 'success',
    })
    return true
  } catch (error) {
    toast.add({
      title: t('common.error'),
      description: error instanceof Error ? error.message : t('common.tryAgain'),
      color: 'error',
    })
    return false
  }
}

const saveDefaultLanguage = async () => {
  if (!auth.tenant.value) return

  defaultSaving.value = true
  await persist(defaultSnippetLocale.value, auth.tenant.value.enabledSnippetLocales)
  defaultSaving.value = false
}

const saveEnabledLanguages = async () => {
  if (!auth.tenant.value) return

  languagesSaving.value = true
  await persist(activeDefaultSnippetLocale.value, enabledSnippetLocales.value)
  languagesSaving.value = false
}
</script>

<template>
  <div class="space-y-6">
    <UPageCard
      :title="t('settings.defaultSnippetLanguage')"
      :description="t('settings.defaultSnippetLanguageDescription')"
      variant="subtle"
    >
      <template #footer>
        <div class="flex items-center justify-between gap-4">
          <USelect
            v-model="defaultSnippetLocale"
            :items="defaultLocaleItems"
            class="w-full sm:w-64"
          />
          <UButton
            :label="t('common.save')"
            :loading="defaultSaving"
            @click="saveDefaultLanguage"
          />
        </div>
      </template>
    </UPageCard>

    <UPageCard
      :title="t('settings.snippetLanguages')"
      :description="t('settings.snippetLanguagesDescription')"
      variant="subtle"
    >
      <div class="divide-y divide-default">
        <div
          v-for="locale in snippetLocales"
          :key="locale.code"
          class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
        >
          <div class="flex min-w-0 items-center gap-3">
            <span class="text-xl leading-none">{{ localeFlag(locale.code) }}</span>
            <div class="min-w-0">
              <p class="truncate font-medium text-highlighted">{{ locale.label }}</p>
              <p class="text-sm text-muted">{{ locale.code }}</p>
            </div>
          </div>
          <USwitch
            :model-value="enabledSnippetLocales.includes(locale.code)"
            :disabled="locale.code === activeDefaultSnippetLocale"
            :aria-label="locale.label"
            @update:model-value="
              (enabled) => toggleSnippetLocale(locale.code, Boolean(enabled))
            "
          />
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end">
          <UButton
            :label="t('common.save')"
            :loading="languagesSaving"
            @click="saveEnabledLanguages"
          />
        </div>
      </template>
    </UPageCard>
  </div>
</template>
