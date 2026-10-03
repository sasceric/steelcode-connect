<?php

namespace App\Integration;

final class SourcePayloadSanitizer
{
    public static function sanitize(array $fields): array
    {
        $safe = [];
        foreach ($fields as $key => $value) {
            $fieldName = is_array($value) && is_string($value['key'] ?? null) ? $value['key'] : $key;
            if (is_string($fieldName) && preg_match(
                '/password|token|secret|hash|credential|api.?key|access.?key|auth.?code|authorization|session.?id|deep.?link|remote.?address|ip.?address|user.?agent|order.?key|payment.?url|^_links$/i',
                $fieldName,
            )) {
                continue;
            }
            $safe[$key] = is_array($value) ? self::sanitize($value) : $value;
        }
        return array_is_list($fields) ? array_values($safe) : $safe;
    }
}
