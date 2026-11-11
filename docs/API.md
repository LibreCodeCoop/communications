<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
# API local — Contextual Campaigns 0.11.0

**Contrato experimental; não estabilizado para integrações externas.** Instância isolada com Nextcloud 36 master. Todas as URLs são relativas a `/index.php/apps/contextualcampaigns`.

## Autenticação e público

- Rotas administrativas requerem usuário **administrador** com sessão autenticada Nextcloud.
- Rotas de entrega requerem sessão de usuário Nextcloud; as escritas exigem token CSRF (`requesttoken`) do próprio usuário.
- Rotas não aceitam que o navegador forneça o UID. O backend deriva o destinatário a partir da sessão.
- Única rota pública: `POST /api/hooks/event`, protegida por HMAC SHA-256 sobre o corpo original.
- Não incluir credenciais de API, segredo HMAC ou identificadores de documentos dentro de campanhas.

## Catálogo orientado à intenção

`GET /api/catalog` retorna `automation.intents` (**3 opções**), `automation.sources` (adaptadores confiáveis de eventos), `automation.applications` (união de origens de eventos com aplicativos que possuem interface navegável) e `automation.placements` (locais de exibição). A lista `triggers` permanece por compatibilidade com campanhas anteriores; não deve ser usada como seletor único de centenas de eventos.

Exemplo de parte da resposta:

```json
{
  "automation": {
    "intents": [{"id":"app","label":"Em um aplicativo","description":"Ação realizada ou visita a uma página do aplicativo"}],
    "sources": [{"id":"files","appId":"files","intent":"action","label":"Arquivos",
      "actions":[{"key":"nextcloud.files.created","label":"Arquivo criado","description":"Depois que alguém enviar ou criar um arquivo"}]}],
    "applications": [{"id":"files","label":"Arquivos","canEvents":true,"canPages":true,
      "actions":[{"key":"nextcloud.files.created","label":"Arquivo criado","description":"Depois que alguém enviar ou criar um arquivo"}]},
      {"id":"dashboard","label":"Dashboard","canEvents":false,"canPages":true,"actions":[]}],
    "placements": [{"value":"any","label":"Qualquer página compatível"},{"value":"files","label":"Arquivos (Files)"}]
  }
}
```

Para listar as páginas de um aplicativo, o administrador consulta:

```text
GET /index.php/apps/contextualcampaigns/api/routes?app=files
```

Exemplo de resposta (padrões de rota, não URLs reais dos usuários):

```json
{
  "app": "files",
  "routes": [
    {"value": "/apps/files/", "label": "Página principal", "description": "/apps/files/"},
    {"value": "/apps/files/{view}", "label": "Página /{view}", "description": "/apps/files/{view}"},
    {"value": "/apps/files/{view}/{fileid}", "label": "Página /{view}/{fileid}", "description": "/apps/files/{view}/{fileid}"}
  ]
}
```

O retorno pode variar conforme os aplicativos e a versão do Nextcloud. Não inclui `/apps/files/api/v1/stats` nem endpoints OCS. **Não use este catálogo como substituto de um teste de renderização HTML; a classificação por rotas é heurística.**

Fontes com mais eventos são agrupadas por aplicativo. Para aparecer como **origem de evento**, basta oferecer classe PHP de evento descoberta automaticamente ou um adaptador semântico opcional; para aparecer como **origem de visita**, precisa ter uma página reconhecida na navegação da instância. O Dashboard pode, portanto, aparecer sem adaptador de eventos. Provedores de eventos registram metadados via `RegisterTriggerCapabilitiesEvent` e ocorrências confiáveis via `EventStore::record()`. Consulte [AUTOMATION.md](AUTOMATION.md).

O painel oferece busca por nome, descrição, palavras-chave e ações, limitada a 12 sugestões por vez. `placement` é validado contra a navegação disponível; `page_route`, quando informado, deve corresponder exatamente a um padrão de rota web GET disponibilizado pelo servidor. A descoberta das rotas é heurística e usa uma interface interna do Nextcloud, portanto precisa ser revalidada nas atualizações do servidor.

## Administração

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/catalog` | Catálogo de opções, fontes de eventos, aplicativos navegáveis e locais disponíveis |
| GET | `/api/routes?app=files` | Rotas web GET selecionáveis do aplicativo, filtradas no servidor |
| GET | `/api/users?search=...` | Pesquisa limitada a administradores |
| GET | `/api/campaigns` | Listar campanhas, incluindo arquivadas, até 500 |
| GET | `/api/campaigns/{id}` | Ler campanha |
| POST | `/api/campaigns` | Criar campanha (201) |
| PUT | `/api/campaigns/{id}` | Atualizar campos (200) |
| POST | `/api/campaigns/{id}/status` | Ativar, pausar, rascunho, arquivar |
| POST | `/api/campaigns/{id}/duplicate` | Duplicar em rascunho (201) |
| DELETE | `/api/campaigns/{id}` | Arquivar sem excluir o registro |
| GET | `/api/campaigns/{id}/metrics` | Contadores agregados, não auditados |

### Campanha de exemplo

```json
{
  "name": "Orientações após compartilhar",
  "description": "Campanha interna de orientação.",
  "headline": "Compartilhamento realizado",
  "message": "**Sua nuvem, suas regras.**\n\n- Verifique os destinatários.\n- Revise as permissões.",
  "cta_label": "Abrir arquivos",
  "cta_url": "/index.php/apps/files/files",
  "trigger_key": "nextcloud.share.created",
  "audience_type": "group",
  "audience_groups": ["team-a", "team-b"],
  "audience_users": [],
  "group_match": "any",
  "excluded_groups": ["no-promotions"],
  "placement": "files",
  "page_route": "",
  "presentation": "banner",
  "status": "draft",
  "priority": 25,
  "starts_at": null,
  "ends_at": null,
  "frequency": "weekly",
  "min_interval_minutes": 0,
  "max_per_day": 1,
  "max_total": 4,
  "category": "institutional",
  "theme": "default"
}
```

Os campos `audience_groups` e `audience_users` são arrays de identificadores. Na modalidade `group`, o critério `group_match=any` representa OU e `all` representa E. `excluded_groups` tem prioridade sobre as inclusões. `audience_group` singular permanece aceito como compatibilidade legado.

`trigger_key` é um dos identificadores do catálogo. `placement` é `any`, `settings` ou o ID de um aplicativo navegável como `files` e `dashboard`. Para `trigger_key=nextcloud.page.opened`, **`placement` identifica o aplicativo visitado** e `page_route` opcional especifica um padrão retornado por `/api/routes?app=<id>`, por exemplo `/apps/files/{view}`. `page_route=""` significa qualquer página desse aplicativo, não qualquer página do Nextcloud; URLs concretas, caminhos de API e rotas externas não são aceitos. Para os demais gatilhos, `page_route` fica vazio. `frequency`: `event`, `once`, `daily`, `weekly`, `custom`. Quando `custom`, `min_interval_minutes` informa o limite inferior. `max_per_day` conta exposições nas últimas 24 horas, não no dia civil. `max_total=0` e `max_per_day=0` significam ilimitado, exceto conteúdo patrocinado, que recebe limite conservador de duas exposições/24h se não configurado.

`category`: `institutional` ou `sponsored`. Conteúdo patrocinado é identificado visualmente e admite preferência de ocultação por usuário. `theme`: `default`, `primary` ou `warning`.

`starts_at`/`ends_at` são timestamps Unix ou null; as datas são avaliadas no servidor. `published_at` é calculado e mantido pelo backend: só eventos posteriores à ativação entram na entrega.

**Validações:** títulos e mensagem obrigatórios, HTTPS ou rota interna no CTA, grupo/usuário existente, enumerações e limites restritos, intervalo temporal válido. Campos inválidos respondem HTTP 422.

## Entrega e reconhecimento

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/claim` | Reservar uma entrega para a aba atual |
| POST | `/api/impression` | Informar montagem visual de uma entrega reservada |
| POST | `/api/ack` | Informar dispensa/fechamento |
| POST | `/api/click` | Informar clique no CTA |
| GET | `/api/pending?page=files&path=/index.php/apps/files/recent` | Consulta diagnóstica sem reservar; caminhos web restritos são validados |
| GET | `/api/preferences` | Ler preferência de publicidade |
| POST | `/api/preferences` | Atualizar preferência |

**Reserva:**

```json
{"page": "files", "path": "/index.php/apps/files/recent", "newVisit": true}
```

`path` é o caminho de navegação do navegador, **sem query string nem fragmento**. Para campanhas `nextcloud.page.opened`, somente visitas a rotas web válidas podem criar uma ocorrência. A API de `claim` recebe o caminho do cliente; portanto, o evento de visita **não constitui prova auditável de navegação humana**, e não pode ser usado isoladamente para faturamento ou antifraude.

**Resposta:**

```json
{
  "campaign": {
    "campaignId": 123,
    "deliveryId": 456,
    "eventId": "be3a5a40-4185-52d3-13ce-8045131e8e12",
    "claimToken": "<token temporário>",
    "title": "Comunicado",
    "message": "Conteúdo formatado em Markdown.",
    "presentation": "banner",
    "ctaLabel": "Saiba mais",
    "ctaUrl": "/index.php/apps/files/files",
    "category": "institutional",
    "theme": "default",
    "priority": 10
  }
}
```

Quando não houver nada elegível, retorna `{"campaign":null}`.

A reserva dura **90 segundos** e não pode ser usada por outro usuário ou outra campanha. Reservas de eventos distintos da mesma campanha para o mesmo usuário também são coordenadas. Um frontend deve apresentar o item recebido, enviar `/api/impression` e, no fechamento ou clique, `/api/ack` ou `/api/click`:

```json
{"deliveryId": 456, "claimToken": "<token temporário>"}
```

Se a pessoa passar mais de 90 segundos lendo um conteúdo já marcado como apresentado, a dispensa e o clique ainda são aceitos usando o token original, desde que o registro continue com aquele token. O reconhecimento incorreto retorna HTTP 409. Respostas repetidas de impressão e dispensa são idempotentes. Conteúdos em `/api/pending` ainda não contam como impressões.

**Importante:** `/api/impression` é uma informação fornecida pelo navegador; não demonstra que houve atenção, leitura humana ou veiculação faturável. Não use essas contagens para cobrança até implementar detecção de visibilidade, controle contra fraude e auditoria independente.

Preferência para ocultar publicidade patrocinada:

```json
{"sponsoredOptout": true}
```

Retorno de `GET /api/preferences`: `{"sponsoredOptout":true|false}`.

## Webhook público autenticado

`POST /api/hooks/event`.

- `Content-Type: application/json`
- `X-CC-Timestamp: <segundos Unix>`
- `X-CC-Signature: sha256=<HMAC-SHA256 em hexadecimal>`
- A string assinada é `timestamp + "." + corpo_json_bruto`.
- Tolerância máxima de relógio: 300 segundos.
- O segredo `webhook_secret` deve ter pelo menos 32 caracteres e ser configurado no servidor em chave sensível de aplicativo; nunca transportá-lo no navegador.
- O JSON exige **`campaignId`**, **`uid`** e **`eventId`**. `campaignId` identifica uma campanha ativa configurada para `external.webhook`. `uid` identifica **uma única conta do Nextcloud**; a integração não pode indicar um grupo ou todos os usuários em uma chamada. Não inclui conteúdo de arquivo. O público-alvo e os limites de frequência continuam sendo validados pelo servidor.
- O `eventId` externo é deduplicado **por campanha e usuário**. Identificador repetido para o mesmo `campaignId`/`uid` retorna a mesma ocorrência; a mesma chamada não dispara outras campanhas.
- HTTP 202 aceito, 401 assinatura inválida, 422 usuário/evento inválido, 503 webhook sem segredo configurado.

Exemplo de payload:

```json
{"campaignId":42,"uid":"example_user","eventId":"integration-2026-11-07-001"}
```

Este endpoint registra uma ocorrência `external.webhook` **exclusivamente** para a campanha e o UID informados. Uma chamada não aciona todas as campanhas com gatilho de webhook, nem envia a todos os usuários. A campanha precisa estar ativa; a apresentação ocorrerá apenas na próxima visita elegível do destinatário. O webhook **não autentica a identidade empresarial do emissor além do segredo HMAC comum**. Produção multi-inquilino precisa de credenciais por integração, autorização por escopo, revogação, rotação de chaves e rate limiting. Uma integração futura com LibreSign deve usar um evento confiável de assinatura concluída e não depender de mudanças de URL ou DOM.

## Integração Nextcloud Flow

A operação `Disparar campanha contextual` foi registrada no motor de operações do Nextcloud. Para usar:
1. Crie uma campanha com gatilho `nextcloud.flow.matched`.
2. Em Administração → Flow, configure uma regra sobre evento de arquivo, com ao menos uma condição e a operação deste aplicativo.
3. Selecione a campanha pelo **nome**, usando o componente `NcSelect` fornecido pelo aplicativo na própria interface Flow; não é necessário digitar o ID.
4. Um evento que cumprir a regra gera uma ocorrência vinculada exclusivamente àquele ID.

A classe implementa `ISpecificOperation`, escopo de administração e eventos reais de arquivo. **Uma regra real foi validada de ponta a ponta** com evento de criação de arquivo e condição MIME `text/plain`, upload WebDAV, ocorrência registrada e entrega disponível. O componente de seleção pelo nome também foi verificado na tela administrativa do Flow. A capacidade de construir fluxos com entidades de outros aplicativos depende de integrações específicas.

## Limitações atuais

- Contagem e limites por usuário; não existe cobrança por anunciante, medição auditada ou medição por tenant.
- Filtragem da fila usa eventos recentes de até 30 dias; o job de limpeza foi registrado, requer cron do Nextcloud ativo.
- Adaptação para signatários **convidados sem login** e eventos de assinatura LibreSign ainda não existe.
- Não há envio automático por Notifications/Announcement Center, integração de conteúdo com Mautic, traduções por campanha nem ACL delegada a anunciantes.
- Não inserir HTML arbitrário. O visual usa Markdown renderizado pelo `NcRichText`.
