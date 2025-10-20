<?php

declare(strict_types=1);

namespace Infrastructure\Security;

/**
 * Nonce Generator for CSP (Content Security Policy)
 *
 * Generates cryptographically secure random nonces for each HTTP request.
 * Used to whitelist specific inline <script> and <style> tags.
 */
class NonceGenerator
{
    /**
     * Generate a cryptographically secure nonce
     *
     * @param int $length Length in bytes (default 16 = 128 bits)
     * @return string Base64-encoded nonce
     */
    public static function generate(int $length = 16): string
    {
        $randomBytes = random_bytes($length);
        return base64_encode($randomBytes);
    }

    /**
     * Validate nonce format (for debugging)
     *
     * @param string $nonce
     * @return bool
     */
    public static function isValid(string $nonce): bool
    {
        return !empty($nonce)
            && strlen($nonce) >= 16
            && base64_decode($nonce, true) !== false;
    }
}
