# Angola GeoGuard

Pacote PHP/Laravel para geolocalizacao, geofencing, controlo territorial e
seguranca geoespacial em Angola — permite que qualquer aplicacao restrinja
acesso por pais, provincia, geofence personalizado ou politica hibrida,
com suporte a multi-tenancy, auditoria e defesa em profundidade contra
VPN/proxy/Tor.

> **Estado do projeto**: nucleo, dados administrativos das 21 provincias,
> motor de politicas, middleware e seguranca de base implementados. As
> geometrias oficiais das fronteiras provinciais **nao estao incluidas** —
> devem ser importadas de uma fonte oficial (ver [Dados geograficos](#dados-geograficos-e-geometrias)).

## Indice

- [Requisitos](#requisitos)
- [Instalacao](#instalacao)
- [Configuracao](#configuracao)
- [Seed das 21 provincias](#seed-das-21-provincias)
- [Uso basico (API fluida)](#uso-basico-api-fluida)
- [Middleware](#middleware)
- [Modos de acesso](#modos-de-acesso)
- [Geofences personalizados](#geofences-personalizados)
- [Multi-tenancy](#multi-tenancy)
- [Provedores de geolocalizacao](#provedores-de-geolocalizacao)
- [Motor espacial (memoria / PostGIS / MySQL)](#motor-espacial)
- [Seguranca: proxies confiaveis e tokens de localizacao](#seguranca)
- [Excecoes de acesso](#excecoes-de-acesso)
- [Dados geograficos e geometrias](#dados-geograficos-e-geometrias)
- [Testes](#testes)
- [Politica de seguranca](#politica-de-seguranca)
- [Licenciamento](#licenciamento)

## Requisitos

- PHP 8.3+
- Laravel 10.x ou 11.x (opcional — o nucleo `Core/`, `Spatial/`, `Security/`
  e `DTOs/` funciona sem Laravel)
- `ext-json`

## Instalacao

```bash
composer require josequembi/angola-geoguard
```

```bash
php artisan vendor:publish \
  --provider="JoseQuembi\AngolaGeoGuard\AngolaGeoGuardServiceProvider"
```

```bash
php artisan migrate
```

## Configuracao

Todas as opcoes vivem em `config/angola-geoguard.php` e podem ser
definidas por variavel de ambiente:

```env
ANGOLA_GEOGUARD_ENABLED=true
ANGOLA_GEOGUARD_DEFAULT_MODE=angola_only
ANGOLA_GEOGUARD_FAILURE_MODE=deny
ANGOLA_GEOGUARD_BLOCK_VPN=false
ANGOLA_GEOGUARD_BLOCK_TOR=true
ANGOLA_GEOGUARD_TRUSTED_PROXIES=173.245.48.0/20,10.0.0.0/8
ANGOLA_GEOGUARD_TOKEN_KEY=troque-esta-chave-em-producao
ANGOLA_GEOGUARD_SPATIAL_ENGINE=memory
```

`FAILURE_MODE` controla o que acontece quando a localizacao nao pode ser
determinada: `deny` (recomendado para sistemas privados), `allow`,
`challenge` ou `observe` (nao bloqueia, apenas regista — util em rollout
gradual).

## Seed das 21 provincias

```bash
php artisan db:seed --class="JoseQuembi\AngolaGeoGuard\Database\Seeders\AngolaProvincesSeeder"
```

Semeia o pais Angola e as 21 provincias (incluindo Cuando, Cubango, Icolo
e Bengo e Moxico Leste, criadas pela Lei n.º 14/24) com nome oficial,
capital e codigo interno estavel (`AO-HUI`, `AO-LUA`, etc). **A geometria
fica `null`** ate importares uma fonte oficial.

## Uso basico (API fluida)

```php
use JoseQuembi\AngolaGeoGuard\Facades\GeoGuard;

$decision = GeoGuard::request($request)
    ->country('AO')
    ->minimumConfidence('medium')
    ->denyVpn()
    ->evaluate();

if ($decision->denied()) {
    abort(403, $decision->publicMessage());
}
```

Apenas uma provincia:

```php
GeoGuard::request($request)->province('huila')->evaluate();
```

Varias provincias:

```php
GeoGuard::request($request)->provinces(['huila', 'benguela', 'namibe'])->evaluate();
```

Por utilizador, com excecoes temporarias aplicadas automaticamente:

```php
GeoGuard::request($request)
    ->forUser($user->id)
    ->usingPolicy('government-private-access')
    ->evaluate();
```

## Middleware

```php
Route::middleware(['auth', 'geo.angola'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
});

Route::middleware(['geo.province:huila'])->group(function () {
    Route::get('/sistema-provincial', ProvincialController::class);
});

Route::middleware(['geo.provinces:huila,benguela,namibe'])->group(function () {
    Route::get('/regiao-sul', SouthernRegionController::class);
});

Route::middleware(['geo.policy:government-private-access'])->group(function () {
    Route::get('/sistema-interno', InternalSystemController::class);
});

Route::middleware(['geo.global', 'geo.no-vpn'])->group(function () {
    Route::get('/global', GlobalController::class);
});
```

Middleware disponiveis: `geo.angola`, `geo.province:<slug>`,
`geo.provinces:<slug1>,<slug2>`, `geo.global`, `geo.policy:<slug>`,
`geo.no-vpn`, `geo.no-proxy`, `geo.verified` (exige token de localizacao
assinado no cabecalho `X-Location-Token`).

Cada middleware guarda a decisao em `$request->attributes->get('geo_access_decision')`
para uso posterior (logging, UI condicional, etc).

**Nunca** existe bypass por parametro publico (`?bypass=true`) — por
desenho, para evitar evasao trivial.

## Modos de acesso

| Modo | `AccessMode` | Descricao |
|---|---|---|
| Global | `GLOBAL` | Qualquer pais; seguranca (VPN/Tor) continua a ser aplicada |
| Apenas Angola | `ANGOLA_ONLY` | So permite `country_code === 'AO'` |
| Uma provincia | `PROVINCE_ONLY` | Uma unica provincia autorizada |
| Varias provincias | `MULTIPLE_PROVINCES` | Lista de provincias autorizadas |
| Geofence personalizado | `CUSTOM_GEOFENCE` | Poligono/circulo/bounding box definidos pela aplicacao |
| Lista de bloqueio | `BLOCKLIST` | Bloqueia provincias especificas, permite o resto |
| Lista de permissao | `ALLOWLIST` | So permite provincias explicitamente listadas |
| Hibrido | `HYBRID` | Combina blocklist + allowlist (blocklist tem prioridade) |

## Geofences personalizados

```php
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;

$policy = GeoAccessPolicyConfig::fromArray([
    'mode' => AccessMode::CUSTOM_GEOFENCE,
    'allowed_geofences' => ['sede-luanda'],
]);

$geometries = [
    'sede-luanda' => $geofenceModel->geometry, // GeoJSON Polygon/MultiPolygon
];

$decision = app(\JoseQuembi\AngolaGeoGuard\Services\GeoAccessPolicyEngine::class)
    ->evaluate($location, $policy, $geometries);
```

Formas suportadas no model `Geofence`: `polygon`, `multipolygon`, `circle`
(centro + raio), `bounding_box`, `corridor`.

## Multi-tenancy

Implementa `TenantContextInterface` na tua aplicacao:

```php
final class AppTenantContext implements \JoseQuembi\AngolaGeoGuard\Contracts\TenantContextInterface
{
    public function __construct(private readonly Tenant $tenant) {}

    public function tenantId(): string { return (string) $this->tenant->id; }
    public function tenantSlug(): ?string { return $this->tenant->slug; }
    public function geoConfig(): array { return $this->tenant->geo_settings ?? []; }
}
```

```php
GeoGuard::request($request)
    ->forTenant(new AppTenantContext($tenant))
    ->evaluate();
```

As chaves de cache incluem sempre o tenant, a versao dos dados e a versao
da politica (`geoguard:{tenant}:{data_version}:{policy_version}:{ip_hash}`),
evitando fuga de decisoes entre tenants — ver `Tenancy\TenantAwareCacheKey`.

## Provedores de geolocalizacao

O pacote **nao forca nenhum servico comercial**. Por defeito, nenhum
provedor real esta configurado (`NullGeolocationProvider`), para nunca
inventar uma localizacao. Para ativar resolucao real por IP, registe um
adaptador que implemente `GeolocationProviderInterface`:

```php
$this->app->bind(
    \JoseQuembi\AngolaGeoGuard\Contracts\GeolocationProviderInterface::class,
    fn () => new MyMaxMindAdapter(config('angola-geoguard.providers.maxmind.database_path')),
);
```

## Motor espacial

Controlado por `angola-geoguard.spatial.engine`:

- `memory` (padrao) — ray-casting em PHP, sem dependencias externas,
  suporta poligonos com buracos e multipoligonos.
- `postgis` — delega para PostGIS via `ST_Contains`/`ST_DWithin`/`ST_Intersects`.
- `mysql` / `mariadb` — delega para funcoes `ST_*` do MySQL 8+/MariaDB.

## Seguranca

### Proxies confiaveis

```env
ANGOLA_GEOGUARD_TRUSTED_PROXIES=173.245.48.0/20,10.0.0.0/8
```

`X-Forwarded-For`, `CF-Connecting-IP`, `True-Client-IP` e `X-Real-IP` so
sao considerados quando a origem imediata do pedido pertence a um destes
CIDRs — caso contrario sao ignorados, para impedir spoofing.

### Tokens de localizacao assinados

Para rotas de alto risco onde IP nao chega, emite um token assinado com
GPS do dispositivo:

```php
use JoseQuembi\AngolaGeoGuard\Security\LocationToken;

$token = LocationToken::issue(
    userId: (string) $user->id,
    coordinates: new Coordinates($lat, $lng),
    signingKey: config('angola-geoguard.security.location_token.key'),
    ttlSeconds: 300,
);
```

O cliente envia o token no cabecalho `X-Location-Token`; o middleware
`geo.verified` valida assinatura HMAC, expiracao e protecao contra replay.

## Excecoes de acesso

```php
\JoseQuembi\AngolaGeoGuard\Models\GeoAccessExceptionGrant::create([
    'user_id' => $user->id,
    'reason' => 'Missao institucional autorizada',
    'authorized_territories' => ['global'],
    'expires_at' => now()->addHours(2),
    'created_by' => auth()->user()->email,
]);
```

Excecoes sao sempre explicitas, tipicamente temporarias, e auditaveis
(`usage_limit`, `usage_count`, `revoke()`).

## Dados geograficos e geometrias

Os dados administrativos (nome, capital, codigo interno) das 21
provincias sao verificados e incluidos no seeder. **As geometrias
(poligonos de fronteira) nao estao incluidas** — nao inventamos limites
territoriais. Importa a partir de uma fonte oficial (INE, IGCA, ou
equivalente) preenchendo a coluna `geometry` de `geo_provinces` com
GeoJSON valido em WGS 84 (EPSG:4326).

## Testes

```bash
composer install
vendor/bin/phpunit
```

Suites: `tests/Unit` (Value Objects, motor de politicas, seguranca —
sem dependencia de base de dados), `tests/Feature` (seeders, migrations,
via Orchestra Testbench), `tests/Security`, `tests/Integration`.

### Teste de integracao com fonte GeoJSON real

`tests/Integration/GeoJsonProvinceImportServiceTest.php` importa as
fronteiras ADM1 de Angola publicadas pelo
[geoBoundaries](https://www.geoboundaries.org) (William & Mary geoLab,
CC BY 4.0) — uma fonte real e nao controlada por este pacote, com 18
provincias (divisao pre-reforma de 2024) e convencao de propriedades
diferente da interna (`shapeISO` em vez de `internal_code`). O teste
confirma: 15 provincias casam diretamente pelo codigo, 2 (Benguela,
Cunene) casam por alias historico, a antiga provincia fundida "Kuando
Kubango" e corretamente reportada como sem correspondencia (em vez de
adivinhar qual das duas novas provincias lhe corresponde), as 4
provincias criadas em 2024 ficam corretamente sem geometria, e — o
mais importante — **Lubango cai dentro do poligono real importado da
Huila, e fora do poligono real de Luanda**, confirmando point-in-polygon
contra geometria oficial verdadeira, nao um fixture artificial.


## Politica de seguranca

Ver [SECURITY.md](SECURITY.md).

## Licenciamento

Proprietaria por padrao — ver [LICENSE](LICENSE). Configuravel pelo
proprietario do pacote antes da publicacao.
