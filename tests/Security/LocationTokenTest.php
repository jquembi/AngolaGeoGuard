<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Tests\Security;

use JoseQuembi\AngolaGeoGuard\Security\LocationToken;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

final class LocationTokenTest extends TestCase
{
    private const KEY = 'test-secret-key';

    private function coordinates(): Coordinates
    {
        return new Coordinates(-14.9172, 13.4925);
    }

    public function test_valid_token_verifies(): void
    {
        $token = LocationToken::issue('user-1', $this->coordinates(), self::KEY);
        $verified = LocationToken::verify($token, self::KEY);

        $this->assertNotNull($verified);
        $this->assertSame('user-1', $verified->userId);
    }

    public function test_wrong_key_is_rejected(): void
    {
        $token = LocationToken::issue('user-1', $this->coordinates(), self::KEY);

        $this->assertNull(LocationToken::verify($token, 'wrong-key'));
    }

    public function test_tampered_token_is_rejected(): void
    {
        $token = LocationToken::issue('user-1', $this->coordinates(), self::KEY);
        $tampered = substr($token, 0, -5).'AAAAA';

        $this->assertNull(LocationToken::verify($tampered, self::KEY));
    }

    public function test_expired_token_is_rejected(): void
    {
        $expired = LocationToken::issue('user-1', $this->coordinates(), self::KEY, ttlSeconds: -10);

        $this->assertNull(LocationToken::verify($expired, self::KEY));
    }

    public function test_replay_protection_blocks_second_use(): void
    {
        $seenNonces = [];
        $nonceSeenBefore = function (string $nonce) use (&$seenNonces): bool {
            if (isset($seenNonces[$nonce])) {
                return true;
            }
            $seenNonces[$nonce] = true;

            return false;
        };

        $token = LocationToken::issue('user-2', $this->coordinates(), self::KEY);

        $this->assertNotNull(LocationToken::verify($token, self::KEY, $nonceSeenBefore));
        $this->assertNull(LocationToken::verify($token, self::KEY, $nonceSeenBefore));
    }
}
