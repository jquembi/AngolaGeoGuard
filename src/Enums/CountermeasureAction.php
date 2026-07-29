<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Enums;

/**
 * Acao de contramedida aplicada em resposta a um ThreatLevel,
 * atribuida pelo CountermeasureEngine. Ver secao 13/46 do prompt
 * mestre (defesa em profundidade, resposta a evasao).
 */
enum CountermeasureAction: string
{
    /** Nenhuma acao — comportamento normal. */
    case NONE = 'none';

    /** Apenas regista o sinal para analise posterior; nao afeta o pedido atual. */
    case LOG_ONLY = 'log_only';

    /** Exige verificacao adicional (ex.: geo.verified) nos proximos pedidos. */
    case CHALLENGE = 'challenge';

    /** Aplica um atraso/limite de taxa aos proximos pedidos do sujeito. */
    case THROTTLE = 'throttle';

    /** Bloqueia todos os pedidos do sujeito por um periodo, com duracao escalonada para reincidentes. */
    case QUARANTINE = 'quarantine';

    public function severity(): int
    {
        return match ($this) {
            self::NONE => 0,
            self::LOG_ONLY => 1,
            self::CHALLENGE => 2,
            self::THROTTLE => 3,
            self::QUARANTINE => 4,
        };
    }
}
