<?php

namespace App\Integration;

final class SecretCipher
{
    private string $key;

    public function __construct(string $appSecret)
    {
        $this->key = hash('sha256', $appSecret, true);
    }

    /** @return array{ciphertext: string, nonce: string} */
    public function encrypt(string $value): array
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return [
            'ciphertext' => base64_encode(sodium_crypto_secretbox($value, $nonce, $this->key)),
            'nonce' => base64_encode($nonce),
        ];
    }

    public function decrypt(string $ciphertext, string $nonce): string
    {
        $value = sodium_crypto_secretbox_open(base64_decode($ciphertext, true), base64_decode($nonce, true), $this->key);
        if ($value === false) {
            throw new \RuntimeException('Unable to decrypt integration secret.');
        }

        return $value;
    }
}
