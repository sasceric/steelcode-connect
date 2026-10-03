<?php

namespace App\Tests\Unit\Integration;

use App\Integration\WooCommerceSalesMapper;
use PHPUnit\Framework\TestCase;

final class WooCommerceSalesMapperTest extends TestCase
{
    public function testOrderPreservesTotalsAddressesPaymentAndVariantIdentity(): void
    {
        $event = (new WooCommerceSalesMapper())->order(
            [
                'id' => 10,
                'number' => 'WC-10',
                'currency' => 'EUR',
                'status' => 'processing',
                'customer_id' => 4,
                'total' => '29.00',
                'total_tax' => '4.00',
                'shipping_total' => '5.00',
                'shipping_tax' => '0.00',
                'billing' => ['email' => 'customer@example.test', 'address_1' => 'Main street', 'country' => 'DE'],
                'shipping' => ['address_1' => 'Delivery street'],
                'date_created_gmt' => '2026-09-01T12:00:00',
                'date_paid' => '2026-09-01T12:01:00',
                'payment_method' => 'bacs',
                'payment_method_title' => 'Bank transfer',
                'transaction_id' => 'BANK-1',
                'line_items' => [
                    [
                        'id' => 100,
                        'product_id' => 20,
                        'variation_id' => 21,
                        'sku' => 'OLD',
                        'resolvedSku' => 'VARIANT-21',
                        'name' => 'Variant',
                        'quantity' => 2,
                        'total' => '20.00',
                        'total_tax' => '4.00',
                        'subtotal' => '20.00',
                        'subtotal_tax' => '4.00',
                    ],
                ],
                'shipping_lines' => [
                    [
                        'id' => 101,
                        'method_id' => 'flat_rate',
                        'method_title' => 'Standard',
                        'total' => '5.00',
                    ],
                ],
                'order_key' => 'private-order-key',
                'customer_ip_address' => '127.0.0.1',
                'meta_data' => [
                    ['key' => 'warehouse_note', 'value' => 'Keep'],
                    ['key' => '_payment_token', 'value' => 'private'],
                ],
            ],
        );
        self::assertSame('4', $event['customer']['externalId']);
        self::assertSame('VARIANT-21', $event['lines'][0]['sku']);
        self::assertSame(21, $event['lines'][0]['sourcePayload']['sourceFields']['variation_id']);
        self::assertSame(29.0, $event['commercial']['amountGross']);
        self::assertSame(25.0, $event['commercial']['amountNet']);
        self::assertSame(12.0, $event['lines'][0]['commercial']['unitGross']);
        self::assertSame('Main street', $event['billingAddress']['street']);
        self::assertSame('paid', $event['payments'][0]['state']);
        self::assertSame('BANK-1', $event['payments'][0]['reference']);
        self::assertSame('Standard', $event['deliveries'][0]['method']);
        self::assertSame([], $event['deliveries'][0]['positions']);
        self::assertNull($event['deliveries'][0]['state']);
        self::assertArrayNotHasKey('order_key', $event['sourcePayload']['sourceFields']);
        self::assertArrayNotHasKey('customer_ip_address', $event['sourcePayload']['sourceFields']);
        self::assertCount(1, $event['sourcePayload']['sourceFields']['meta_data']);
        self::assertSame('Keep', $event['sourcePayload']['customFields']['warehouse_note']);
    }

    public function testCustomerPreservesSafeMetadataAndAddressBooks(): void
    {
        $mapper = new WooCommerceSalesMapper();
        $profile = $mapper->customer(
            [
                'id' => 4,
                'email' => 'business@example.test',
                'billing' => ['company' => 'Business', 'address_1' => 'Main', 'phone' => '123'],
                'meta_data' => [['key' => 'vat_id', 'value' => 'DE123'], ['key' => 'password_hash', 'value' => 'private']],
                'password' => 'private',
            ],
        );
        self::assertSame('business', $profile['accountType']);
        self::assertSame('Business', $profile['company']);
        self::assertSame('billing', $profile['defaultBillingAddressId']);
        self::assertNull($profile['defaultShippingAddressId']);
        self::assertArrayNotHasKey('password', $profile['sourceFields']);
        self::assertCount(1, $profile['customFields']);
        self::assertSame('second', $mapper->address(['address_2' => 'second'])['additionalAddressLine1']);
    }

    public function testGuestIsNotAssignedTheSharedZeroCustomerIdentifier(): void
    {
        $event = (new WooCommerceSalesMapper())->order(['id' => 3, 'currency' => 'EUR', 'customer_id' => 0]);
        self::assertTrue($event['customer']['guest']);
        self::assertNull($event['customer']['externalId']);
    }

    public function testPaidDateWithoutAGatewayNameKeepsKnownStateWithoutInventingAMethod(): void
    {
        $event = (new WooCommerceSalesMapper())->order([
            'id' => 4,
            'currency' => 'EUR',
            'total' => '10.00',
            'date_paid' => '2026-09-01T12:00:00',
        ]);

        self::assertCount(1, $event['payments']);
        self::assertSame('paid', $event['payments'][0]['state']);
        self::assertNull($event['payments'][0]['method']);
        self::assertSame(10.0, $event['payments'][0]['amount']);
    }

    public function testEmptySourceShippingDoesNotCreateAnEmptyAddressBookRow(): void
    {
        $mapper = new WooCommerceSalesMapper();
        $shipping = ['first_name' => '', 'last_name' => '', 'address_1' => '', 'city' => '', 'country' => ''];
        self::assertSame([], $mapper->address($shipping, 'shipping'));
        $profile = $mapper->customer(['id' => 5, 'shipping' => $shipping]);
        self::assertNull($profile['defaultBillingAddressId']);
        self::assertNull($profile['defaultShippingAddressId']);
        self::assertSame($shipping, $profile['sourceFields']['shipping']);
    }
}
