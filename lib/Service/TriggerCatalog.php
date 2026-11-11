<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * A capability catalog, not a dump of every PHP event or installed app.
 * Applications appear only when they have an implemented adapter and are
 * enabled. Future integrations can register bounded capabilities via Event.
 */
namespace OCA\Communications\Service;

use OCA\Communications\Event\RegisterTriggerCapabilitiesEvent;
use OCP\App\IAppManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\INavigationManager;

final class TriggerCatalog {
 public function __construct(
  private IAppManager $apps,
  private IEventDispatcher $dispatcher,
  private IAppConfig $config,
  private INavigationManager $navigation,
  private WebRouteCatalog $webRoutes,
  private NativeEventCatalog $nativeEvents,
  private GatewayTransport $gatewayTransport,
 ) {}

 public function intents(): array {
  return [
   ['id'=>'app', 'label'=>'Em um aplicativo', 'description'=>'Ação realizada ou visita a uma página do aplicativo', 'icon'=>'application-outline'],
   ['id'=>'schedule', 'label'=>'Em uma data ou período', 'description'=>'Durante uma janela de publicação', 'icon'=>'calendar-clock-outline'],
   ['id'=>'board', 'label'=>'Ao ativar a publicação', 'description'=>'Enviar os canais de saída ao ativar; banners ou janelas aparecem na próxima visita elegível', 'icon'=>'bulletin-board'],
   ['id'=>'advanced', 'label'=>'Regra avançada ou integração', 'description'=>'Usar condições do Flow ou um sistema externo', 'icon'=>'source-branch'],
  ];
 }

 public function sources(): array {
  $sources = [
   [
    'id'=>'files', 'appId'=>'files', 'intent'=>'action',
    'label'=>'Arquivos', 'description'=>'Criação, atualização ou exclusão no Files',
    'keywords'=>['arquivo','documento','enviar','upload','alterar','excluir','pasta'],
    'actions'=>[
     ['key'=>'nextcloud.files.created','label'=>'Arquivo criado','description'=>'Depois que alguém enviar ou criar um arquivo'],
     ['key'=>'nextcloud.files.updated','label'=>'Arquivo atualizado','description'=>'Depois de modificar um arquivo existente'],
     ['key'=>'nextcloud.files.deleted','label'=>'Arquivo excluído','description'=>'Depois de excluir um arquivo'],
    ],
   ],
   [
    'id'=>'sharing', 'appId'=>'files_sharing', 'intent'=>'action',
    'label'=>'Compartilhamentos', 'description'=>'Criação ou aceitação de compartilhamentos',
    'keywords'=>['share','compartilhar','aceitar','destinatário','permissão'],
    'actions'=>[
     ['key'=>'nextcloud.share.created','label'=>'Compartilhamento criado','description'=>'Quando uma pessoa compartilhar um item'],
     ['key'=>'nextcloud.share.accepted','label'=>'Compartilhamento aceito','description'=>'Quando uma pessoa aceitar um compartilhamento recebido'],
    ],
   ],
   [
    'id'=>'users', 'appId'=>'core', 'intent'=>'action',
    'label'=>'Acesso e contas', 'description'=>'Login de usuários autenticados',
    'keywords'=>['login','acesso','entrar','conta','boas-vindas'],
    'actions'=>[
     ['key'=>'nextcloud.user.login','label'=>'Login interativo','description'=>'Em cada entrada por formulário de login'],
     ['key'=>'nextcloud.user.first_login','label'=>'Primeiro login','description'=>'Quando uma conta entrar pela primeira vez'],
    ],
   ],
   [
    'id'=>'comments', 'appId'=>'comments', 'intent'=>'action',
    'label'=>'Comentários', 'description'=>'Mensagens criadas nos comentários do Nextcloud',
    'keywords'=>['comentário','comentar','mensagem'],
    'actions'=>[
     ['key'=>'nextcloud.comment.created','label'=>'Comentário publicado','description'=>'Depois que alguém publicar um comentário'],
    ],
   ],
   [
    'id'=>'navigation', 'appId'=>'core', 'intent'=>'visit',
    'label'=>'Navegação', 'description'=>'Mostrar ao entrar em um aplicativo compatível',
    'keywords'=>['abrir','entrar','visitar','files','dashboard','página','aplicativo'],
    'actions'=>[
     ['key'=>'nextcloud.page.opened','label'=>'Página de aplicativo aberta',
      'description'=>'Na entrada da área selecionada em “Onde apresentar”'],
    ],
   ],
   [
    'id'=>'board', 'appId'=>'communications', 'intent'=>'board',
    'label'=>'Publicação imediata', 'description'=>'Entregar a mensagem aos destinatários sem aguardar um evento técnico',
    'keywords'=>['mural','comunicado','anúncio','publicar','notícia','histórico','imediato'],
    'actions'=>[
     ['key'=>'nextcloud.board.published','label'=>'Publicação imediata',
      'description'=>'Envio imediato em fila, com banner/modal na próxima visita e Caixa de entrada opcional'],
    ],
   ],
   [
    'id'=>'scheduler', 'appId'=>'core', 'intent'=>'schedule',
    'label'=>'Agendamento', 'description'=>'Disponível durante o período de publicação',
    'keywords'=>['data','agendado','período','hora','programar'],
    'actions'=>[
     ['key'=>'nextcloud.time.window','label'=>'Publicação disponível no período',
      'description'=>'Na próxima visita elegível durante as datas configuradas'],
    ],
   ],
   [
    'id'=>'flow', 'appId'=>'workflowengine', 'intent'=>'advanced',
    'label'=>'Nextcloud Flow', 'description'=>'Condições avançadas sobre arquivos e ações',
    'keywords'=>['regra','fluxo','mime','tipo','extensão','pasta','condição','automação'],
    'actionUrl'=>'/index.php/settings/admin/workflow',
    'setup'=>'Salve a campanha e, no Flow, crie uma regra de arquivos com condições. Escolha a operação “Disparar campanha contextual” e selecione esta campanha pelo nome.',
    'actions'=>[
     ['key'=>'nextcloud.flow.matched','label'=>'Uma regra do Flow foi atendida',
      'description'=>'O Flow verifica os filtros; o aplicativo só controla a entrega'],
    ],
   ],
  ];
  if (strlen($this->config->getValueString('communications','webhook_secret','')) >= 32) {
   $sources[] = [
    'id'=>'webhook','appId'=>'communications','intent'=>'advanced',
    'label'=>'Webhook externo', 'description'=>'Um sistema autorizado informa um evento para um usuário específico',
    'keywords'=>['api','webhook','integração','externo','mautic','sistema'],
    'setup'=>'A integração envia uma chamada HMAC com campaignId (identificador DESTA campanha), uid (UM usuário do Nextcloud) e eventId (identificador único). Só esta campanha e este usuário recebem a ocorrência; as regras de público e frequência ainda são aplicadas. Não há disparo para todos os usuários. Consulte docs/API.md.',
    'actions'=>[
     ['key'=>'external.webhook','label'=>'Evento HMAC de campanha e usuário',
      'description'=>'Registrar a ocorrência apenas para campaignId e uid indicados no webhook'],
    ],
   ];
  }

  // Generic typed events are indexed only when an admin opens the catalog.
  // These actions target the authenticated principal, never an inferred signer.
  $nativeByApp = [];
  foreach ($this->nativeEvents->all() as $native) {
   $app = $native['appId'];
   if (!isset($nativeByApp[$app])) {
    $info = $app === 'core' ? null : $this->apps->getAppInfo($app);
    $appLabel = is_array($info) && is_string($info['name'] ?? null) ? $info['name'] : $app;
    $nativeByApp[$app] = [
     'id'=>'auto_' . $app, 'appId'=>$app, 'intent'=>'action',
     'label'=>$appLabel, 'description'=>'Eventos PHP tipados descobertos automaticamente',
     'keywords'=>['evento','php','automático','eventdispatcher'],
     'actions'=>[],
    ];
   }
   if (count($nativeByApp[$app]['actions']) >= 60) { continue; }
   $nativeByApp[$app]['actions'][] = [
    'key'=>$native['key'], 'label'=>$native['label'],
    'description'=>$native['description'],
    'eventClass'=>$native['class'], 'phase'=>$native['phase'],
    'webhookCompatible'=>$native['webhook'],
   ];
  }
  foreach ($nativeByApp as $source) {
   if ($source['actions'] !== []) { $sources[] = $source; }
  }

  // Optional extension point for native apps that publish an explicit
  // recipient/semantic contract; basic event discovery needs no adapter.
  $event = new RegisterTriggerCapabilitiesEvent();
  $this->dispatcher->dispatchTyped($event);
  foreach ($event->getSources() as $external) {
   if (!$this->validateExternal($external)) { continue; }
   $sources[] = $external;
  }

  $known = [];
  $filtered = [];
  foreach ($sources as $source) {
   $app = $source['appId'];
   if ($app !== 'core' && !$this->apps->isEnabledForAnyone($app)) { continue; }
   // Avoid spoofing built-in keys and duplicates from different providers.
   $validActions = [];
   foreach ($source['actions'] as $action) {
    $key = $action['key'];
    if (isset($known[$key])) { continue; }
    $known[$key] = true;
    $validActions[] = $action;
   }
   if ($validActions === []) { continue; }
   $source['actions'] = $validActions;
   $filtered[] = $source;
  }
  return $filtered;
 }

 private function validateExternal(array $source): bool {
  foreach (['id','appId','intent','label','description','actions'] as $key) {
   if (!isset($source[$key])) { return false; }
  }
  if (!is_string($source['id']) || !preg_match('/^[a-z0-9_-]{2,40}$/', $source['id'])) { return false; }
  if (!is_string($source['appId']) || !preg_match('/^[a-z0-9_-]{2,40}$/', $source['appId'])) { return false; }
  if (!in_array($source['intent'], ['action','visit','schedule','advanced'], true)) { return false; }
  if (!is_string($source['label']) || mb_strlen($source['label']) > 80
      || !is_string($source['description']) || mb_strlen($source['description']) > 240) { return false; }
  if (!is_array($source['actions']) || count($source['actions']) === 0 || count($source['actions']) > 30) { return false; }
  foreach ($source['actions'] as $action) {
   if (!is_array($action) || !isset($action['key'],$action['label'],$action['description'])
       || !is_string($action['key']) || !preg_match('/^' . preg_quote($source['appId'], '/') . '\.[a-z0-9_.-]{1,40}$/', $action['key'])
       || !is_string($action['label']) || mb_strlen($action['label']) > 80
       || !is_string($action['description']) || mb_strlen($action['description']) > 240) { return false; }
  }
  return true;
 }

 /**
  * Installed and navigable applications only. Never suggest a server-only
  * app as a landing page. The app ID is also used as the browser context.
  */
 public function placements(): array {
  $options = [
   ['value'=>'any','label'=>'Qualquer página compatível'],
   ['value'=>'settings','label'=>'Configurações pessoais'],
   ['value'=>'admin_settings','label'=>'Configurações de administração'],
  ];
  $seen = ['any'=>true,'settings'=>true,'admin_settings'=>true];
  $navIcons = [];
  foreach ($this->navigation->getAll() as $item) {
   $href = $item['href'] ?? '';
   if (!is_string($href)) { continue; }
   if (!preg_match('~/(?:index\.php/)?apps/([a-z0-9_-]{2,32})(?:/|$)~', $href, $matches)) {
    continue;
   }
   $app = $matches[1];
   $navIcons[$app] = $item['icon'] ?? null;
   // Campaigns are not presented over their own administrative editor.
   if ($app === 'communications' || isset($seen[$app]) || !$this->apps->isEnabledForAnyone($app)) { continue; }
   if ($this->webRoutes->routesFor($app) === []) { continue; }
   $seen[$app] = true;
   $options[] = ['value'=>$app,'label'=>is_string($item['name'] ?? null) ? $item['name'] : $app,
    'icon'=>$this->safeIcon($navIcons[$app] ?? $this->apps->getAppIcon($app))];
  }
  // Discover every enabled app declaring navigation in its installed info.xml,
  // even when an app's navigation entry is not loaded for this request.
  // Do not promote a server-only app to a UI merely because it is installed.
  foreach ($this->apps->getEnabledApps() as $app) {
   if ($app === 'communications' || isset($seen[$app]) || !$this->apps->isEnabledForAnyone($app)) { continue; }
   $info = $this->apps->getAppInfo($app);
   if (empty($info['navigations']['navigation']) || $this->webRoutes->routesFor($app) === []) { continue; }
   $seen[$app] = true;
   $options[] = [
    'value'=>$app,
    'label'=>is_string($info['name'] ?? null) ? $info['name'] : $app,
    'icon'=>$this->safeIcon($this->apps->getAppIcon($app)),
   ];
  }
  usort($options, static function (array $a, array $b): int {
   return ($a['value'] === 'any' ? -1 : ($b['value'] === 'any' ? 1 : 0))
       ?: strcasecmp($a['label'], $b['label']);
  });
  return $options;
 }

 /**
  * Configurable applications include event adapters and real navigable GUIs.
  * An installed app without an event adapter can still offer page visits.
  * Applications that only expose APIs are not page origins.
  */
 private function safeIcon(?string $url): ?string {
  // Never expose a third-party tracking image from an untrusted navigation entry.
  return $url !== null && preg_match('~^/(?!/)[^\\\\?&#<>"\x00-\x1f]+\\.(?:svg|png)$~i', $url) ? $url : null;
 }

 public function applications(): array {
  $byApp = [];
  foreach ($this->sources() as $source) {
   if ($source['intent'] !== 'action') { continue; }
   $id = $source['appId'];
   if (!isset($byApp[$id])) {
    $byApp[$id] = [
     'id'=>$id, 'label'=>$source['label'],
     'icon'=>$this->safeIcon($this->apps->getAppIcon($id)),
     'description'=>$source['description'],
     'keywords'=>$source['keywords'] ?? [],
     'canEvents'=>true, 'canPages'=>false,
     'actions'=>$source['actions'],
    ];
   } else {
    $byApp[$id]['canEvents'] = true;
    $byApp[$id]['actions'] = array_merge($byApp[$id]['actions'], $source['actions']);
   }
  }
  foreach ($this->placements() as $placement) {
   $id = $placement['value'];
   if (in_array($id,['any','settings','admin_settings'],true)) { continue; }
   if (isset($byApp[$id])) {
    $byApp[$id]['canPages'] = true;
    $byApp[$id]['icon'] = $placement['icon'] ?? $byApp[$id]['icon'];
   } else {
    $byApp[$id] = [
     'id'=>$id, 'label'=>$placement['label'],
     'icon'=>$placement['icon'] ?? null,
     'description'=>'Páginas acessíveis na navegação do Nextcloud',
     'keywords'=>['página','rota','acessar','abrir'],
     'canEvents'=>false, 'canPages'=>true, 'actions'=>[],
    ];
   }
  }
  $apps = array_values($byApp);
  usort($apps, static fn (array $a,array $b):int => strcasecmp($a['label'],$b['label']));
  return $apps;
 }

 /**
  * Real adapter readiness versus app installation. Returning a capability
  * never enables an unimplemented external delivery adapter.
  */
 public function deliveryChannels(): array {
  $notifications = $this->apps->isEnabledForAnyone('notifications');
  $activity = $this->apps->isEnabledForAnyone('activity');
  $gateway = $this->gatewayTransport->installed();
  $sources = [
   [
    'id'=>'notifications', 'label'=>'Notificações do Nextcloud',
    'installed'=>$notifications, 'ready'=>$notifications,
    'availability'=>$notifications ? 'ready' : 'missing_app',
    'status'=>$notifications ? 'Disponível' : 'Não instalado',
    'help'=>$notifications
     ? 'Envia um aviso pelo sininho do Nextcloud a cada destinatário autorizado, com link de leitura restrito, sem exigir uma cópia na Caixa de entrada.'
     : 'O aplicativo Notifications não está habilitado neste Nextcloud. Um administrador precisa instalar e ativar Notifications em Aplicativos antes de usar o sininho. O serviço notify_push, se existir, não substitui o aplicativo Notifications.',
   ],
   [
    'id'=>'activity', 'label'=>'Activity do Nextcloud',
    'installed'=>$activity, 'ready'=>$activity,
    'availability'=>$activity ? 'ready' : 'missing_app',
    'status'=>$activity ? 'Disponível' : 'Não instalado',
    'help'=>$activity
     ? 'Registra uma atividade para o destinatário autorizado, com link de detalhe restrito. Não exige cópia na Caixa de entrada.'
     : 'O aplicativo Activity não está habilitado neste Nextcloud. É preciso instalar e ativar Activity em Aplicativos antes de registrar avisos nesse canal.',
   ],
   [
    'id'=>'email', 'label'=>'E-mail',
    'installed'=>true, 'ready'=>true, 'availability'=>'requires_configuration',
    'status'=>'Verificar SMTP',
    'help'=>'O adaptador de e-mail já está implementado. O envio real exige um servidor de e-mail configurado pelo administrador do Nextcloud e endereços válidos dos destinatários. A disponibilidade de SMTP não é verificada por este catálogo.',
   ],
   [
    'id'=>'twofactor_gateway','label'=>'Two-Factor Gateway',
    'installed'=>$gateway,'ready'=>false,
    'availability'=>$gateway ? 'configured_per_provider' : 'missing_app',
    'status'=>$gateway ? 'Ver canais' : 'Não instalado',
    'help'=>$gateway
     ? 'Integração nativa com o serviço direto do Two-Factor Gateway, como integração nativa. Configure os provedores e solicite a cada destinatário adesão explícita nas Preferências de avisos.'
     : 'Instale e configure o Two-Factor Gateway para utilizar SMS, Signal, Telegram, WhatsApp ou XMPP. O envio depende do provedor e de consentimento do destinatário.',
   ],
  ];
  foreach (GatewayTransport::LABELS as $id=>$label) {
   $ready=$this->gatewayTransport->configured($id);
   $sources[]=[
    'id'=>'gateway:'.$id,'label'=>$label,
    'installed'=>$gateway,'ready'=>$ready,
    'availability'=>!$gateway ? 'missing_app' : ($ready ? 'ready' : 'missing_configuration'),
    'status'=>!$gateway ? 'Não instalado' : ($ready ? 'Disponível' : 'Não configurado'),
    'help'=>!$gateway
     ? 'O aplicativo Two-Factor Gateway precisa estar instalado. Este canal permanece inativo e não envia mensagens.'
     : ($ready
       ? 'Gateway direto configurado. Cada destinatário ainda precisa informar seu próprio identificador e autorizar este canal em Caixa de entrada → Preferências de avisos; publicidade requer autorização adicional.'
       : 'Este provedor não possui um gateway direto completo configurado no Two-Factor Gateway. Configure-o em Administração → Segurança. Só depois será possível selecioná-lo.'),
   ];
  }
  return $sources;
 }

 public function supportsPlacement(string $placement): bool {
  foreach ($this->placements() as $page) {
   if ($page['value'] === $placement) { return true; }
  }
  return false;
 }

 public function supports(string $key): bool {
  foreach ($this->sources() as $source) {
   foreach ($source['actions'] as $action) {
    if ($action['key'] === $key) { return true; }
   }
  }
  return false;
 }

 public function legacyTriggers(): array {
  $out = [];
  foreach ($this->sources() as $source) {
   foreach ($source['actions'] as $action) {
    $out[] = ['key'=>$action['key'], 'label'=>$action['label']];
   }
  }
  return $out;
 }
}
