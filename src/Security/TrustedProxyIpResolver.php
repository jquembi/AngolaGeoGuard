<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Security;

/**
 * Resolve o IP real do cliente a partir de uma cadeia de proxies,
 * confiando em cabecalhos (X-Forwarded-For, CF-Connecting-IP,
 * True-Client-IP, Forwarded) apenas quando a origem imediata do
 * pedido pertence a um CIDR explicitamente confiavel. Ver secao 14.
 *
 * NUNCA aceita X-Forwarded-For as cegas: se o IP de origem nao
 * estiver na lista de `trustedProxyCidrs`, o cabecalho e ignorado
 * e o IP de conexao direta e usado.
 */
final class TrustedProxyIpResolver
{
    /**
     * @param  array<string>  $trustedProxyCidrs  Ex.: ['10.0.0.0/8', '173.245.48.0/20']
     */
    public function __construct(
        private readonly array $trustedProxyCidrs,
    ) {
    }

    /**
     * @param  string  $remoteAddr  IP da ligacao TCP direta (nunca falsificavel pela aplicacao)
     * @param  array<string, string>  $headers  Cabecalhos HTTP recebidos, case-insensitive
     * @return array{ip: string, trusted_chain: bool, warnings: array<string>}
     */
    public function resolve(string $remoteAddr, array $headers): array
    {
        $warnings = [];
        $normalizedHeaders = $this->normalizeHeaders($headers);

        if (! $this->isTrustedProxy($remoteAddr)) {
            if ($this->headersPresent($normalizedHeaders)) {
                $warnings[] = sprintf(
                    'Cabecalhos de proxy presentes mas a origem %s nao e um proxy confiavel; cabecalhos ignorados.',
                    $remoteAddr,
                );
            }

            return ['ip' => $remoteAddr, 'trusted_chain' => false, 'warnings' => $warnings];
        }

        // Prioridade: CF-Connecting-IP / True-Client-IP (definidos por um
        // unico ponto confiavel, ex.: Cloudflare) sobre X-Forwarded-For
        // (que pode ter multiplos saltos).
        foreach (['cf-connecting-ip', 'true-client-ip'] as $header) {
            if (isset($normalizedHeaders[$header]) && $this->isValidIp($normalizedHeaders[$header])) {
                return ['ip' => $normalizedHeaders[$header], 'trusted_chain' => true, 'warnings' => $warnings];
            }
        }

        if (isset($normalizedHeaders['x-forwarded-for'])) {
            $chain = array_map('trim', explode(',', $normalizedHeaders['x-forwarded-for']));
            $clientIp = $chain[0] ?? null;

            if ($clientIp !== null && $this->isValidIp($clientIp)) {
                return ['ip' => $clientIp, 'trusted_chain' => true, 'warnings' => $warnings];
            }

            $warnings[] = 'X-Forwarded-For presente mas o primeiro IP da cadeia e invalido.';
        }

        if (isset($normalizedHeaders['x-real-ip']) && $this->isValidIp($normalizedHeaders['x-real-ip'])) {
            return ['ip' => $normalizedHeaders['x-real-ip'], 'trusted_chain' => true, 'warnings' => $warnings];
        }

        return ['ip' => $remoteAddr, 'trusted_chain' => true, 'warnings' => $warnings];
    }

    public function isTrustedProxy(string $ip): bool
    {
        foreach ($this->trustedProxyCidrs as $cidr) {
            if ($this->ipMatchesCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function headersPresent(array $normalizedHeaders): bool
    {
        foreach (['x-forwarded-for', 'x-real-ip', 'cf-connecting-ip', 'true-client-ip', 'forwarded'] as $header) {
            if (isset($normalizedHeaders[$header])) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $value) {
            $normalized[strtolower($key)] = is_array($value) ? (string) reset($value) : (string) $value;
        }

        return $normalized;
    }

    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $maskBits] = explode('/', $cidr);
        $maskBits = (int) $maskBits;

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $bytesToCheck = intdiv($maskBits, 8);
        $remainingBits = $maskBits % 8;

        if ($bytesToCheck > 0 && substr($ipBinary, 0, $bytesToCheck) !== substr($subnetBinary, 0, $bytesToCheck)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = ~(0xFF >> $remainingBits) & 0xFF;

        return (ord($ipBinary[$bytesToCheck]) & $mask) === (ord($subnetBinary[$bytesToCheck]) & $mask);
    }
}
