<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Enums;

/**
 * Nivel de confianca associado a uma localizacao resolvida.
 *
 * Nunca deve ser interpretado como prova absoluta de presenca fisica;
 * representa apenas o grau de certeza estatistica/tecnica da fonte.
 */
enum ConfidenceLevel: string
{
    case VERY_LOW = 'very_low';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case VERIFIED = 'verified';

    /**
     * Ordem numerica para comparacoes (ex.: "minimo exigido").
     */
    public function weight(): int
    {
        return match ($this) {
            self::VERY_LOW => 0,
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
            self::VERIFIED => 4,
        };
    }

    public function meetsMinimum(self $minimum): bool
    {
        return $this->weight() >= $minimum->weight();
    }

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::VERY_LOW;
    }
}
