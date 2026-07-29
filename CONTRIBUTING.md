# Como contribuir

Obrigado pelo interesse em melhorar o Angola GeoGuard.

Este pacote toma decisões de acesso com impacto de segurança. Por isso, as
contribuições devem privilegiar clareza, testes e comportamento previsível.

## Ambiente de desenvolvimento

```bash
git clone <repo>
cd angola-geoguard
composer install
```

## Antes de submeter alterações

Execute:

```bash
composer validate --strict
composer quality
```

`composer quality` executa Laravel Pint, PHPStan e PHPUnit.

## Convenções técnicas

- PHP 8.3 ou superior.
- `declare(strict_types=1)` em todos os ficheiros PHP.
- DTOs e Value Objects devem ser imutáveis sempre que possível.
- O núcleo em `Core/`, `DTOs/`, `Enums/`, `Security/`, `Spatial/`,
  `ValueObjects/` e `Contracts/` não deve depender de Laravel.
- Dependências de Laravel devem ficar em `Console/`, `Http/`, `Models/`,
  Service Provider e serviços de integração.
- Alterações em segurança exigem testes.
- Alterações em geometrias ou dados territoriais exigem fonte documentada.

## Dados geográficos

Não adicione fronteiras, polígonos ou coordenadas sem fonte oficial ou
verificável. O pacote não deve inventar limites territoriais.

Ao adicionar uma fonte, documente:

- Nome da fonte.
- Entidade responsável.
- URL ou referência.
- Licença.
- Data de obtenção.
- Sistema de referência, preferencialmente WGS 84 / EPSG:4326.

## Segurança

Não introduza bypasses públicos, como `?bypass=true`.

Para vulnerabilidades, consulte [SECURITY.md](SECURITY.md). Não abra issues
públicas com detalhes exploráveis.

## Pull requests

Um bom pull request deve incluir:

- Descrição do problema.
- Explicação da solução.
- Testes adicionados ou atualizados.
- Resultado de `composer quality`.
- Notas de migração, quando houver alteração incompatível.
