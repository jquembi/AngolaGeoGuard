<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Enums;

/**
 * Codigos de motivo associados a uma GeoAccessDecision.
 *
 * Estes codigos sao para uso interno/auditoria. As respostas publicas
 * (ver Http/Resources) NAO devem expor o codigo detalhado ao cliente
 * final para nao facilitar evasao (ver secao 27 do prompt mestre).
 */
enum DecisionReasonCode: string
{
    case ACCESS_ALLOWED_GLOBAL = 'ACCESS_ALLOWED_GLOBAL';
    case ACCESS_ALLOWED_COUNTRY = 'ACCESS_ALLOWED_COUNTRY';
    case ACCESS_ALLOWED_PROVINCE = 'ACCESS_ALLOWED_PROVINCE';
    case ACCESS_ALLOWED_EXCEPTION = 'ACCESS_ALLOWED_EXCEPTION';

    case ACCESS_DENIED_COUNTRY = 'ACCESS_DENIED_COUNTRY';
    case ACCESS_DENIED_PROVINCE = 'ACCESS_DENIED_PROVINCE';
    case ACCESS_DENIED_OUTSIDE_GEOFENCE = 'ACCESS_DENIED_OUTSIDE_GEOFENCE';
    case ACCESS_DENIED_LOW_CONFIDENCE = 'ACCESS_DENIED_LOW_CONFIDENCE';
    case ACCESS_DENIED_VPN = 'ACCESS_DENIED_VPN';
    case ACCESS_DENIED_PROXY = 'ACCESS_DENIED_PROXY';
    case ACCESS_DENIED_TOR = 'ACCESS_DENIED_TOR';
    case ACCESS_DENIED_DATACENTER = 'ACCESS_DENIED_DATACENTER';
    case ACCESS_DENIED_LOCATION_MISMATCH = 'ACCESS_DENIED_LOCATION_MISMATCH';
    case ACCESS_DENIED_UNRESOLVED_LOCATION = 'ACCESS_DENIED_UNRESOLVED_LOCATION';

    case ACCESS_REQUIRES_VERIFICATION = 'ACCESS_REQUIRES_VERIFICATION';

    public function isAllowed(): bool
    {
        return str_starts_with($this->value, 'ACCESS_ALLOWED_');
    }

    public function isDenied(): bool
    {
        return str_starts_with($this->value, 'ACCESS_DENIED_');
    }
}
