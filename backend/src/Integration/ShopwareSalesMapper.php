<?php

namespace App\Integration;

final class ShopwareSalesMapper
{
    /** @return array<string, mixed> */
    public function customerAssociations(): array
    {
        return [
            'group' => [],
            'language' => ['associations' => ['locale' => []]],
            'salutation' => [],
            'lastPaymentMethod' => [],
            'tags' => [],
            'defaultBillingAddress' => ['associations' => ['country' => [], 'countryState' => []]],
            'defaultShippingAddress' => ['associations' => ['country' => [], 'countryState' => []]],
            'addresses' => ['associations' => ['country' => [], 'countryState' => []]],
        ];
    }

    /** @return array<string, mixed> */
    public function orderAssociations(): array
    {
        return [
            'currency' => [],
            'language' => ['associations' => ['locale' => []]],
            'tags' => [],
            'orderCustomer' => [],
            'lineItems' => [],
            'addresses' => ['associations' => ['country' => [], 'countryState' => []]],
            'stateMachineState' => [],
            'transactions' => ['associations' => ['paymentMethod' => [], 'stateMachineState' => []]],
            'deliveries' => ['associations' => [
                'shippingMethod' => [],
                'shippingOrderAddress' => ['associations' => ['country' => [], 'countryState' => []]],
                'stateMachineState' => [],
                'positions' => [],
            ]],
        ];
    }

    /**
     * Shopware Admin API puts requested associations in JSON:API `included`.
     * Resolve only the current page so memory remains bounded.
     *
     * @param array<string, mixed> $source
     * @param list<array<string, mixed>> $included
     * @return array<string, mixed>
     */
    public function withIncluded(array $source, array $included): array
    {
        $index = [];
        foreach ($included as $entity) {
            $type = $entity['type'] ?? null;
            $id = $entity['id'] ?? null;
            if (is_string($type) && is_string($id)) {
                $index[$type.':'.$id] = $entity;
            }
        }

        return $this->hydrate($source, $index, 0);
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    public function customer(array $source): array
    {
        $attributes = $this->attributes($source);
        $sourceFields = $this->sourceFields($source);
        $externalId = $this->string($source['id'] ?? $attributes['id'] ?? null);
        if ($externalId === null) {
            throw new \InvalidArgumentException('Shopware customer has no ID.');
        }

        return [
            'externalId' => $externalId,
            'email' => $this->string($attributes['email'] ?? null),
            'firstName' => $this->string($attributes['firstName'] ?? null),
            'lastName' => $this->string($attributes['lastName'] ?? null),
            'company' => $this->string($attributes['company'] ?? null),
            'phone' => $this->string($attributes['phoneNumber'] ?? null),
            'guest' => (bool) ($attributes['guest'] ?? false),
            'customerNumber' => $this->string($attributes['customerNumber'] ?? null),
            'accountType' => $this->string($attributes['accountType'] ?? null),
            'title' => $this->string($attributes['title'] ?? null),
            'active' => isset($attributes['active']) ? (bool) $attributes['active'] : null,
            'vatIds' => is_array($attributes['vatIds'] ?? null) ? $attributes['vatIds'] : [],
            'defaultBillingAddressId' => $this->string($attributes['defaultBillingAddressId'] ?? null),
            'defaultShippingAddressId' => $this->string($attributes['defaultShippingAddressId'] ?? null),
            'languageExternalId' => $this->string($attributes['languageId'] ?? null),
            'groupExternalId' => $this->string($attributes['groupId'] ?? null),
            'salesChannelExternalId' => $this->string($attributes['salesChannelId'] ?? null),
            'affiliateCode' => $this->string($attributes['affiliateCode'] ?? null),
            'campaignCode' => $this->string($attributes['campaignCode'] ?? null),
            'customFields' => is_array($sourceFields['customFields'] ?? null) ? $sourceFields['customFields'] : [],
            'sourceFields' => $sourceFields,
        ];
    }

    /** @param array<string, mixed> $source @return list<array<string, mixed>> */
    public function customerAddresses(array $source): array
    {
        $addresses = [];
        foreach ($this->relations($source, 'addresses') as $address) {
            $snapshot = $this->address($address);
            if ($snapshot['externalId'] !== null) {
                $addresses[] = $snapshot;
            }
        }

        return $addresses;
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    public function address(array $source): array
    {
        $attributes = $this->attributes($source);
        if ($attributes === []) {
            return [];
        }

        $sourceFields = $this->sourceFields($source);

        $country = $this->attributes($this->relation($source, 'country'));
        $state = $this->attributes($this->relation($source, 'countryState'));

        return [
            'externalId' => $this->string($source['id'] ?? $attributes['id'] ?? null),
            'firstName' => $this->string($attributes['firstName'] ?? null),
            'lastName' => $this->string($attributes['lastName'] ?? null),
            'company' => $this->string($attributes['company'] ?? null),
            'department' => $this->string($attributes['department'] ?? null),
            'title' => $this->string($attributes['title'] ?? null),
            'street' => $this->string($attributes['street'] ?? null),
            'zipcode' => $this->string($attributes['zipcode'] ?? null),
            'city' => $this->string($attributes['city'] ?? null),
            'countryCode' => $this->string($country['iso'] ?? $attributes['countryCode'] ?? null),
            'countryState' => $this->string($state['shortCode'] ?? null),
            'phone' => $this->string($attributes['phoneNumber'] ?? null),
            'additionalAddressLine1' => $this->string($attributes['additionalAddressLine1'] ?? null),
            'additionalAddressLine2' => $this->string($attributes['additionalAddressLine2'] ?? null),
            'customFields' => is_array($sourceFields['customFields'] ?? null) ? $sourceFields['customFields'] : [],
            'sourceFields' => $sourceFields,
        ];
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    public function order(array $source): array
    {
        $attributes = $this->attributes($source);
        $sourceFields = $this->sourceFields($source);
        $externalId = $this->string($source['id'] ?? $attributes['id'] ?? null);
        if ($externalId === null) {
            throw new \InvalidArgumentException('Shopware order has no ID.');
        }

        $currency = $this->attributes($this->relation($source, 'currency'));
        $currencyCode = $this->string($currency['isoCode'] ?? $attributes['currencyCode'] ?? null);
        $orderCustomer = $this->relation($source, 'orderCustomer');
        $customerAttributes = $this->attributes($orderCustomer);
        $customerSourceFields = $this->sourceFields($orderCustomer);
        $customerId = $this->string($customerAttributes['customerId'] ?? null);
        $customer = $customerAttributes === [] ? [] : [
            'externalId' => $customerId,
            'email' => $this->string($customerAttributes['email'] ?? null),
            'firstName' => $this->string($customerAttributes['firstName'] ?? null),
            'lastName' => $this->string($customerAttributes['lastName'] ?? null),
            'company' => $this->string($customerAttributes['company'] ?? null),
            'customerNumber' => $this->string($customerAttributes['customerNumber'] ?? null),
            'guest' => $customerId === null,
            'customFields' => is_array($customerSourceFields['customFields'] ?? null) ? $customerSourceFields['customFields'] : [],
            'sourceFields' => $customerSourceFields,
        ];

        $billingAddressId = $this->string($attributes['billingAddressId'] ?? null);
        $billingAddress = [];
        foreach ($this->relations($source, 'addresses') as $address) {
            $addressAttributes = $this->attributes($address);
            if ($billingAddressId === ($address['id'] ?? $addressAttributes['id'] ?? null)) {
                $billingAddress = $this->address($address);
                break;
            }
        }

        $lines = [];
        foreach ($this->relations($source, 'lineItems') as $line) {
            $item = $this->attributes($line);
            $lineType = $this->string($item['type'] ?? null) ?? 'product';
            $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
            $price = is_array($item['price'] ?? null) ? $item['price'] : [];
            $taxes = is_array($price['calculatedTaxes'] ?? null) ? $price['calculatedTaxes'] : [];
            $sku = $this->string($payload['productNumber'] ?? $item['productNumber'] ?? null);

            $lines[] = [
                'externalLineId' => $this->string($line['id'] ?? $item['id'] ?? null),
                'type' => $lineType,
                'sku' => $sku,
                'name' => $this->string($item['label'] ?? null) ?? $sku ?? $lineType,
                'quantity' => $item['quantity'] ?? null,
                'sourcePayload' => [
                    'referencedId' => $this->string($item['referencedId'] ?? null),
                    'payload' => $this->safeFields($payload),
                    'sourceFields' => $this->sourceFields($line),
                ],
                'commercial' => [
                    'currencyCode' => $currencyCode,
                    'unitGross' => $price['unitPrice'] ?? $item['unitPrice'] ?? null,
                    'totalGross' => $price['totalPrice'] ?? $item['totalPrice'] ?? null,
                    'totalTax' => $this->taxTotal($taxes),
                    'taxes' => $taxes,
                ],
            ];
        }

        $payments = [];
        foreach ($this->relations($source, 'transactions') as $transaction) {
            $item = $this->attributes($transaction);
            $method = $this->attributes($this->relation($transaction, 'paymentMethod'));
            $state = $this->attributes($this->relation($transaction, 'stateMachineState'));
            $payments[] = [
                'externalId' => $this->string($transaction['id'] ?? $item['id'] ?? null),
                'methodExternalId' => $this->string($item['paymentMethodId'] ?? $this->relation($transaction, 'paymentMethod')['id'] ?? null),
                'method' => $this->string($method['name'] ?? null),
                'state' => $this->string($state['technicalName'] ?? null),
                'reference' => $this->string($item['customFields']['reference'] ?? null),
                'amount' => $item['amount']['totalPrice'] ?? null,
                'currencyCode' => $currencyCode,
                'sourceFields' => $this->sourceFields($transaction),
            ];
        }

        $deliveries = [];
        $shippingAddress = [];
        foreach ($this->relations($source, 'deliveries') as $delivery) {
            $item = $this->attributes($delivery);
            $method = $this->attributes($this->relation($delivery, 'shippingMethod'));
            $state = $this->attributes($this->relation($delivery, 'stateMachineState'));
            $address = $this->address($this->relation($delivery, 'shippingOrderAddress'));
            if ($shippingAddress === [] && $address !== []) {
                $shippingAddress = $address;
            }

            $trackingCodes = $item['trackingCodes'] ?? [];
            $positions = [];
            foreach ($this->relations($delivery, 'positions') as $position) {
                $positionAttributes = $this->attributes($position);
                $positions[] = [
                    'externalLineId' => $this->string($positionAttributes['orderLineItemId'] ?? null),
                    'quantity' => $positionAttributes['quantity'] ?? null,
                ];
            }
            $deliveries[] = [
                'externalId' => $this->string($delivery['id'] ?? $item['id'] ?? null),
                'methodExternalId' => $this->string($item['shippingMethodId'] ?? $this->relation($delivery, 'shippingMethod')['id'] ?? null),
                'method' => $this->string($method['name'] ?? null),
                'state' => $this->string($state['technicalName'] ?? null),
                'trackingNumber' => is_array($trackingCodes) ? $this->string($trackingCodes[0] ?? null) : null,
                'trackingCodes' => is_array($trackingCodes) && array_is_list($trackingCodes) ? $trackingCodes : [],
                'positions' => $positions,
                'shippingGross' => is_array($item['shippingCosts'] ?? null)
                    ? ($item['shippingCosts']['totalPrice'] ?? null)
                    : null,
                'shippingAddress' => $address,
                'sourceFields' => $this->sourceFields($delivery),
            ];
        }

        $shippingCosts = is_array($attributes['shippingCosts'] ?? null) ? $attributes['shippingCosts'] : [];
        $shippingTaxes = is_array($shippingCosts['calculatedTaxes'] ?? null) ? $shippingCosts['calculatedTaxes'] : [];
        $shippingGross = $shippingCosts['totalPrice'] ?? null;
        $shippingTax = $this->taxTotal($shippingTaxes);

        return [
            'type' => 'placed',
            'externalId' => $externalId,
            'externalNumber' => $this->string($attributes['orderNumber'] ?? null) ?? $externalId,
            'orderedAt' => $this->string($attributes['orderDateTime'] ?? $attributes['createdAt'] ?? null),
            'customer' => $customer,
            'billingAddress' => $billingAddress,
            'shippingAddress' => $shippingAddress,
            'lines' => $lines,
            'payments' => $payments,
            'deliveries' => $deliveries,
            'commercial' => [
                'currencyCode' => $currencyCode,
                'taxStatus' => $this->string($attributes['taxStatus'] ?? null),
                'amountNet' => $attributes['amountNet'] ?? null,
                'amountGross' => $attributes['amountTotal'] ?? null,
                'amountTax' => is_numeric($attributes['amountTotal'] ?? null) && is_numeric($attributes['amountNet'] ?? null)
                    ? (float) $attributes['amountTotal'] - (float) $attributes['amountNet']
                    : null,
                'shippingNet' => is_numeric($shippingGross) ? (float) $shippingGross - $shippingTax : null,
                'shippingGross' => $shippingGross,
                'shippingTax' => $shippingTax,
            ],
            'sourcePayload' => [
                'shopwareOrderId' => $externalId,
                'state' => $this->string($this->attributes($this->relation($source, 'stateMachineState'))['technicalName'] ?? null),
                'primaryOrderTransactionId' => $this->string($attributes['primaryOrderTransactionId'] ?? null),
                'primaryOrderDeliveryId' => $this->string($attributes['primaryOrderDeliveryId'] ?? null),
                'customFields' => is_array($sourceFields['customFields'] ?? null)
                    ? $sourceFields['customFields']
                    : [],
                'sourceFields' => $sourceFields,
            ],
        ];
    }

    /**
     * Preserve source business fields for future connector-to-connector mapping.
     * Associations are imported separately; authentication and guest-access
     * secrets are never retained in the migration snapshot.
     *
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function sourceFields(array $source): array
    {
        $attributes = $this->attributes($source);
        $sourceAttributes = $attributes;
        $relationships = is_array($source['relationships'] ?? null) ? $source['relationships'] : [];
        foreach (array_keys($relationships) as $association) {
            unset($attributes[$association]);
        }

        $fields = $this->safeFields($attributes);
        $references = [];
        $referenceNames = ['addresses', 'tags', 'documents', 'lineItems', 'transactions', 'deliveries'];
        foreach ($relationships as $name => $relationship) {
            if (!is_string($name) || !in_array($name, $referenceNames, true) || !is_array($relationship)) {
                continue;
            }

            $data = $relationship['data'] ?? null;
            if (is_array($data) && array_is_list($data)) {
                $references[$name] = array_values(array_filter(array_map(
                    static fn (mixed $item): ?string => is_array($item) && is_string($item['id'] ?? null)
                        ? $item['id']
                        : null,
                    $data,
                )));
            } elseif (is_array($data) && is_string($data['id'] ?? null)) {
                $references[$name] = $data['id'];
            }
        }
        if ($references !== []) {
            $fields['_relationships'] = $references;
        }

        $associationNames = ['group', 'language', 'salutation', 'lastPaymentMethod', 'tags', 'documents'];
        $associated = [];
        foreach ($associationNames as $name) {
            if (!isset($relationships[$name])) {
                continue;
            }

            $value = $sourceAttributes[$name] ?? null;
            if (is_array($value) && array_is_list($value)) {
                $associated[$name] = array_map(
                    fn (array $item): array => $this->referenceSnapshot($name, $item),
                    array_values(array_filter($value, 'is_array')),
                );
            } elseif (is_array($value)) {
                $associated[$name] = $this->referenceSnapshot($name, $value);
            }
        }
        if ($associated !== []) {
            $fields['_associations'] = $associated;
        }

        return $fields;
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    private function referenceSnapshot(string $name, array $source): array
    {
        $attributes = $this->attributes($source);
        $keys = match ($name) {
            'group' => ['name', 'displayGross'],
            'language' => ['name', 'localeId'],
            'salutation' => ['salutationKey', 'displayName', 'letterName'],
            'lastPaymentMethod' => ['name', 'technicalName'],
            'tags' => ['name'],
            'documents' => ['documentTypeId', 'documentMediaFileId', 'documentA11yMediaFileId', 'referencedDocumentId', 'config', 'sent', 'static', 'createdAt'],
            default => [],
        };
        $snapshot = ['id' => $source['id'] ?? null];
        foreach ($keys as $key) {
            if (array_key_exists($key, $attributes)) {
                $snapshot[$key] = $attributes[$key];
            }
        }
        if ($name === 'language') {
            $locale = $this->attributes($this->relation($source, 'locale'));
            $snapshot['localeCode'] = $locale['code'] ?? null;
        }

        return $this->safeFields($snapshot);
    }

    /** @param array<string, mixed> $fields @return array<string, mixed> */
    private function safeFields(array $fields): array
    {
        return SourcePayloadSanitizer::sanitize($fields);
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    private function attributes(array $source): array
    {
        return is_array($source['attributes'] ?? null) ? $source['attributes'] : $source;
    }

    /** @param array<string, array<string, mixed>> $index @param array<string, mixed> $source @return array<string, mixed> */
    private function hydrate(array $source, array $index, int $depth): array
    {
        if ($depth >= 4 || !is_array($source['relationships'] ?? null)) {
            return $source;
        }

        $attributes = $this->attributes($source);
        foreach ($source['relationships'] as $name => $relationship) {
            if (!is_string($name) || !is_array($relationship)) {
                continue;
            }

            $references = $relationship['data'] ?? null;
            if (is_array($references) && array_is_list($references)) {
                $attributes[$name] = array_map(
                    fn (mixed $reference): array => $this->resolve($reference, $index, $depth + 1),
                    $references,
                );
            } elseif (is_array($references)) {
                $attributes[$name] = $this->resolve($references, $index, $depth + 1);
            }
        }

        $source['attributes'] = $attributes;

        return $source;
    }

    /** @param array<string, array<string, mixed>> $index @return array<string, mixed> */
    private function resolve(mixed $reference, array $index, int $depth): array
    {
        if (!is_array($reference)) {
            return [];
        }

        $type = $reference['type'] ?? null;
        $id = $reference['id'] ?? null;
        $entity = is_string($type) && is_string($id)
            ? ($index[$type.':'.$id] ?? $reference)
            : $reference;

        return $this->hydrate($entity, $index, $depth);
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    private function relation(array $source, string $name): array
    {
        $attributes = $this->attributes($source);
        $value = $attributes[$name] ?? $source['relationships'][$name]['data'] ?? null;
        if (is_array($value) && is_array($value['data'] ?? null)) {
            $value = $value['data'];
        }

        return is_array($value) && !array_is_list($value) ? $value : [];
    }

    /** @param array<string, mixed> $source @return list<array<string, mixed>> */
    private function relations(array $source, string $name): array
    {
        $attributes = $this->attributes($source);
        $value = $attributes[$name] ?? $source['relationships'][$name]['data'] ?? [];
        if (is_array($value) && is_array($value['data'] ?? null)) {
            $value = $value['data'];
        }

        return is_array($value) && array_is_list($value)
            ? array_values(array_filter($value, 'is_array'))
            : [];
    }

    private function string(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param list<array<string, mixed>> $taxes */
    private function taxTotal(array $taxes): float
    {
        $total = 0.0;
        foreach ($taxes as $tax) {
            $total += (float) ($tax['tax'] ?? 0);
        }

        return $total;
    }
}
