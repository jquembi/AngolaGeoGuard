# Angola GeoGuard

[![Latest Version on Packagist](https://img.shields.io/packagist/v/josequembi/angola-geoguard.svg?style=flat-square)](https://packagist.org/packages/josequembi/angola-geoguard)
[![Total Downloads](https://img.shields.io/packagist/dt/josequembi/angola-geoguard.svg?style=flat-square)](https://packagist.org/packages/josequembi/angola-geoguard)
[![PHP Version](https://img.shields.io/packagist/php-v/josequembi/angola-geoguard.svg?style=flat-square)](https://packagist.org/packages/josequembi/angola-geoguard)
[![License](https://img.shields.io/packagist/l/josequembi/angola-geoguard.svg?style=flat-square)](LICENSE)

**Angola GeoGuard** é um pacote PHP/Laravel para geolocalização, geofencing,
controlo territorial, restrição de acesso e segurança geoespacial em Angola.
Ele permite proteger rotas, APIs e fluxos internos por país, província,
geofence personalizado, listas de permissão/bloqueio e políticas híbridas.

O pacote foi desenhado para aplicações que precisam de decisões territoriais
auditáveis: SaaS multi-tenant, portais institucionais, plataformas privadas,
operações com restrições provinciais e sistemas que exigem defesa contra VPN,
proxy, Tor, datacenters e padrões de tráfego suspeitos.

> **Estado do projeto:** núcleo, modelos, migrations, seed das 21 províncias,
> motor de políticas, middleware, auditoria, importação GeoJSON e deteção
> comportamental estão implementados. As geometrias oficiais de fronteiras
> provinciais **não são incluídas** no pacote; devem ser importadas de uma
> fonte oficial ou verificável.

## Destaques

- Suporte às **21 províncias de Angola**, incluindo a reorganização de 2024.
- API fluida via `GeoGuard::request($request)->province(...)->evaluate()`.
- Middleware Laravel: `geo.angola`, `geo.province`, `geo.provinces`,
  `geo.policy`, `geo.global`, `geo.no-vpn`, `geo.no-proxy` e `geo.verified`.
- Motor espacial em memória, PostGIS ou MySQL/MariaDB Spatial.
- Tokens de localização assinados por HMAC, com expiração e proteção contra replay.
- Resolução segura de IP real atrás de proxies confiáveis.
- Auditoria de decisões e comandos Artisan para diagnóstico, importação e limpeza.
- Deteção comportamental heurística com contramedidas progressivas.
- Comando `geoguard:calibrate` para sugerir limiares a partir do histórico real.
- Testes automatizados com PHPUnit, PHPStan 2 e Laravel Pint.

## Índice

- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Publicação da configuração e migrations](#publicação-da-configuração-e-migrations)
- [Configuração básica](#configuração-básica)
- [Seed das províncias](#seed-das-províncias)
- [Uso rápido](#uso-rápido)
- [Middleware](#middleware)
- [Políticas e modos de acesso](#políticas-e-modos-de-acesso)
- [Geofences personalizados](#geofences-personalizados)
- [Dados geográficos e fronteiras](#dados-geográficos-e-fronteiras)
- [Provedores de geolocalização](#provedores-de-geolocalização)
- [Segurança](#segurança)
- [Deteção comportamental](#deteção-comportamental)
- [Calibração por tráfego real](#calibração-por-tráfego-real)
- [Comandos Artisan](#comandos-artisan)
- [Qualidade e testes](#qualidade-e-testes)
- [Publicação no Packagist](#publicação-no-packagist)
- [Segurança e licença](#segurança-e-licença)

## Requisitos

- PHP 8.3 ou superior.
- Laravel 10, 11 ou 12 para integração completa.
- Extensão PHP `json`.

O núcleo em `Core/`, `DTOs/`, `Enums/`, `Security/`, `Spatial/` e
`ValueObjects/` é framework-agnostic e pode ser testado sem Laravel.

## Instalação

```bash
composer require josequembi/angola-geoguard
```

## Publicação da configuração e migrations

```bash
php artisan vendor:publish \
  --provider="JoseQuembi\AngolaGeoGuard\AngolaGeoGuardServiceProvider"

php artisan migrate
```

Também pode publicar apenas grupos específicos:

```bash
php artisan vendor:publish --tag=angola-geoguard-config
php artisan vendor:publish --tag=angola-geoguard-migrations
```

## Configuração básica

Todas as opções ficam em `config/angola-geoguard.php` e podem ser
controladas por variáveis de ambiente:

```env
ANGOLA_GEOGUARD_ENABLED=true
ANGOLA_GEOGUARD_DEFAULT_MODE=angola_only
ANGOLA_GEOGUARD_FAILURE_MODE=deny

ANGOLA_GEOGUARD_BLOCK_VPN=false
ANGOLA_GEOGUARD_BLOCK_PROXY=false
ANGOLA_GEOGUARD_BLOCK_TOR=true
ANGOLA_GEOGUARD_BLOCK_DATACENTER=false

ANGOLA_GEOGUARD_TRUSTED_PROXIES=173.245.48.0/20,10.0.0.0/8
ANGOLA_GEOGUARD_TOKEN_KEY=troque-esta-chave-em-producao
ANGOLA_GEOGUARD_SPATIAL_ENGINE=memory
```

`ANGOLA_GEOGUARD_FAILURE_MODE` define o que acontece quando a localização
não pode ser resolvida:

- `deny`: nega por padrão, recomendado para sistemas privados.
- `allow`: permite por padrão, útil apenas em cenários de baixo risco.
- `challenge`: exige verificação adicional.
- `observe`: não bloqueia, apenas regista a decisão.

## Seed das províncias

```bash
php artisan geoguard:seed-angola
```

Ou diretamente pelo seeder:

```bash
php artisan db:seed --class="JoseQuembi\AngolaGeoGuard\Database\Seeders\AngolaProvincesSeeder"
```

O seed cria Angola e as 21 províncias com nome oficial, capital e código
interno estável. As colunas de geometria ficam vazias até importar dados
oficiais ou verificáveis.

## Uso rápido

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

Restringir a uma província:

```php
GeoGuard::request($request)
    ->province('huila')
    ->evaluate();
```

Permitir várias províncias:

```php
GeoGuard::request($request)
    ->provinces(['huila', 'benguela', 'namibe'])
    ->evaluate();
```

Usar uma política persistida e exceções temporárias por utilizador:

```php
GeoGuard::request($request)
    ->forUser($user->id)
    ->usingPolicy('acesso-interno-governo')
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

Route::middleware(['geo.policy:acesso-interno-governo'])->group(function () {
    Route::get('/sistema-interno', InternalSystemController::class);
});

Route::middleware(['geo.global', 'geo.no-vpn'])->group(function () {
    Route::get('/global', GlobalController::class);
});
```

A decisão fica disponível em:

```php
$request->attributes->get('geo_access_decision');
```

Não existe bypass por parâmetro público, como `?bypass=true`. Essa decisão é
intencional para evitar evasão trivial.

## Políticas e modos de acesso

| Modo | Enum | Descrição |
|---|---|---|
| Global | `GLOBAL` | Permite qualquer país; regras de segurança continuam ativas |
| Apenas Angola | `ANGOLA_ONLY` | Permite apenas `country_code === 'AO'` |
| Uma província | `PROVINCE_ONLY` | Permite uma única província |
| Várias províncias | `MULTIPLE_PROVINCES` | Permite uma lista de províncias |
| Geofence personalizado | `CUSTOM_GEOFENCE` | Avalia polígonos ou multipolígonos GeoJSON |
| Lista de bloqueio | `BLOCKLIST` | Bloqueia províncias específicas |
| Lista de permissão | `ALLOWLIST` | Permite apenas províncias listadas |
| Híbrido | `HYBRID` | Combina lista de bloqueio e permissão; bloqueio tem prioridade |

## Geofences personalizados

```php
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;
use JoseQuembi\AngolaGeoGuard\Services\GeoAccessPolicyEngine;

$policy = GeoAccessPolicyConfig::fromArray([
    'mode' => AccessMode::CUSTOM_GEOFENCE,
    'allowed_geofences' => ['sede-luanda'],
]);

$geometries = [
    'sede-luanda' => $geofenceModel->geometry,
];

$decision = app(GeoAccessPolicyEngine::class)
    ->evaluate($location, $policy, $geometries);
```

O motor em memória aceita `Polygon` e `MultiPolygon` no formato GeoJSON,
incluindo polígonos com buracos.

## Dados geográficos e fronteiras

O pacote inclui dados administrativos das províncias, mas **não inclui
fronteiras oficiais**. Isso evita publicar limites territoriais inventados,
desatualizados ou sem licença clara.

Para importar geometrias:

```bash
php artisan geoguard:import \
  --file=/caminho/fronteiras-ago-adm1.geojson \
  --source="Fonte oficial ou verificável" \
  --version=2026.1
```

O importador valida `FeatureCollection`, `Polygon` e `MultiPolygon`, cria
uma nova versão em `geo_data_versions` e associa as geometrias às províncias
existentes por código interno, slug ou alias documentado.

## Provedores de geolocalização

O pacote não força nenhum serviço comercial. Por padrão, usa
`NullGeolocationProvider` para nunca inventar localização.

Para ativar resolução por IP, registe um adaptador que implemente
`GeolocationProviderInterface`:

```php
$this->app->bind(
    \JoseQuembi\AngolaGeoGuard\Contracts\GeolocationProviderInterface::class,
    fn () => new MyMaxMindAdapter(config('angola-geoguard.providers.maxmind.database_path')),
);
```

## Segurança

### Proxies confiáveis

Cabeçalhos como `X-Forwarded-For`, `CF-Connecting-IP`, `True-Client-IP` e
`X-Real-IP` só são considerados quando o IP de origem pertence a um CIDR
configurado em `ANGOLA_GEOGUARD_TRUSTED_PROXIES`.

Nunca use:

```env
ANGOLA_GEOGUARD_TRUSTED_PROXIES=0.0.0.0/0
```

### Tokens de localização assinados

Para rotas de alto risco, pode exigir um token assinado:

```php
use JoseQuembi\AngolaGeoGuard\Security\LocationToken;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

$token = LocationToken::issue(
    userId: (string) $user->id,
    coordinates: new Coordinates($lat, $lng),
    signingKey: config('angola-geoguard.security.location_token.key'),
    ttlSeconds: 300,
);
```

O cliente envia o token no cabeçalho `X-Location-Token`. O middleware
`geo.verified` valida assinatura HMAC, expiração, coordenadas e replay.

## Deteção comportamental

Além da decisão por pedido, o pacote observa padrões por sujeito ao longo do
tempo. Essa camada é **heurística e estatística**; não é um modelo de machine
learning treinado.

Sinais avaliados:

| Sinal | Padrão detetado |
|---|---|
| `impossible_travel` | Velocidade implícita fisicamente improvável entre duas localizações |
| `high_denial_ratio` | Muitos pedidos negados numa janela curta |
| `rapid_fire` | Intervalo entre pedidos muito abaixo da linha de base aprendida |
| `province_enumeration` | Muitas províncias distintas tentadas numa janela curta |
| `country_hopping` | Mudanças frequentes de país |
| `evasion_signal_cycling` | Alternância repetida de VPN, proxy ou Tor |

Contramedidas possíveis:

```text
NONE -> LOG_ONLY -> CHALLENGE -> THROTTLE -> QUARANTINE
```

A quarentena é escalonada por reincidência, no estilo fail2ban.

## Calibração por tráfego real

Depois de acumular histórico em `geo_access_decisions`, use:

```bash
php artisan geoguard:calibrate --days=30
php artisan geoguard:calibrate --days=30 --env
```

O comando sugere limiares para `threat_detection.thresholds` com base em
percentis reais: taxa de negação, enumeração territorial, alternância de
sinais de evasão, rajadas de pedidos e viagem impossível.

O comando é somente leitura; ele não altera configuração automaticamente.

## Comandos Artisan

```bash
php artisan geoguard:install
php artisan geoguard:publish
php artisan geoguard:seed-angola
php artisan geoguard:import --file=fronteiras.geojson --source="Fonte" --version=2026.1
php artisan geoguard:validate
php artisan geoguard:diagnose
php artisan geoguard:audit --days=7
php artisan geoguard:calibrate --days=30 --env
php artisan geoguard:threats
php artisan geoguard:clear-cache
php artisan geoguard:prune
php artisan geoguard:rollback-data 2026.1
```

## Qualidade e testes

```bash
composer install
composer quality
```

`composer quality` executa:

- Laravel Pint.
- PHPStan 2 no nível 8.
- PHPUnit.

Também pode executar separadamente:

```bash
composer pint:test
composer stan
composer test
```

## Publicação no Packagist

Para boa apresentação no Packagist:

1. Garanta que `composer.json` está validado:

   ```bash
   composer validate --strict
   ```

2. Publique o repositório no GitHub com uma tag semântica:

   ```bash
   git tag v0.1.0
   git push origin main --tags
   ```

3. Registe o pacote em [packagist.org](https://packagist.org).
4. Ative o hook GitHub/Packagist para atualizar releases automaticamente.
5. Mantenha o README em português claro, com exemplos executáveis e changelog.

## Segurança e licença

Vulnerabilidades devem ser reportadas de forma privada. Consulte
[SECURITY.md](SECURITY.md).

O pacote usa licença proprietária por padrão. Consulte [LICENSE](LICENSE) e
ajuste a licença antes de publicação pública caso deseje distribuição open
source.
