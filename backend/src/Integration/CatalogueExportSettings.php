<?php

namespace App\Integration;

use Symfony\Component\Uid\Uuid;

final class CatalogueExportSettings
{
    public const REFERENCE_TYPES = [
        'category', 'manufacturer', 'property', 'tax', 'currency',
        'locale', 'unit', 'deliveryTime', 'customField', 'product', 'brand', 'propertyGroup',
    ];

    public static function defaults(): array
    {
        return [
            'scope' => 'selected',
            'automaticSync' => false,
            'createMissingReferences' => false,
            'categoryRootId' => '',
            'categoryIds' => [],
            'brandIds' => [],
            'manufacturerIds' => [],
            'productIds' => [],
            'excludeIds' => [],
            'includeDescendants' => true,
            'includeVariants' => true,
            'salesChannelId' => '',
            'publicationMode' => 'keep',
            'fields' => ['content', 'prices', 'classification', 'fulfilment'],
            'priceMarkup' => '0',
            'mappings' => [],
        ];
    }

    public static function normalize(array $input, string $provider = 'shopware'): array
    {
        $settings = self::defaults();
        if ($provider === 'woocommerce') {
            $locale = $input['destinationLocaleId'] ?? '';
            if (!is_string($locale) || ($locale !== '' && !Uuid::isValid($locale))) {
                throw new \InvalidArgumentException('Invalid destination catalogue language.');
            }
            $settings['destinationLocaleId'] = $locale;
        }
        $scope = $input['scope'] ?? 'selected';
        if (!in_array($scope, ['selected', 'all'], true)) {
            throw new \InvalidArgumentException('Invalid catalogue scope.');
        }
        $settings['scope'] = $scope;
        foreach (['automaticSync', 'createMissingReferences'] as $key) {
            $settings[$key] = ($input[$key] ?? false) === true;
        }
        $root = $input['categoryRootId'] ?? '';
        if (!is_string($root) || ($root !== '' && !self::validTarget('category', $root, $provider))) {
            throw new \InvalidArgumentException('Invalid destination category root.');
        }
        $settings['categoryRootId'] = strtolower($root);
        foreach (['categoryIds', 'brandIds', 'manufacturerIds', 'productIds', 'excludeIds'] as $key) {
            $ids = $input[$key] ?? [];
            if (!is_array($ids) || count($ids) > 1000) {
                throw new \InvalidArgumentException('Invalid export selection.');
            }
            foreach ($ids as $id) {
                if (!is_string($id) || !Uuid::isValid($id)) {
                    throw new \InvalidArgumentException('Invalid export selection ID.');
                }
            }
            $settings[$key] = array_values(array_unique($ids));
            sort($settings[$key]);
        }
        foreach (['includeDescendants', 'includeVariants'] as $key) {
            $settings[$key] = ($input[$key] ?? true) === true;
        }
        $channel = $input['salesChannelId'] ?? '';
        if (!is_string($channel) || ($channel !== '' && !preg_match('/^[a-f0-9]{32}$/i', $channel))) {
            throw new \InvalidArgumentException('Invalid destination sales channel.');
        }
        $settings['salesChannelId'] = strtolower($channel);
        $mode = $input['publicationMode'] ?? 'keep';
        if (!in_array($mode, ['keep', 'activate', 'deactivate'], true)) {
            throw new \InvalidArgumentException('Invalid publication mode.');
        }
        $settings['publicationMode'] = $mode;
        $fields = $input['fields'] ?? $settings['fields'];
        if (!is_array($fields) || array_diff($fields, ['content', 'prices', 'classification', 'fulfilment', 'customFields', 'media']) !== []) {
            throw new \InvalidArgumentException('Invalid export field groups.');
        }
        $settings['fields'] = array_values(array_unique($fields));
        sort($settings['fields']);
        $markup = (string) ($input['priceMarkup'] ?? '0');
        if (!preg_match('/^-?\d{1,4}(\.\d{1,4})?$/', $markup) || (float) $markup < -100 || (float) $markup > 1000) {
            throw new \InvalidArgumentException('Price markup must be between -100 and 1000 percent.');
        }
        $settings['priceMarkup'] = $markup;
        $mappings = $input['mappings'] ?? [];
        if (!is_array($mappings)) {
            throw new \InvalidArgumentException('Invalid export mappings.');
        }
        foreach ($mappings as $type => $values) {
            if (!in_array($type, self::REFERENCE_TYPES, true) || !is_array($values) || count($values) > 10000) {
                throw new \InvalidArgumentException('Invalid export mapping type.');
            }
            foreach ($values as $localId => $externalId) {
                if (!Uuid::isValid((string) $localId) || !is_string($externalId) || !self::validTarget($type, $externalId, $provider)) {
                    throw new \InvalidArgumentException('Invalid export reference mapping.');
                }
                $values[$localId] = $provider === 'shopware' ? strtolower($externalId) : $externalId;
            }
            ksort($values);
            $settings['mappings'][$type] = $values;
        }
        ksort($settings['mappings']);

        return $settings;
    }

    public static function hash(array $settings, string $provider = 'shopware'): string
    {
        return hash('sha256', json_encode(self::normalize($settings, $provider), JSON_THROW_ON_ERROR));
    }

    public static function validTarget(string $type, string $id, string $provider): bool
    {
        if ($provider === 'shopware') {
            return (bool) preg_match('/^[a-f0-9]{32}$/i', $id);
        }
        if ($provider !== 'woocommerce') {
            return false;
        }

        return match ($type) {
            'tax' => (bool) preg_match('/^[a-z0-9-]{1,100}$/', $id),
            'currency' => (bool) preg_match('/^[A-Z]{3}$/', $id),
            'locale' => (bool) preg_match('/^[a-zA-Z]{2,3}[_-][a-zA-Z]{2,4}$/', $id),
            'customField' => (bool) preg_match('/^[a-zA-Z0-9_.-]{1,200}$/', $id),
            'property' => (bool) preg_match('/^[1-9][0-9]*:term:[1-9][0-9]*$/', $id),
            default => (bool) preg_match('/^[1-9][0-9]{0,18}$/', $id),
        };
    }
}
