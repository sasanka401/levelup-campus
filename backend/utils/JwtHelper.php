<?php
/**
 * Lightweight JWT implementation (HS256) — no external library needed.
 * Usage:
 *   $token = JwtHelper::encode(['id' => 5]);
 *   $payload = JwtHelper::decode($token);  // returns array or null
 */
class JwtHelper {

    // ─── Encode ──────────────────────────────────────────────
    public static function encode(array $payload): string {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

        $payload['iat'] = time();
        $payload['exp'] = time() + JWT_EXPIRY;

        $body      = self::base64UrlEncode(json_encode($payload));
        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$body", JWT_SECRET, true)
        );

        return "$header.$body.$signature";
    }

    // ─── Decode ───────────────────────────────────────────────
    // Returns payload array on success, null on failure
    public static function decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $body, $signature] = $parts;

        // Verify signature
        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$body", JWT_SECRET, true)
        );
        if (!hash_equals($expectedSig, $signature)) return null;

        $payload = json_decode(self::base64UrlDecode($body), true);
        if (!$payload) return null;

        // Check expiry
        if (isset($payload['exp']) && $payload['exp'] < time()) return null;

        return $payload;
    }

    // ─── Helpers ──────────────────────────────────────────────
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
