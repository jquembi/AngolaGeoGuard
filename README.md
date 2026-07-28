Aqui está o texto com os erros ortográficos corrigidos:

```markdown
# Angola GeoGuard

Pacote PHP/Laravel para geolocalização, geofencing, controlo territorial e
segurança geoespacial em Angola — permite que qualquer aplicação restrinja
acesso por país, província, geofence personalizado ou política híbrida,
com suporte a multi-tenancy, auditoria e defesa em profundidade contra
VPN/proxy/Tor.

> **Estado do projeto**: núcleo, dados administrativos das 21 províncias,
> motor de políticas, middleware e segurança de base implementados. As
> geometrias oficiais das fronteiras provinciais **não estão incluídas** —
> devem ser importadas de uma fonte oficial (ver [Dados geográficos](#dados-geograficos-e-geometrias)).

## Índice

- [Requisitos](#requisitos)
- [Instalação](#instalacao)
- [Configuração](#configuracao)
- [Seed das 21 províncias](#seed-das-21-provincias)
- [Uso básico (API fluida)](#uso-basico-api-fluida)
- [Middleware](#middleware)
- [Modos de acesso](#modos-de-acesso)
- [Geofences personalizados](#geofences-personalizados)
- [Multi-tenancy](#multi-tenancy)
- [Provedores de geolocalização](#provedores-de-geolocalizacao)
- [Motor espacial (memória / PostGIS / MySQL)](#motor-espacial)
- [Segurança: proxies confiáveis e tokens de localização](#seguranca)
- [Exceções de acesso](#excecoes-de-acesso)
- [Dados geográficos e geometrias](#dados-geograficos-e-geometrias)
- [Testes](#testes)
- [Política de segurança](#politica-de-seguranca)
- [Licenciamento](#licenciamento)

## Requisitos

- PHP 8.3+
- Laravel 10.x ou 11.x (opcional — o núcleo `Core/`, `Spatial/`, `Security/`
  e `DTOs/` funciona sem Laravel)
- `ext-json`

## Instalação

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

## Configuração

Todas as opções vivem em `config/angola-geoguard.php` e podem ser
definidas por variável de ambiente:

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

`FAILURE_MODE` controla o que acontece quando a localização não pode ser
determinada: `deny` (recomendado para sistemas privados), `allow`,
`challenge` ou `observe` (não bloqueia, apenas regista — útil em rollout
gradual).

## Seed das 21 províncias

```bash
php artisan db:seed --class="JoseQuembi\AngolaGeoGuard\Database\Seeders\AngolaProvincesSeeder"
```

Semeia o país Angola e as 21 províncias (incluindo Cuando, Cubango, Icolo
e Bengo e Moxico Leste, criadas pela Lei n.º 14/24) com nome oficial,
capital e código interno estável (`AO-HUI`, `AO-LUA`, etc). **A geometria
fica `null`** até importares uma fonte oficial.

## Uso básico (API fluida)

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

Apenas uma província:

```php
GeoGuard::request($request)->province('huila')->evaluate();
```

Várias províncias:

```php
GeoGuard::request($request)->provinces(['huila', 'benguela', 'namibe'])->evaluate();
```

Por utilizador, com exceções temporárias aplicadas automaticamente:

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

Middleware disponíveis: `geo.angola`, `geo.province:<slug>`,
`geo.provinces:<slug1>,<slug2>`, `geo.global`, `geo.policy:<slug>`,
`geo.no-vpn`, `geo.no-proxy`, `geo.verified` (exige token de localização
assinado no cabeçalho `X-Location-Token`).

Cada middleware guarda a decisão em `$request->attributes->get('geo_access_decision')`
para uso posterior (logging, UI condicional, etc).

**Nunca** existe bypass por parâmetro público (`?bypass=true`) — por
desenho, para evitar evasão trivial.

## Modos de acesso

| Modo | `AccessMode` | Descrição |
|---|---|---|
| Global | `GLOBAL` | Qualquer país; segurança (VPN/Tor) continua a ser aplicada |
| Apenas Angola | `ANGOLA_ONLY` | Só permite `country_code === 'AO'` |
| Uma província | `PROVINCE_ONLY` | Uma única província autorizada |
| Várias províncias | `MULTIPLE_PROVINCES` | Lista de províncias autorizadas |
| Geofence personalizado | `CUSTOM_GEOFENCE` | Polígono/círculo/bounding box definidos pela aplicação |
| Lista de bloqueio | `BLOCKLIST` | Bloqueia províncias específicas, permite o resto |
| Lista de permissão | `ALLOWLIST` | Só permite províncias explicitamente listadas |
| Híbrido | `HYBRID` | Combina blocklist + allowlist (blocklist tem prioridade) |

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

Implementa `TenantContextInterface` na tua aplicação:

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

As chaves de cache incluem sempre o tenant, a versão dos dados e a versão
da política (`geoguard:{tenant}:{data_version}:{policy_version}:{ip_hash}`),
evitando fuga de decisões entre tenants — ver `Tenancy\TenantAwareCacheKey`.

## Provedores de geolocalização

O pacote **não força nenhum serviço comercial**. Por defeito, nenhum
provedor real está configurado (`NullGeolocationProvider`), para nunca
inventar uma localização. Para ativar resolução real por IP, registe um
adaptador que implemente `GeolocationProviderInterface`:

```php
$this->app->bind(
    \JoseQuembi\AngolaGeoGuard\Contracts\GeolocationProviderInterface::class,
    fn () => new MyMaxMindAdapter(config('angola-geoguard.providers.maxmind.database_path')),
);
```

## Motor espacial

Controlado por `angola-geoguard.spatial.engine`:

- `memory` (padrão) — ray-casting em PHP, sem dependências externas,
  suporta polígonos com buracos e multipolígonos.
- `postgis` — delega para PostGIS via `ST_Contains`/`ST_DWithin`/`ST_Intersects`.
- `mysql` / `mariadb` — delega para funções `ST_*` do MySQL 8+/MariaDB.

## Segurança

### Proxies confiáveis

```env
ANGOLA_GEOGUARD_TRUSTED_PROXIES=173.245.48.0/20,10.0.0.0/8
```

`X-Forwarded-For`, `CF-Connecting-IP`, `True-Client-IP` e `X-Real-IP` só
são considerados quando a origem imediata do pedido pertence a um destes
CIDRs — caso contrário são ignorados, para impedir spoofing.

### Tokens de localização assinados

Para rotas de alto risco onde IP não chega, emite um token assinado com
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

O cliente envia o token no cabeçalho `X-Location-Token`; o middleware
`geo.verified` valida assinatura HMAC, expiração e proteção contra replay.

## Exceções de acesso

```php
\JoseQuembi\AngolaGeoGuard\Models\GeoAccessExceptionGrant::create([
    'user_id' => $user->id,
    'reason' => 'Missão institucional autorizada',
    'authorized_territories' => ['global'],
    'expires_at' => now()->addHours(2),
    'created_by' => auth()->user()->email,
]);
```

Exceções são sempre explícitas, tipicamente temporárias, e auditáveis
(`usage_limit`, `usage_count`, `revoke()`).

## Dados geográficos e geometrias

Os dados administrativos (nome, capital, código interno) das 21
províncias são verificados e incluídos no seeder. **As geometrias
(polígonos de fronteira) não estão incluídas** — não inventamos limites
territoriais. Importa a partir de uma fonte oficial (INE, IGCA, ou
equivalente) preenchendo a coluna `geometry` de `geo_provinces` com
GeoJSON válido em WGS 84 (EPSG:4326).

## Testes

```bash
composer install
vendor/bin/phpunit
```

Suites: `tests/Unit` (Value Objects, motor de políticas, segurança —
sem dependência de base de dados), `tests/Feature` (seeders, migrations,
via Orchestra Testbench), `tests/Security`, `tests/Integration`.

### Teste de integração com fonte GeoJSON real

`tests/Integration/GeoJsonProvinceImportServiceTest.php` importa as
fronteiras ADM1 de Angola publicadas pelo
[geoBoundaries](https://www.geoboundaries.org) (William & Mary geoLab,
CC BY 4.0) — uma fonte real e não controlada por este pacote, com 18
províncias (divisão pré-reforma de 2024) e convenção de propriedades
diferente da interna (`shapeISO` em vez de `internal_code`). O teste
confirma: 15 províncias casam diretamente pelo código, 2 (Benguela,
Cunene) casam por alias histórico, a antiga província fundida "Kuando
Kubango" é corretamente reportada como sem correspondência (em vez de
adivinhar qual das duas novas províncias lhe corresponde), as 4
províncias criadas em 2024 ficam corretamente sem geometria, e — o
mais importante — **Lubango cai dentro do polígono real importado da
Huíla, e fora do polígono real de Luanda**, confirmando point-in-polygon
contra geometria oficial verdadeira, não um fixture artificial.


## Política de segurança

Ver [SECURITY.md](SECURITY.md).

## Licenciamento

Proprietária por padrão — ver [LICENSE](LICENSE). Configurável pelo
proprietário do pacote antes da publicação.
```

