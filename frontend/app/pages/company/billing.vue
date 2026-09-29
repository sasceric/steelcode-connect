<script setup lang="ts">
const company = useCompany()
const { invoices, paymentMethods, subscription } = company
const { t } = useI18n()

const money = (amount: number, currency = 'BAM') => {
  const label = currency === 'BAM' ? 'KM' : currency

  return `${(amount / 100).toFixed(2)} ${label}`
}

const defaultPaymentMethod = computed(() => paymentMethods.value.find((method) => method.isDefault))

onMounted(async () => {
  await Promise.all([
    company.loadSubscription(),
    company.loadInvoices(),
    company.loadPaymentMethods(),
  ])
})
</script>

<template>
  <UPageCard
    :title="t('company.billing')"
    :description="t('company.billingDescription')"
    variant="subtle"
  >
    <div class="grid gap-6 sm:grid-cols-2">
      <div>
        <p class="text-sm text-muted">
          {{ t('company.currentPlan') }}
        </p>
        <p class="font-semibold">
          {{ subscription?.name || t('company.noPlan') }}
        </p>
        <p v-if="subscription" class="text-sm text-muted">
          {{ money(subscription.price, subscription.currency) }} /
          {{ subscription.interval === 'annual' ? t('company.year') : t('company.month') }}
        </p>
        <UButton
          to="/company/pricing-plans"
          :label="t('company.changePlan')"
          size="sm"
          variant="subtle"
          class="mt-3"
        />
      </div>

      <div>
        <p class="text-sm text-muted">
          {{ t('company.paymentMethod') }}
        </p>
        <p class="font-semibold">
          {{ defaultPaymentMethod?.label || t('company.noPaymentMethod') }}
        </p>
        <UButton
          to="/company/payment-methods"
          :label="t('company.managePaymentMethods')"
          size="sm"
          variant="subtle"
          class="mt-3"
        />
      </div>
    </div>
  </UPageCard>

  <UPageCard
    :title="t('company.invoices')"
    :description="t('company.invoicesDescription')"
    variant="subtle"
  >
    <div class="divide-y divide-default">
      <div
        v-for="invoice in invoices"
        :key="invoice.id"
        class="flex items-center justify-between gap-4 py-4"
      >
        <div>
          <p class="font-medium">
            {{ invoice.number }}
          </p>
          <p class="text-sm text-muted">{{ invoice.billingPeriod }} · {{ invoice.plan }}</p>
        </div>

        <div class="flex items-center gap-4">
          <span>{{ money(invoice.amount, invoice.currency) }}</span>
          <UButton
            :to="company.invoicePdfUrl(invoice.id)"
            target="_blank"
            :label="t('company.openPdf')"
            size="sm"
            variant="subtle"
          />
        </div>
      </div>

      <p v-if="!invoices.length" class="py-6 text-sm text-muted">
        {{ t('company.noInvoices') }}
      </p>
    </div>
  </UPageCard>
</template>
