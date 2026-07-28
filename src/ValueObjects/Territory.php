<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\ValueObjects;

/**
 * Identificador imutavel de um territorio (pais, provincia, municipio...).
 *
 * Usa o `internalCode` do pacote (ex.: AO-HUI) como identidade estavel,
 * independente de o codigo oficial/ISO ainda nao existir para a entidade.
 */
final class Territory
{
    public function __construct(
        public readonly string $internalCode,
        public readonly string $slug,
        public readonly string $officialName,
        public readonly string $level, // country|province|municipality|commune|custom
        public readonly ?string $officialCode = null,
        public readonly ?string $parentCode = null,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->internalCode === $other->internalCode;
    }

    public function toArray(): array
    {
        return [
            'internal_code' => $this->internalCode,
            'slug' => $this->slug,
            'official_name' => $this->officialName,
            'official_code' => $this->officialCode,
            'level' => $this->level,
            'parent_code' => $this->parentCode,
        ];
    }
}
