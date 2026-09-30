<?php

namespace App\Tests\Unit\Integration;

use App\Integration\ShopwareSalesMapper;
use PHPUnit\Framework\TestCase;

final class ShopwareSalesMapperTest extends TestCase
{
    public function testItMapsAllIncludedCustomerAddresses(): void
    {
        $mapper = new ShopwareSalesMapper();
        $customer = [
            'type' => 'customer',
            'id' => 'customer-1',
            'attributes' => [
                'email' => 'buyer@example.test',
                'customerNumber' => 'C-100',
                'accountType' => 'business',
                'vatIds' => ['DE123'],
                'defaultBillingAddressId' => 'address-2',
            ],
            'relationships' => [
                'addresses' => ['data' => [
                    ['type' => 'customer_address', 'id' => 'address-1'],
                    ['type' => 'customer_address', 'id' => 'address-2'],
                ]],
            ],
        ];
        $included = [
            ['type' => 'customer_address', 'id' => 'address-1', 'attributes' => ['street' => 'First Street']],
            ['type' => 'customer_address', 'id' => 'address-2', 'attributes' => [
                'street' => 'Second Street',
                'department' => 'Purchasing',
                'additionalAddressLine1' => 'Building B',
            ]],
        ];

        $hydrated = $mapper->withIncluded($customer, $included);
        $profile = $mapper->customer($hydrated);
        $addresses = $mapper->customerAddresses($hydrated);

        self::assertCount(2, $addresses);
        self::assertSame('Second Street', $addresses[1]['street']);
        self::assertSame('Purchasing', $addresses[1]['department']);
        self::assertSame('Building B', $addresses[1]['additionalAddressLine1']);
        self::assertSame('C-100', $profile['customerNumber']);
        self::assertSame(['DE123'], $profile['vatIds']);
        self::assertSame('address-2', $profile['defaultBillingAddressId']);
    }

    public function testItPreservesBusinessFieldsAndCustomFieldsButNotSecrets(): void
    {
        $mapper = new ShopwareSalesMapper();
        $source = [
            'id' => 'customer-2',
            'attributes' => [
                'email' => 'buyer@example.test',
                'affiliateCode' => 'affiliate-1',
                'campaignCode' => 'campaign-1',
                'birthday' => '1990-01-01',
                'languageId' => 'language-1',
                'customFields' => ['loyalty_tier' => 'gold', 'apiToken' => 'secret-value'],
                'password' => 'password-hash',
                'hash' => 'guest-code',
                'remoteAddress' => '127.0.0.1',
            ],
            'relationships' => [
                'tags' => ['data' => [['type' => 'tag', 'id' => 'tag-1']]],
            ],
        ];

        $mapped = $mapper->customer($mapper->withIncluded($source, [[
            'type' => 'tag',
            'id' => 'tag-1',
            'attributes' => ['name' => 'Wholesale', 'customers' => ['unwanted-large-association']],
        ]]));

        self::assertSame('affiliate-1', $mapped['affiliateCode']);
        self::assertSame('campaign-1', $mapped['campaignCode']);
        self::assertSame(['loyalty_tier' => 'gold'], $mapped['customFields']);
        self::assertSame('1990-01-01', $mapped['sourceFields']['birthday']);
        self::assertSame(['tag-1'], $mapped['sourceFields']['_relationships']['tags']);
        self::assertSame(
            [['id' => 'tag-1', 'name' => 'Wholesale']],
            $mapped['sourceFields']['_associations']['tags'],
        );
        self::assertArrayNotHasKey('password', $mapped['sourceFields']);
        self::assertArrayNotHasKey('hash', $mapped['sourceFields']);
        self::assertArrayNotHasKey('remoteAddress', $mapped['sourceFields']);
    }

    public function testItResolvesIncludedOrderAssociationsAndMapsSnapshots(): void
    {
        $mapper = new ShopwareSalesMapper();
        $order = [
            'type' => 'order',
            'id' => 'order-1',
            'attributes' => [
                'orderNumber' => '10001',
                'amountTotal' => 119.0,
                'amountNet' => 100.0,
                'shippingCosts' => [
                    'totalPrice' => 4.5,
                    'calculatedTaxes' => [['tax' => 0.5]],
                ],
                'primaryOrderTransactionId' => 'payment-1',
                'primaryOrderDeliveryId' => 'delivery-1',
                'billingAddressId' => 'address-1',
                'orderDateTime' => '2026-09-01T12:00:00+00:00',
                'affiliateCode' => 'partner-1',
                'customFields' => ['sales_region' => 'EU'],
                'deepLinkCode' => 'private-guest-link',
            ],
            'relationships' => [
                'lineItems' => ['data' => [
                    ['type' => 'order_line_item', 'id' => 'line-1'],
                    ['type' => 'order_line_item', 'id' => 'discount-1'],
                ]],
                'currency' => ['data' => ['type' => 'currency', 'id' => 'currency-1']],
                'orderCustomer' => ['data' => ['type' => 'order_customer', 'id' => 'buyer-1']],
                'addresses' => ['data' => [['type' => 'order_address', 'id' => 'address-1']]],
                'transactions' => ['data' => [['type' => 'order_transaction', 'id' => 'payment-1']]],
                'deliveries' => ['data' => [['type' => 'order_delivery', 'id' => 'delivery-1']]],
            ],
        ];
        $included = [
            ['type' => 'order_line_item', 'id' => 'line-1', 'attributes' => [
                'type' => 'product',
                'label' => 'Variant name',
                'quantity' => 2,
                'payload' => ['productNumber' => 'SKU-VARIANT'],
                'price' => ['unitPrice' => 59.5, 'totalPrice' => 119.0, 'calculatedTaxes' => [['tax' => 19.0]]],
            ]],
            ['type' => 'order_line_item', 'id' => 'discount-1', 'attributes' => [
                'type' => 'promotion',
                'label' => 'Discount',
                'quantity' => 1,
                'price' => ['unitPrice' => -5, 'totalPrice' => -5],
            ]],
            ['type' => 'currency', 'id' => 'currency-1', 'attributes' => ['isoCode' => 'EUR']],
            ['type' => 'order_customer', 'id' => 'buyer-1', 'attributes' => [
                'customerId' => 'customer-1',
                'email' => 'buyer@example.test',
            ]],
            ['type' => 'order_address', 'id' => 'address-1', 'attributes' => [
                'firstName' => 'Buyer',
                'street' => 'Main Street 1',
            ]],
            ['type' => 'order_transaction', 'id' => 'payment-1', 'attributes' => [
                'amount' => ['totalPrice' => 119.0],
                'paymentMethodId' => 'method-1',
            ]],
            ['type' => 'order_delivery', 'id' => 'delivery-1', 'attributes' => [
                'trackingCodes' => ['TRACK-1', 'TRACK-2'],
                'shippingMethodId' => 'shipping-1',
                'shippingCosts' => ['totalPrice' => 4.5],
            ]],
        ];

        $event = $mapper->order($mapper->withIncluded($order, $included));

        self::assertSame('order-1', $event['externalId']);
        self::assertSame('SKU-VARIANT', $event['lines'][0]['sku']);
        self::assertSame('Variant name', $event['lines'][0]['name']);
        self::assertCount(2, $event['lines']);
        self::assertSame('promotion', $event['lines'][1]['type']);
        self::assertNull($event['lines'][1]['sku']);
        self::assertSame('EUR', $event['commercial']['currencyCode']);
        self::assertSame(4.0, $event['commercial']['shippingNet']);
        self::assertSame(0.5, $event['commercial']['shippingTax']);
        self::assertSame(4.5, $event['commercial']['shippingGross']);
        self::assertSame('payment-1', $event['sourcePayload']['primaryOrderTransactionId']);
        self::assertSame('delivery-1', $event['sourcePayload']['primaryOrderDeliveryId']);
        self::assertSame('partner-1', $event['sourcePayload']['sourceFields']['affiliateCode']);
        self::assertSame(['sales_region' => 'EU'], $event['sourcePayload']['customFields']);
        self::assertArrayNotHasKey('deepLinkCode', $event['sourcePayload']['sourceFields']);
        self::assertSame('customer-1', $event['customer']['externalId']);
        self::assertSame('Main Street 1', $event['billingAddress']['street']);
        self::assertSame(119.0, $event['payments'][0]['amount']);
        self::assertSame('method-1', $event['payments'][0]['methodExternalId']);
        self::assertSame('TRACK-1', $event['deliveries'][0]['trackingNumber']);
        self::assertSame(['TRACK-1', 'TRACK-2'], $event['deliveries'][0]['trackingCodes']);
        self::assertSame('shipping-1', $event['deliveries'][0]['methodExternalId']);
        self::assertSame(4.5, $event['deliveries'][0]['shippingGross']);
    }
}
