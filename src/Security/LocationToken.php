<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Security;

use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * Token de localizacao assinado por HMAC, com expiracao e nonce
 * para protecao contra replay. Ver secao 13.
 *
 * Uso tipico: o cliente (app movel/navegador) obtem coordenadas GPS,
 * a aplicacao emite um LocationToken assinado com a chave do servidor,
 * e o pacote verifica a assinatura/expiracao/nonce antes de confiar
 * na localizacao declarada pelo dispositivo.
 */
final class LocationToken
{
    private function __construct(
        public readonly string $userId,
        public readonly ?string $deviceId,
        public readonly Coordinates $coordinates,
        public readonly ?float $accuracyMeters,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
        public readonly string $nonce,
        public readonly string $keyVersion,
    ) {
    }

    /**
     * Emite e assina um novo token.
     */
    public static function issue(
        string $userId,
        Coordinates $coordinates,
        string $signingKey,
        string $keyVersion = 'v1',
        int $ttlSeconds = 300,
        ?string $deviceId = null,
        ?float $accuracyMeters = null,
        ?string $nonce = null,
    ): string {
        $now = time();

        $payload = [
            'user_id' => $userId,
            'device_id' => $deviceId,
            'latitude' => $coordinates->latitude,
            'longitude' => $coordinates->longitude,
            'accuracy' => $accuracyMeters,
            'issued_at' => $now,
            'expires_at' => $now + $ttlSeconds,
            'nonce' => $nonce ?? bin2hex(random_bytes(16)),
            'key_version' => $keyVersion,
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = self::sign($json, $signingKey);

        return base64_encode($json).'.'.$signature;
    }

    /**
     * Verifica assinatura, expiracao e (opcionalmente) reutilizacao de
     * nonce, e devolve o token decodificado ou null se invalido.
     *
     * @param  callable(string): bool|null  $nonceSeenBefore  Deve devolver true se o nonce ja foi usado (proteccao contra replay); tipicamente backed por cache/Redis com TTL = ttlSeconds do token.
     */
    public static function verify(string $token, string $signingKey, ?callable $nonceSeenBefore = null): ?self
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $signature] = $parts;
        $json = base64_decode($encodedPayload, true);

        if ($json === false) {
            return null;
        }

        $expectedSignature = self::sign($json, $signingKey);

        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        try {
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! isset($payload['expires_at']) || time() > (int) $payload['expires_at']) {
            return null;
        }

        if ($nonceSeenBefore !== null && $nonceSeenBefore((string) $payload['nonce'])) {
            return null;
        }

        return new self(
            userId: (string) $payload['user_id'],
            deviceId: $payload['device_id'] ?? null,
            coordinates: new Coordinates((float) $payload['latitude'], (float) $payload['longitude']),
            accuracyMeters: isset($payload['accuracy']) ? (float) $payload['accuracy'] : null,
            issuedAt: (int) $payload['issued_at'],
            expiresAt: (int) $payload['expires_at'],
            nonce: (string) $payload['nonce'],
            keyVersion: (string) ($payload['key_version'] ?? 'v1'),
        );
    }

    /**
     * Comparacao segura contra timing attacks e feita via hash_equals
     * em verify(); esta funcao apenas calcula a assinatura.
     */
    private static function sign(string $payload, string $key): string
    {
        return hash_hmac('sha256', $payload, $key);
    }
}
