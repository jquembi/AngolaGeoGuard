# Política de Segurança

## Reportar uma vulnerabilidade

Se encontrar uma vulnerabilidade de segurança no `angola-geoguard`, por
favor **não abra uma issue pública**.

Contacte o mantenedor diretamente pelos canais indicados em `composer.json`
ou pelo repositório oficial, incluindo:

- Descrição da vulnerabilidade.
- Impacto potencial.
- Passos para reproduzir.
- Versão do pacote afetada.
- Sugestão de correção, se possível.

O objetivo é confirmar a receção em até 72 horas e manter o reporter
informado sobre o progresso da correção.

## Alcance

Este pacote toma decisões de controlo de acesso com base em sinais
geográficos e comportamentais. Áreas especialmente sensíveis:

- Bypass do `TrustedProxyIpResolver`.
- Falsificação de IP por cabeçalhos não confiáveis.
- Falsificação, replay ou aceitação indevida de `LocationToken`.
- Decisões incorretas em `GeoAccessPolicyEngine`.
- Fuga de dados entre tenants.
- Exposição de regras internas, geometrias sensíveis ou detalhes de risco em
  respostas públicas.
- Contramedidas comportamentais que bloqueiem utilizadores legítimos sem
  evidência suficiente.

## Limitações conhecidas

Geolocalização nunca é prova absoluta de presença física. O pacote trata
localização por IP e sinais de evasão como indícios probabilísticos.

Para cenários de alto risco, combine o Angola GeoGuard com:

- MFA.
- Dispositivos registados.
- Redes privadas institucionais.
- Tokens de localização assinados.
- Auditoria e revisão humana.

## Boas práticas

- Use `ANGOLA_GEOGUARD_FAILURE_MODE=deny` em sistemas privados.
- Configure `ANGOLA_GEOGUARD_TRUSTED_PROXIES` apenas com CIDRs reais da sua
  infraestrutura.
- Nunca use `0.0.0.0/0` como proxy confiável.
- Defina uma chave forte em `ANGOLA_GEOGUARD_TOKEN_KEY`.
- Rode a chave de tokens periodicamente.
- Não exponha detalhes internos de `reason_code` ao utilizador final.
- Use `GeoAccessDecision::publicMessage()` para respostas públicas.

## Versões suportadas

Enquanto o pacote estiver antes de `1.0.0`, apenas a versão de desenvolvimento
mais recente recebe correções de segurança.
