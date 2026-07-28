<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Enums;

/**
 * Comportamento do pacote quando a localizacao nao pode ser resolvida
 * ou um provedor falha.
 */
enum FailureMode: string
{
    /** Permite o acesso quando a localizacao nao puder ser confirmada. */
    case ALLOW = 'allow';

    /** Bloqueia o acesso quando a localizacao nao puder ser confirmada. Padrao recomendado. */
    case DENY = 'deny';

    /** Solicita verificacao adicional (ex.: MFA, confirmacao manual). */
    case CHALLENGE = 'challenge';

    /** Nao bloqueia, apenas regista o resultado (rollout gradual). */
    case OBSERVE = 'observe';
}
