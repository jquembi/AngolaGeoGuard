# Changelog

Todas as alterações relevantes deste pacote serão documentadas neste ficheiro.
O formato segue [Keep a Changelog](https://keepachangelog.com/pt-PT/1.0.0/) e
o projeto adere a [Versionamento Semântico](https://semver.org/lang/pt-PT/).

## [Não lançado]

### Adicionado

- Comando `geoguard:calibrate`, que sugere limiares de deteção
  comportamental a partir do histórico real em `geo_access_decisions`.
- Deteção comportamental e contramedidas progressivas:
  `ThreatScorer`, `CountermeasureEngine`, `BehaviorTrackingService`,
  `GeoBehaviorProfile`, eventos de segurança e comando `geoguard:threats`.
- Suporte a perfis comportamentais persistidos e pruning de perfis inativos.
- Testes para o motor de ameaças e contramedidas.
- Documentação de Packagist, badges, comandos, calibração e publicação.
- `.gitignore` adequado para pacote Composer/Laravel.

### Alterado

- `phpstan/phpstan` atualizado para `^2.2`.
- `rector/rector` atualizado para `^2.0`, compatível com PHPStan 2.
- `phpstan.neon` atualizado para usar identificadores de erro modernos.
- README reescrito em português, com ortografia corrigida e exemplos mais claros.
- Metadados do Composer ampliados com keywords de descoberta no Packagist.

### Corrigido

- Validação de `LocationToken` contra payloads assinados, mas incompletos,
  inválidos ou não estruturados como objeto.
- Tratamento de pontos exatamente sobre a borda de polígonos no motor espacial.
- Validação de anéis GeoJSON malformados com exceções de domínio.
- Tipagem de comandos Artisan, middlewares e modelos Eloquent para PHPStan 2.
- Limpeza de cache usando o store subjacente compatível com Laravel.
- Pequenas correções ortográficas e de codificação em documentação.

## [0.1.0] - Não lançado

Versão inicial de desenvolvimento do pacote.

### Inclui

- Núcleo framework-agnostic com enums, DTOs, Value Objects e contratos.
- Dados administrativos das 21 províncias de Angola.
- Migrations, models e seeder de províncias.
- Motor espacial em memória, PostGIS e MySQL/MariaDB Spatial.
- Motor de políticas geográficas com modos global, Angola, províncias,
  geofence, lista de bloqueio, lista de permissão e modo híbrido.
- Middleware Laravel para controlo de acesso geográfico.
- Trusted proxy resolver, tokens de localização e proteção contra replay.
- Auditoria de decisões e eventos de domínio.
- Importação GeoJSON com versionamento de dados territoriais.
- Testes unitários, feature, integração e segurança.

### Nota

O pacote não inclui geometrias oficiais de fronteiras provinciais. As
geometrias devem ser importadas de uma fonte oficial ou verificável.
