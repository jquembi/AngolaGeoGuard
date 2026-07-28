<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Tests\Unit;

use JoseQuembi\AngolaGeoGuard\Security\TrustedProxyIpResolver;
use PHPUnit\Framework\TestCase;

final class TrustedProxyIpResolverTest extends TestCase
{
    private TrustedProxyIpResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TrustedProxyIpResolver(['173.245.48.0/20', '10.0.0.0/8']);
    }

    public function test_cidr_matching(): void
    {
        $this->assertTrue($this->resolver->ipMatchesCidr('173.245.50.5', '173.245.48.0/20'));
        $this->assertFalse($this->resolver->ipMatchesCidr('8.8.8.8', '173.245.48.0/20'));
        $this->assertTrue($this->resolver->ipMatchesCidr('10.1.2.3', '10.0.0.0/8'));
    }

    public function test_forged_header_from_untrusted_origin_is_ignored(): void
    {
        $result = $this->resolver->resolve('8.8.8.8', ['X-Forwarded-For' => '1.2.3.4']);

        $this->assertSame('8.8.8.8', $result['ip']);
        $this->assertFalse($result['trusted_chain']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_header_honored_from_trusted_proxy(): void
    {
        $result = $this->resolver->resolve('173.245.50.5', ['X-Forwarded-For' => '196.10.20.30, 173.245.50.5']);

        $this->assertSame('196.10.20.30', $result['ip']);
        $this->assertTrue($result['trusted_chain']);
    }

    public function test_cf_connecting_ip_takes_priority(): void
    {
        $result = $this->resolver->resolve('173.245.50.5', [
            'CF-Connecting-IP' => '196.10.20.30',
            'X-Forwarded-For' => '9.9.9.9',
        ]);

        $this->assertSame('196.10.20.30', $result['ip']);
    }

    public function test_no_headers_returns_remote_addr(): void
    {
        $result = $this->resolver->resolve('173.245.50.5', []);

        $this->assertSame('173.245.50.5', $result['ip']);
    }
}
