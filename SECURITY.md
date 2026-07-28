# Politica de Seguranca

## Reportar uma vulnerabilidade

Se encontrares uma vulnerabilidade de seguranca no `angola-geoguard`,
por favor **nao abras uma issue publica**. Contacta o mantenedor
diretamente (ver `composer.json` -> `support`) com:

- Descricao da vulnerabilidade e impacto potencial;
- Passos para reproduzir;
- Versao do pacote afetada;
-, se possivel, uma sugestao de correcao.

Compromentemo-nos a confirmar a receção em ate 72 horas e a manter-te
informado sobre o progresso da correcao.

## Alcance

Este pacote toma decisoes de controlo de acesso com base em
geolocalizacao. Uma falha de seguranca aqui pode resultar em bypass de
restricoes territoriais. Areas de particular interesse para reports:

- Bypass do `TrustedProxyIpResolver` (falsificacao de IP aceite
  incorretamente como confiavel);
- Falsificacao ou replay de `LocationToken`;
- Bypass do motor de decisao `GeoAccessPolicyEngine` (ex.: um modo que
  permite acesso quando deveria negar);
- Fuga de dados entre tenants (isolamento de cache/politicas/geofences);
- Exposicao de informacao interna (regras completas, geometrias
  sensiveis) em respostas publicas de bloqueio.

## Limitacoes conhecidas e por desenho

Geolocalizacao **nunca** e uma garantia absoluta de presenca fisica. O
pacote:

- Nao pretende impedir toda a evasao possivel (VPN institucional bem
  configurada, dispositivos comprometidos, partilha de credenciais, etc);
- Recomenda combinar com MFA, dispositivos registados, ou redes privadas
  institucionais para cenarios de alto risco;
- Trata geolocalizacao por IP como um sinal probabilistico, nunca como
  prova — daí o sistema de `ConfidenceLevel` e `RiskLevel`.

## Boas praticas para quem usa o pacote

- Define `ANGOLA_GEOGUARD_FAILURE_MODE=deny` para sistemas privados;
- Configura `ANGOLA_GEOGUARD_TRUSTED_PROXIES` com os CIDRs exatos da tua
  infraestrutura — nunca uses `0.0.0.0/0`;
- Roda a chave de assinatura de `LocationToken`
  (`ANGOLA_GEOGUARD_TOKEN_KEY`) periodicamente;
- Nao exponhas `reason_code` detalhado em respostas publicas — usa
  `GeoAccessDecision::publicMessage()`.
