<?php

namespace App\Integration;

final class WooCommerceSalesMapper
{
    public function address(
        array $source,
        ?string $externalId = null,
    ): array
    {
        $hasAddress = false;
        foreach (['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone'] as $field) {
            if ($this->string($source[$field] ?? null) !== null) {
                $hasAddress = true;
                break;
            }
        }
        if (!$hasAddress) {
            return [];
        }
        return [
            'externalId' => $externalId,
            'firstName' => $this->string($source['first_name'] ?? null),
            'lastName' => $this->string($source['last_name'] ?? null),
            'company' => $this->string($source['company'] ?? null),
            'street' => $this->string($source['address_1'] ?? null),
            'additionalAddressLine1' => $this->string($source['address_2'] ?? null),
            'city' => $this->string($source['city'] ?? null),
            'zipcode' => $this->string($source['postcode'] ?? null),
            'countryCode' => $this->string($source['country'] ?? null),
            'countryState' => $this->string($source['state'] ?? null),
            'phone' => $this->string($source['phone'] ?? null),
            'sourceFields' => SourcePayloadSanitizer::sanitize($source),
        ];
    }

    public function customer(array $source): array
    {
        $id = (int) ($source['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('WooCommerce customer has no ID.');
        }
        $billing = $source['billing'] ?? [];
        return [
            'externalId' => (string) $id,
            'customerNumber' => (string) $id,
            'email' => $this->string($source['email'] ?? null),
            'firstName' => $this->string($source['first_name'] ?? null) ?? $this->string($billing['first_name'] ?? null),
            'lastName' => $this->string($source['last_name'] ?? null) ?? $this->string($billing['last_name'] ?? null),
            'company' => $this->string($billing['company'] ?? null),
            'phone' => $this->string($billing['phone'] ?? null),
            'guest' => false,
            'accountType' => empty($billing['company']) ? 'private' : 'business',
            'defaultBillingAddressId' => $this->address($billing) !== [] ? 'billing' : null,
            'defaultShippingAddressId' => $this->address($source['shipping'] ?? []) !== [] ? 'shipping' : null,
            'customFields' => $this->metadata($source['meta_data'] ?? []),
            'sourceFields' => SourcePayloadSanitizer::sanitize($source),
        ];
    }

    public function order(array $source): array
    {
        $id = (int) ($source['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('WooCommerce order has no ID.');
        }
        $currency = strtoupper((string) ($source['currency'] ?? ''));
        $billing = $source['billing'] ?? [];
        $shipping = $this->address($source['shipping'] ?? []);
        $lines = [];
        foreach (['line_items' => 'product', 'fee_lines' => 'fee'] as $collection => $type) {
            foreach ($source[$collection] ?? [] as $line) {
                $quantity = $type === 'product' ? (float) ($line['quantity'] ?? 0) : 1;
                if ($quantity <= 0 || empty($line['id'])) {
                    throw new \InvalidArgumentException('WooCommerce order has an invalid line.');
                }
                $net = $this->number($line['total'] ?? null);
                $tax = $this->number($line['total_tax'] ?? null);
                $gross = $net + $tax;
                $lines[] = [
                    'externalLineId' => $type . ':' . $line['id'],
                    'type' => $type,
                    'sku' => $type === 'product' ? $this->string($line['resolvedSku'] ?? $line['sku'] ?? null) : null,
                    'name' => $this->string($line['name'] ?? null) ?? 'Fee',
                    'quantity' => $quantity,
                    'commercial' => [
                        'currencyCode' => $currency,
                        'unitNet' => $net / $quantity,
                        'unitGross' => $gross / $quantity,
                        'totalNet' => $net,
                        'totalTax' => $tax,
                        'totalGross' => $gross,
                        'discountGross' => $type === 'product' ? $this->number($line['subtotal'] ?? null) + $this->number($line['subtotal_tax'] ?? null) - $gross : 0,
                        'taxes' => $line['taxes'] ?? [],
                    ],
                    'sourcePayload' => ['sourceFields' => SourcePayloadSanitizer::sanitize($line)],
                ];
            }
        }
        $deliveries = [];
        foreach ($source['shipping_lines'] ?? [] as $line) {
            $deliveries[] = [
                'externalId' => 'shipping:' . $line['id'],
                'methodExternalId' => $this->string($line['method_id'] ?? null),
                'method' => $this->string($line['method_title'] ?? null),
                // Core Woo does not expose per-delivery shipped quantities/tracking.
                'state' => null,
                'shippingGross' => $this->number($line['total'] ?? null) + $this->number($line['total_tax'] ?? null),
                'shippingAddress' => $shipping,
                'trackingCodes' => [],
                'positions' => [],
                'sourceFields' => SourcePayloadSanitizer::sanitize($line),
            ];
        }
        $gross = $this->number($source['total'] ?? null);
        $tax = $this->number($source['total_tax'] ?? null);
        $state = $this->string($source['status'] ?? null);
        $payments = [];
        if (
            !empty($source['payment_method'])
            || !empty($source['transaction_id'])
            || !empty($source['date_paid'])
            || ($source['needs_payment'] ?? false) === true
            || $state === 'refunded'
        ) {
            $payments[] = [
                'externalId' => 'order:' . $id . ':payment',
                'methodExternalId' => $this->string($source['payment_method'] ?? null),
                'method' => $this->string($source['payment_method_title'] ?? null),
                'reference' => $this->string($source['transaction_id'] ?? null),
                'state' => $state === 'refunded' ? 'refunded' : (!empty($source['date_paid']) ? 'paid' : 'unpaid'),
                'amount' => $gross,
                'currencyCode' => $currency,
                'sourceFields' => SourcePayloadSanitizer::sanitize([
                    'paymentMethod' => $source['payment_method'] ?? null,
                    'paymentMethodTitle' => $source['payment_method_title'] ?? null,
                    'transactionId' => $source['transaction_id'] ?? null,
                    'paidAt' => $source['date_paid_gmt'] ?? $source['date_paid'] ?? null,
                    'refunds' => $source['refunds'] ?? [],
                ]),
            ];
        }
        $customerId = (int) ($source['customer_id'] ?? 0);
        $safeSource = SourcePayloadSanitizer::sanitize($source);
        $safeSource['state'] = $state;
        return [
            'type' => 'placed',
            'externalId' => (string) $id,
            'externalNumber' => (string) ($source['number'] ?? $id),
            'orderedAt' => !empty($source['date_created_gmt']) ? $source['date_created_gmt'] . 'Z' : null,
            'customer' => [
                'externalId' => $customerId > 0 ? (string) $customerId : null,
                'guest' => $customerId === 0,
                'email' => $this->string($billing['email'] ?? null),
                'firstName' => $this->string($billing['first_name'] ?? null),
                'lastName' => $this->string($billing['last_name'] ?? null),
                'company' => $this->string($billing['company'] ?? null),
                'phone' => $this->string($billing['phone'] ?? null),
            ],
            'billingAddress' => $this->address($billing),
            'shippingAddress' => $shipping,
            'commercial' => [
                'currencyCode' => $currency,
                'taxStatus' => !empty($source['prices_include_tax']) ? 'gross' : 'net',
                'amountNet' => $gross - $tax,
                'amountTax' => $tax,
                'amountGross' => $gross,
                'shippingNet' => $this->number($source['shipping_total'] ?? null),
                'shippingTax' => $this->number($source['shipping_tax'] ?? null),
                'shippingGross' => $this->number($source['shipping_total'] ?? null) + $this->number($source['shipping_tax'] ?? null),
            ],
            'lines' => $lines,
            'payments' => $payments,
            'deliveries' => $deliveries,
            'sourcePayload' => [
                'woocommerceOrderId' => (string) $id,
                'shipmentQuantitiesUnavailable' => true,
                'state' => $state,
                'customFields' => $this->metadata($source['meta_data'] ?? []),
                'sourceFields' => $safeSource,
            ],
        ];
    }

    private function string(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    private function metadata(array $items): array
    {
        $fields = [];
        foreach (SourcePayloadSanitizer::sanitize($items) as $item) {
            if (is_array($item) && is_string($item['key'] ?? null)) {
                $fields[$item['key']] = $item['value'] ?? null;
            }
        }
        return $fields;
    }

    private function number(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (!is_numeric($value) || !is_finite((float) $value)) {
            throw new \InvalidArgumentException('WooCommerce returned an invalid monetary amount.');
        }
        return (float) $value;
    }
}
