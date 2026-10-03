<?php

namespace App\Integration;

final class CataloguePayloadHash
{
    public static function of(array $value): string
    {
        $sort = static function (mixed $value) use (&$sort): mixed {
            if (!is_array($value)) {
                return $value;
            }
            $value = array_map($sort, $value);
            if (!array_is_list($value)) {
                ksort($value);
            } else {
                usort($value, static fn (mixed $left, mixed $right): int => strcmp(json_encode($left), json_encode($right)));
            }

            return $value;
        };

        return hash('sha256', json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
