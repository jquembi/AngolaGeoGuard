# Changelog

Todas as alteracoes notaveis a este pacote serao documentadas neste
ficheiro. O formato segue [Keep a Changelog](https://keepachangelog.com/pt-PT/1.0.0/)
e este projeto adere a [Versionamento Semantico](https://semver.org/lang/pt-PT/).

## [Nao lancado]

### Adicionado

- Nucleo framework-agnostic: enums (`AccessMode`, `ConfidenceLevel`,
  `RiskLevel`, `FailureMode`, `DecisionReasonCode`), Value Objects
  (`Coordinates`, `Territory`, `BoundingBox`), DTOs imutaveis
  (`LocationResult`, `GeoAccessDecision`, `GeoAccessPolicyConfig`,
  `ProviderHealth`).
- Dados administrativos das 21 provincias de Angola (Lei n.º 14/24),
  com seeder verificado e sistema de versionamento/importacao GeoJSON
  com hash chain (`geo_data_versions`).
- Motor espacial com tres implementacoes: `InMemorySpatialEngine`
  (ray-casting, sem dependencias externas), `PostGisSpatialEngine`,
  `MySqlSpatialEngine`.
- `GeoAccessPolicyEngine`: motor de decisao suportando os oito modos
  (GLOBAL, ANGOLA_ONLY, PROVINCE_ONLY, MULTIPLE_PROVINCES,
  CUSTOM_GEOFENCE, BLOCKLIST, ALLOWLIST, HYBRID).
- Seguranca: `TrustedProxyIpResolver` (validacao de proxies por CIDR),
  `LocationToken` (HMAC assinado, com protecao contra replay).
- 8 middleware Laravel (`geo.angola`, `geo.province`, `geo.provinces`,
  `geo.global`, `geo.policy`, `geo.no-vpn`, `geo.no-proxy`, `geo.verified`).
- API fluida via Facade `GeoGuard` (`request()->province()->denyVpn()->evaluate()`).
- Suporte multi-tenant: `TenantContextInterface`, isolamento de cache
  por tenant/versao de dados/versao de politica.
- Excecoes de acesso temporarias e auditaveis (`GeoAccessExceptionGrant`).
- 15 eventos de dominio (`GeoAccessAllowed`, `GeoAccessDenied`,
  `GeoExceptionGranted`, `GeoDataImported`, etc).
- 12 comandos Artisan: `geoguard:install`, `geoguard:publish`,
  `geoguard:seed-angola`, `geoguard:import`, `geoguard:validate`,
  `geoguard:sync`, `geoguard:cache`, `geoguard:clear-cache`,
  `geoguard:diagnose`, `geoguard:audit`, `geoguard:prune`,
  `geoguard:rollback-data`.
- Suites de testes unitarios, de seguranca e de feature (PHPUnit +
  Orchestra Testbench).
- CI via GitHub Actions (matriz PHP 8.1–8.3 × Laravel 10/11, PHPStan
  nivel 8, Laravel Pint, `composer audit`).

### Nota

Este pacote **nao inclui geometrias oficiais de fronteiras** das
provincias angolanas — apenas a estrutura de importacao e
versionamento. As geometrias devem ser importadas de uma fonte oficial
(INE, IGCA ou equivalente) via `geoguard:import`.

## [0.1.0] - Nao lancado

Versao inicial de desenvolvimento (Fases 1–7 do plano de implementacao).
