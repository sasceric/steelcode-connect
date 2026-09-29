<script setup lang="ts">
const company = useCompany()
const { plans, subscription } = company
const toast = useToast()
const { t } = useI18n()
const interval = ref<Subscription['interval']>('monthly')
const saving = ref<string>()

const price = (plan: SubscriptionPlan) => {
  return interval.value === 'annual' ? plan.price * 12 * 0.9 : plan.price
}

const select = async (plan: SubscriptionPlan) => {
  saving.value = plan.code

  try {
    await company.selectPlan(plan.code, interval.value)
    toast.add({ title: t('company.planUpdated'), color: 'success' })
  } catch (error) {
    toast.add({
      title: error instanceof Error ? error.message : t('company.unableToSelectPlan'),
      color: 'error',
    })
  } finally {
    saving.value = undefined
  }
}

onMounted(async () => {
  await company.loadSubscription()
  interval.value = company.subscription.value?.interval || 'monthly'
})
</script>

<template>
  <div>
    <UPageCard
      :title="t('company.pricingPlans')"
      :description="t('company.pricingDescription')"
      variant="naked"
      class="mb-4"
    />

    <div class="mb-4 flex justify-center">
      <UTabs
        v-model="interval"
        :items="[
          { label: t('company.monthly'), value: 'monthly' },
          { label: t('company.annual'), value: 'annual' },
        ]"
        :content="false"
      />
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
      <UCard
        v-for="plan in plans"
        :key="plan.code"
        :class="{
          'ring-2 ring-primary':
            subscription?.code === plan.code && subscription?.interval === interval,
        }"
      >
        <div class="space-y-4">
          <div>
            <h3 class="text-lg font-semibold">
              {{ plan.name }}
            </h3>
            <p class="text-sm text-muted">
              {{
                plan.productLimit === null
                  ? t('company.unlimitedProducts')
                  : t('company.upToProducts', {
                      count: plan.productLimit.toLocaleString(),
                    })
              }}
            </p>
          </div>

          <p v-if="interval === 'annual'" class="text-sm text-error line-through">
            {{ ((plan.price * 12) / 100).toFixed(2) }} KM
          </p>
          <p class="text-3xl font-semibold">
            {{ (price(plan) / 100).toFixed(2) }}
            <span class="text-base text-muted">
              KM /
              {{ interval === 'annual' ? t('company.year') : t('company.month') }}
            </span>
          </p>

          <UButton
            :label="
              subscription?.code === plan.code && subscription?.interval === interval
                ? t('company.currentPlan')
                : t('company.selectPlan')
            "
            :disabled="subscription?.code === plan.code && subscription?.interval === interval"
            :loading="saving === plan.code"
            block
            @click="select(plan)"
          />
        </div>
      </UCard>
    </div>
  </div>
</template>
