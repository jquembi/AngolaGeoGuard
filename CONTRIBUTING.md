# Contribuir para o angola-geoguard

## Ambiente de desenvolvimento

```bash
git clone <repo>
cd angola-geoguard
composer install
```

## Antes de submeter alteracoes

```bash
composer validate --strict
vendor/bin/pint --test      # estilo de codigo
vendor/bin/phpstan analyse  # analise estatica (nivel 8)
vendor/bin/phpunit          # suite de testes completa
```

Todos os quatro comandos devem passar. Podes correr tudo de uma vez:

```bash
composer quality
```

## Convencoes

- PHP 8.3+, `declare(strict_types=1)` em todos os ficheiros.
- Classes de dominio (`DTOs/`, `ValueObjects/`) sao imutaveis
  (`readonly`), sem excecoes.
- O nucleo (`Core/`, `Spatial/`, `Security/`, `DTOs/`, `Enums/`,
  `ValueObjects/`, `Contracts/`) **nao pode depender de Illuminate/Laravel**.
  Apenas `Models/`, `Http/`, `Console/`, `Services/GeoRequestEvaluator`,
  `Services/GeoAccessRequestBuilder` e o Service Provider podem.
- Nunca adicionar dados geograficos (coordenadas, geometrias,
  fronteiras) sem uma fonte oficial verificavel documentada em
  `GeoDataSource`.
- Nunca introduzir bypass por parametro de query publico
  (`?bypass=true` e similares) — ver `SECURITY.md`.
- Testes novos para qualquer alteracao ao `GeoAccessPolicyEngine`,
  `TrustedProxyIpResolver` ou `LocationToken` sao obrigatorios, dado o
  seu papel de seguranca critica.

## Reportar bugs de seguranca

Ver [SECURITY.md](SECURITY.md) — nao abras uma issue publica para
vulnerabilidades.
