<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
# Formas de entrega independentes (Comunicações 0.28.0)

A mensagem, o público, a vigência, a apresentação e os canais de entrega
pertencem à mesma publicação. A Caixa de entrada é opcional: não é mais uma
dependência para o envio por outros canais.

## Como usar

Na etapa **Formas de entrega**, todos os destinos aparecem juntos. Selecione
um ou vários controles, inclusive banner contextual, janela modal, Caixa de
entrada, Notificações do Nextcloud, feed de atividades (Activity), e-mail e
provedores de mensageria/SMS do Two-Factor Gateway.

**Banner e janela modal são mutuamente exclusivos.** Ativar um desmarca o
outro, mantendo os outros canais previamente escolhidos. Os demais destinos
podem ser combinados livremente. A Caixa de entrada é um destino opcional
como qualquer outro, não uma consequência do e-mail ou de Notifications.

O catálogo distingue instalado/indisponível, mostra instruções por canal
e não permite selecionar um provedor externo não configurado. Se nenhum
destino for selecionado, o editor não permite avançar. As configurações
visuais só aparecem quando o formato visual correspondente está ativo.

A prévia do banner ou modal testa o componente real sem enviar mensagens;
sem destino visual, a prévia apresenta somente o conteúdo, pois cada
provedor de saída possui formatação própria.

## Combinações aceitas

| Objetivo | Exibição | Caixa de entrada | Canais |
| --- | --- | --- | --- |
| Somente e-mail | Nenhuma | Não | E-mail |
| Somente sininho | Nenhuma | Não | Notifications |
| Somente WhatsApp | Nenhuma | Não | Two-Factor Gateway: WhatsApp |
| Somente mural | Nenhuma | Sim | Nenhum |
| Banner e e-mail | Banner contextual | Opcional | E-mail |
| Modal e Telegram | Janela modal | Opcional | Telegram |
| Envio agendado sem tela | Nenhuma | Não | Um ou mais canais |
| Mural + canais múltiplos | Caixa de entrada | Sim | Qualquer combinação |

O editor das novas publicações usa `presentation=none` para entregas
imediatas sem banner ou janela. O backend continua aceitando
`presentation=board` de publicações antigas, sem migração destrutiva.

**Publicação imediata com banner ou modal:** os envios em fila ocorrem
na ativação; a visualização no Nextcloud ocorre na próxima visita elegível
do destinatário. Ao selecionar o primeiro destino visual numa publicação
imediata, o editor escolhe como frequência inicial uma exibição por usuário,
que pode ser ajustada posteriormente. Banners e janelas não são mensagens push em tempo real.

**Gatilhos por eventos individuais:** continuam precisando de banner ou
janela modal. Canais de saída selecionados nesse cenário são enviados na
ativação da campanha, não repetidamente a cada evento; a interface
informa explicitamente esta diferença de momento.

Uma campanha sem apresentação visual, sem Caixa de entrada e sem canais
selecionados é inválida e retorna HTTP 422. Banners para visitantes anônimos
não podem ser combinados com avisos individualizados.

## Momento do envio

A publicação sem banner ou janela pode ser imediata, ao ativar, ou agendada
por data/período. Não pode transformar um evento individual (arquivo, usuário,
navegação, Flow ou webhook) em um disparo em massa ao ativar a campanha.

O serviço BoardPublicationService processa publicações ativas mesmo quando
board_visible é falso, caso exista um canal configurado. O cron coloca os
destinatários na fila em lotes, verificando vigência, segmentação, opt-out
e consentimento. A chave de outbox continua sendo composta pela publicação,
ativação, usuário e canal. O envio não se repete por uma nova exibição de
banner ou modal. Reativar uma publicação inicia outra rodada.

O evento AnnouncementPublished só ocorre quando a publicação é destinada
à Caixa de entrada. Isso impede webhooks de publicação no mural para
campanhas que não possuem mural.

## Acesso aos links recebidos

Uma mensagem enviada exclusivamente por e-mail, Notifications, Activity ou
gateway continua armazenada como registro editorial, mas **não aparece na
lista da Caixa de entrada**. O link recebido abre um detalhe autenticado e
autorizado, com validação de destinatário, estado e vigência. É necessário
haver uma entrega correspondente no outbox como enviada ou em processamento.
Uma pessoa fora do público não pode abrir o detalhe adivinhando o ID.
Não há histórico nem marcação de leitura na Caixa de entrada para esses casos.

Os links de canal não substituem a interface de apresentação. Uma prévia
genérica não comprova a formatação real de e-mail, SMS ou mensageiros.

## Dependências

- Notifications é o aplicativo nativo que mostra o sininho; notify_push não
  é um substituto para o aplicativo Notifications.
- Activity precisa do aplicativo Activity habilitado.
- E-mail exige servidor SMTP configurado e endereços válidos. O catálogo
  não comprova que o SMTP está operacional.
- Two-Factor Gateway exige um provedor direto configurado e consentimento
  por destinatário; dados de autenticação em dois fatores não são reutilizados.
  Conteúdo patrocinado requer consentimento específico.
  Consulte TWO_FACTOR_GATEWAY.md.

Os transportes externos podem falhar ou aceitar uma mensagem sem devolver
confirmação à fila. Há retentativas e proteção contra duplicidade comum,
mas não há promessa de exatamente uma entrega externa.

## Homologação NC36

- test_channel_only_v27.py testa a API e a fila com transportes simulados,
  acessos autorizados, ausência de cópia, revogação, deduplicação e agendamento.
- test_channel_only_ui_v27.cjs verifica o editor em desktop e mobile,
  canais exclusivos, prévia, revisão e persistência sem envio real.
- Testes legados test_unified_delivery_v23.cjs e test_gateway_ui_v26.cjs
  foram atualizados para verificar o Inbox opcional. As regressões de
  publicações e de integrações permanecem.
- O laboratório contém o Two-Factor Gateway instalado, mas sem provedores
  externos configurados, portanto não há homologação de entrega real.

Estas alterações pertencem apenas à POC de laboratório. Não houve deploy
em servidores de produção nem publicação no GitHub.
