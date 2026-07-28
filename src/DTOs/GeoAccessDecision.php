<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\DTOs;

use DateTimeImmutable;
use JoseQuembi\AngolaGeoGuard\Enums\ConfidenceLevel;
use JoseQuembi\AngolaGeoGuard\Enums\DecisionReasonCode;
use JoseQuembi\AngolaGeoGuard\Enums\RiskLevel;

/**
 * Resultado final e imutavel da avaliacao de uma politica geografica.
 * Ver secao 10 do prompt mestre.
 */
final class GeoAccessDecision
{
    /**
     * @param  array<string>  $evidence
     * @param  array<string>  $warnings
     * @param  array<string>  $appliedExceptions
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly DecisionReasonCode $reasonCode,
        public readonly string $reason,
        public readonly ?LocationResult $location,
        public readonly ?string $policyIdentifier,
        public readonly ConfidenceLevel $confidence,
        public readonly RiskLevel $risk,
        public readonly array $evidence,
        public readonly array $warnings,
        public readonly array $appliedExceptions,
        public readonly DateTimeImmutable $decidedAt,
        public readonly ?float $processingTimeMs = null,
    ) {
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }

    /**
     * Mensagem segura para exposicao publica, sem detalhes internos
     * que possam ajudar a contornar a regra (ver secao 27).
     */
    public function publicMessage(): string
    {
        return $this->allowed
            ? 'Acesso permitido.'
            : 'O acesso a este recurso nao esta disponivel na sua localizacao.';
    }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason_code' => $this->reasonCode->value,
            'reason' => $this->reason,
            'location' => $this->location?->toArray(),
            'policy_identifier' => $this->policyIdentifier,
            'confidence' => $this->confidence->value,
            'risk' => $this->risk->value,
            'evidence' => $this->evidence,
            'warnings' => $this->warnings,
            'applied_exceptions' => $this->appliedExceptions,
            'decided_at' => $this->decidedAt->format(DateTimeImmutable::ATOM),
            'processing_time_ms' => $this->processingTimeMs,
        ];
    }
}
