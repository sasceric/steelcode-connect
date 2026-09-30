<script setup lang="ts">
type PriceInput = {
  minimumQuantity: number
  unitCost: number
  currency: string
  validFrom: string
  validUntil: string
}

type OfferFields = {
  supplierSku: string
  preferredCurrency: string
  minimumOrderQuantity: number
  purchaseUnit: string
  stockUnitsPerPurchaseUnit: number
  leadTimeDays: number | null
  preferred: boolean
  active: boolean
  prices: PriceInput[]
}

const props = defineProps<{
  currencies: { label: string, value: string }[]
  units: { label: string, value: string }[]
  compact?: boolean
}>()

const model = defineModel<OfferFields>({ required: true })
const { t } = useI18n()
const expanded = ref(!props.compact)
const primaryCurrency = computed({
  get: () => model.value.prices[0]?.currency ?? '',
  set: (currency: string) => {
    const firstPrice = model.value.prices[0]
    if (!firstPrice) return
    if (model.value.preferredCurrency === firstPrice.currency) {
      model.value.preferredCurrency = currency
    }
    firstPrice.currency = currency
  }
})

const addPrice = () => {
  model.value.prices.push({
    minimumQuantity: 1,
    unitCost: 0,
    currency: model.value.prices[0]?.currency ?? props.currencies[0]?.value ?? '',
    validFrom: '',
    validUntil: ''
  })
  expanded.value = true
}

const removePrice = (index: number) => {
  model.value.prices.splice(index, 1)
}

const clearPriceDate = (index: number, field: 'validFrom' | 'validUntil') => {
  const price = model.value.prices[index]
  if (price) price[field] = ''
}

const toggleExpanded = () => {
  expanded.value = !expanded.value
}
</script>

<template>
  <div class="space-y-3">
    <div class="grid grid-cols-2 gap-2 md:grid-cols-[minmax(7rem,1fr)_minmax(8rem,1fr)_minmax(8rem,1fr)_minmax(8rem,1fr)_auto] md:items-end">
      <UFormField :label="t('purchasing.minimumOrderQuantity')">
        <UInput
          v-model.number="model.minimumOrderQuantity"
          type="number"
          min="0.0001"
          step="0.0001"
          class="w-full"
        />
      </UFormField>
      <UFormField :label="t('purchasing.unitCost')">
        <UInput
          v-if="model.prices[0]"
          v-model.number="model.prices[0].unitCost"
          type="number"
          min="0"
          step="0.0001"
          class="w-full"
        />
        <span v-else class="text-xs text-muted">
          {{ t('purchasing.noPriceYet') }}
        </span>
      </UFormField>
      <UFormField :label="t('purchasing.currency')">
        <USelect
          v-if="model.prices[0]"
          v-model="primaryCurrency"
          :items="currencies"
          value-key="value"
          :placeholder="t('purchasing.selectCurrency')"
          class="w-full"
        />
      </UFormField>
      <UFormField :label="t('purchasing.purchaseUnit')">
        <USelect
          v-model="model.purchaseUnit"
          :items="units"
          value-key="value"
          :placeholder="t('purchasing.selectUnit')"
          class="w-full"
        />
      </UFormField>
      <UButton
        :label="expanded ? t('purchasing.lessDetails') : t('purchasing.moreDetails')"
        :icon="expanded ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
        color="neutral"
        variant="ghost"
        @click="toggleExpanded"
      />
    </div>

    <div v-if="expanded" class="space-y-4 border-t border-default pt-3">
      <div class="grid grid-cols-2 gap-3">
        <UFormField :label="t('purchasing.supplierSku')">
          <UInput
            v-model="model.supplierSku"
            class="w-full"
          />
        </UFormField>
        <UFormField :label="t('purchasing.leadTime')">
          <UInput
            v-model.number="model.leadTimeDays"
            type="number"
            min="0"
            class="w-full"
          />
        </UFormField>
        <UFormField :label="t('purchasing.replenishmentCurrency')">
          <USelect
            v-model="model.preferredCurrency"
            :items="currencies"
            value-key="value"
            class="w-full"
          />
        </UFormField>
        <UFormField :label="t('purchasing.stockUnitsPerPurchaseUnit')">
          <UInput
            v-model.number="model.stockUnitsPerPurchaseUnit"
            type="number"
            min="0.0001"
            step="0.0001"
            class="w-full"
          />
        </UFormField>
      </div>
      <p class="text-xs text-muted">
        {{ t('purchasing.conversionHelp') }}
      </p>
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <p class="text-sm font-medium text-highlighted">
            {{ t('purchasing.priceTiers') }}
          </p>
          <UButton
            :label="t('purchasing.addPriceTier')"
            icon="i-lucide-plus"
            color="neutral"
            variant="subtle"
            size="sm"
            @click="addPrice"
          />
        </div>
        <p class="text-xs text-muted">
          {{ t('purchasing.priceTierHelp') }}
        </p>
        <div
          v-for="(price, index) in model.prices"
          :key="index"
          class="rounded-md border border-default p-3"
        >
          <div class="grid grid-cols-2 gap-3 md:grid-cols-[1fr_1fr_1fr_auto] md:items-end">
            <UFormField :label="t('purchasing.priceFromQuantity')">
              <UInput
                v-model.number="price.minimumQuantity"
                type="number"
                min="0.0001"
                step="0.0001"
                class="w-full"
              />
            </UFormField>
            <UFormField v-if="index > 0" :label="t('purchasing.unitCost')">
              <UInput
                v-model.number="price.unitCost"
                type="number"
                min="0"
                step="0.0001"
                class="w-full"
              />
            </UFormField>
            <UFormField v-if="index > 0" :label="t('purchasing.currency')">
              <USelect
                v-model="price.currency"
                :items="currencies"
                value-key="value"
                class="w-full"
              />
            </UFormField>
            <div v-if="index === 0" class="hidden md:block" />
            <div v-if="index === 0" class="hidden md:block" />
            <UButton
              icon="i-lucide-trash-2"
              color="error"
              variant="ghost"
              :aria-label="t('purchasing.removePriceTier')"
              @click="removePrice(index)"
            />
          </div>
          <div class="mt-3 grid grid-cols-2 gap-3">
            <UFormField :label="t('purchasing.validFrom')">
              <div class="flex items-center gap-1">
                <CustomFieldDateInput
                  v-model="price.validFrom"
                  :with-time="false"
                  :placeholder="t('purchasing.noValidityDate')"
                />
                <UButton
                  icon="i-lucide-x"
                  color="neutral"
                  variant="ghost"
                  :aria-label="t('purchasing.clearDate')"
                  @click="clearPriceDate(index, 'validFrom')"
                />
              </div>
            </UFormField>
            <UFormField :label="t('purchasing.validUntil')">
              <div class="flex items-center gap-1">
                <CustomFieldDateInput
                  v-model="price.validUntil"
                  :with-time="false"
                  :placeholder="t('purchasing.noValidityDate')"
                />
                <UButton
                  icon="i-lucide-x"
                  color="neutral"
                  variant="ghost"
                  :aria-label="t('purchasing.clearDate')"
                  @click="clearPriceDate(index, 'validUntil')"
                />
              </div>
            </UFormField>
          </div>
        </div>
      </div>
      <div class="flex gap-4">
        <UCheckbox
          v-model="model.preferred"
          :label="t('purchasing.preferred')"
        />
        <UCheckbox
          v-model="model.active"
          :label="t('inventory.active')"
        />
      </div>
    </div>
  </div>
</template>
