<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Communications\Service;

use InvalidArgumentException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;

class CampaignRepository {
 public const TABLE = 'cc_campaigns';
 public const TRIGGERS = [
  'nextcloud.user.login' => 'Interactive login',
  'nextcloud.files.created' => 'File created (Files)',
  'nextcloud.files.updated' => 'File updated (Files)',
  'nextcloud.files.deleted' => 'File removed (Files)',
  'nextcloud.user.first_login' => 'First login',
  'nextcloud.share.created' => 'Share created',
  'nextcloud.share.accepted' => 'Share accepted',
  'nextcloud.comment.created' => 'Comment published',
  'nextcloud.page.opened' => 'Application page opened',
  'nextcloud.time.window' => 'Date-scheduled campaign',
  'nextcloud.flow.matched' => 'Nextcloud Flow rule matched',
  'external.webhook' => 'Authenticated external webhook',
  'nextcloud.board.published' => 'Persistent announcement on the board',
 ];
 public const STATUSES = ['draft', 'active', 'paused', 'archived'];
 public const PRESENTATIONS = ['banner', 'modal', 'board', 'none'];
 public const PLACEMENTS = ['any', 'files', 'dashboard', 'settings', 'admin_settings'];
 public const FREQUENCIES = ['event', 'once', 'daily', 'weekly', 'custom'];
 public const CATEGORIES = ['institutional', 'sponsored'];
 // Preserve legacy themes while supporting the four native NcNoteCard types.
 public const THEMES = ['default', 'primary', 'info', 'success', 'warning', 'error', 'neutral', 'custom'];
 public const BANNER_ICONS = [
  'auto','none','info','success','warning','error','shield',
  'megaphone','bullhorn','bell','bell-ring','alert','alert-circle','alert-octagon',
  'information','information-outline','check-circle','check-decagram',
  'close-circle','close-octagon','shield-alert','shield-check','lock',
  'wrench','progress-wrench','broom','server','server-off','database',
  'cloud','cloud-alert','wifi-off','sync','update','download','upload',
  'calendar','calendar-clock','clock-alert','email','flag','star',
  'gift','party-popper','rocket-launch','new-box','lightning-bolt','help-circle',
 ];
 private const MAX_TARGETS = 40;

 public function __construct(
  private IDBConnection $db,
  private IGroupManager $groupManager,
  private IUserManager $userManager,
  private TriggerCatalog $triggerCatalog,
  private WebRouteCatalog $webRoutes,
  private NativeEventSubscriptions $nativeSubscriptions,
  private \OCP\IL10N $l10n,
 ) {}

 /**
  * Stable keyset pagination. Historical records are never silently hidden
  * by an arbitrary 500-row cutoff.
  * @return \Generator<int,array>
  */
 public function scan():\Generator {
  $before=null;
  do {
   $qb=$this->db->getQueryBuilder();
   $qb->select('*')->from(self::TABLE)->orderBy('id','DESC')->setMaxResults(250);
   if ($before!==null) {
    $qb->where($qb->expr()->lt('id',$qb->createNamedParameter($before,IQueryBuilder::PARAM_INT)));
   }
   $rs=$qb->executeQuery();
   $rows=$rs->fetchAllAssociative();$rs->closeCursor();
   foreach ($rows as $row) {
    $before=(int)$row['id'];
    yield $this->castRow($row);
   }
  } while (count($rows)===250);
 }

 /** Legacy admin and CLI list. Prefer pagedList() for large UI datasets. */
 public function all():array {
  $rows=iterator_to_array($this->scan(),false);
  usort($rows,static fn(array $a,array $b):int =>
   ($b['updated_at'] <=> $a['updated_at']) ?: ($b['id']<=>$a['id']));
  return $rows;
 }

 /**
  * Strictly public, preapproved banner projection. Never include audience IDs,
  * group membership, creator, metrics, unpublished records or internal data.
  */
 public function publicForGuests(int $now):array {
  $qb=$this->db->getQueryBuilder();
  $rs=$qb->select('*')->from(self::TABLE)
   ->where($qb->expr()->eq('guest_visible',$qb->createNamedParameter(1,IQueryBuilder::PARAM_INT)))
   ->andWhere($qb->expr()->eq('status',$qb->createNamedParameter('active')))
   ->andWhere($qb->expr()->eq('presentation',$qb->createNamedParameter('banner')))
   ->andWhere($qb->expr()->eq('audience_type',$qb->createNamedParameter('all')))
   ->andWhere($qb->expr()->eq('trigger_key',$qb->createNamedParameter('nextcloud.time.window')))
   ->andWhere($qb->expr()->eq('category',$qb->createNamedParameter('institutional')))
   ->orderBy('priority','DESC')->addOrderBy('manual_order','ASC')
   ->setMaxResults(30)->executeQuery();
  $rows=$rs->fetchAllAssociative();$rs->closeCursor();
  $out=[];
  foreach ($rows as $row) {
   $c=$this->castRow($row);
   if($c['starts_at']!==null && $c['starts_at']>$now)continue;
   if($c['ends_at']!==null && $c['ends_at']<=$now)continue;
   if($c['placement']!=='any'
      || !in_array($c['app_target_mode'],['legacy','all'],true)
      || $c['board_visible'])continue;
   $out[]=[
    'id'=>$c['id'],'version'=>$c['updated_at'],
    'headline'=>$c['headline'],'message'=>$c['message'],
    'ctaLabel'=>$c['cta_label'],
    'ctaUrl'=>CtaUrl::isAllowed($c['cta_url'])?$c['cta_url']:'',
    'type'=>$c['theme'],'color'=>$c['banner_color'],
    'dismissible'=>$c['dismissible'],
    'startsAt'=>$c['starts_at'],'endsAt'=>$c['ends_at'],
   ];
   if(count($out)>=5)break;
  }
  return $out;
 }

 public function find(int $id): ?array {
  $qb = $this->db->getQueryBuilder();
  $result = $qb->select('*')->from(self::TABLE)
   ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
   ->executeQuery();
  $row = $result->fetchAssociative();
  $result->closeCursor();
  return $row === false ? null : $this->castRow($row);
 }

 public function activeForTrigger(string $trigger): array {
  $qb = $this->db->getQueryBuilder();
  $result = $qb->select('*')->from(self::TABLE)
   ->where($qb->expr()->eq('trigger_key', $qb->createNamedParameter($trigger)))
   ->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('active')))
   ->orderBy('priority', 'DESC')->addOrderBy('id', 'ASC')
   ->setMaxResults(100)->executeQuery();
  $rows = $result->fetchAllAssociative();
  $result->closeCursor();
  return array_map($this->castRow(...), $rows);
 }

 /**
  * Resolve editorial translations without exposing the full locale dictionary
  * to announcement readers. A missing locale falls back to the authored copy.
  */
 public static function localize(array $campaign, string $locale): array {
  $locale = str_replace('-', '_', $locale);
  $map = $campaign['translated_content'] ?? [];
  if (!is_array($map) || $map === []) { return $campaign; }
  $short = substr($locale,0,2);
  $choice = $map[$locale] ?? $map[$short] ?? null;
  if (!is_array($choice)) {
   foreach ($map as $name => $translation) {
    if (is_string($name) && str_starts_with($name, $short.'_') && is_array($translation)) {
     $choice = $translation;
     break;
    }
   }
  }
  if (!is_array($choice)) { return $campaign; }
  foreach (['headline','message','cta_label'] as $field) {
   if (is_string($choice[$field] ?? null) && trim($choice[$field]) !== '') {
    $campaign[$field] = $choice[$field];
   }
  }
  return $campaign;
 }

 public function isEligible(array $campaign, string $uid, int $now): bool {
  if ($campaign['status'] !== 'active') { return false; }
  if ($campaign['starts_at'] !== null && $campaign['starts_at'] > $now) { return false; }
  if ($campaign['ends_at'] !== null && $campaign['ends_at'] <= $now) { return false; }
  return $this->isAudienceMember($campaign, $uid);
 }

 /** Recheck current audience even for a previously delivered, expired item. */
 public function isAudienceMember(array $campaign,string $uid):bool {
  if ($campaign['excluded_groups'] !== []) {
   $excluded = ($campaign['excluded_match'] ?? 'any') === 'all'
    ? $this->inAllGroups($uid, $campaign['excluded_groups'])
    : $this->inAnyGroup($uid, $campaign['excluded_groups']);
   if ($excluded) { return false; }
  }
  return match ($campaign['audience_type']) {
   'all' => true,
   'admins' => $this->groupManager->isAdmin($uid),
   'group' => $campaign['group_match'] === 'all'
    ? $this->inAllGroups($uid, $campaign['audience_groups'])
    : $this->inAnyGroup($uid, $campaign['audience_groups']),
   'users' => in_array($uid, $campaign['audience_users'], true),
   default => false,
  };
 }

 private function inAllGroups(string $uid, array $groups): bool {
  if ($groups === []) { return false; }
  foreach ($groups as $groupId) {
   if (!$this->groupManager->isInGroup($uid, $groupId)) { return false; }
  }
  return true;
 }

 private function inAnyGroup(string $uid, array $groups): bool {
  foreach ($groups as $groupId) {
   if ($this->groupManager->isInGroup($uid, $groupId)) { return true; }
  }
  return false;
 }

 public function groupOptions(): array {
  $options = [];
  foreach ($this->groupManager->search('', 150) as $group) {
   $options[] = ['id' => $group->getGID(), 'name' => $group->getDisplayName()];
  }
  usort($options, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));
  return $options;
 }

 public function userOptions(string $search): array {
  $search = trim(mb_substr($search, 0, 80));
  if ($search === '') { return []; }
  $found = [];
  // Nextcloud searches UIDs and display names separately. Deduplicate by UID.
  foreach ([$this->userManager->search($search, 30), $this->userManager->searchDisplayName($search, 30)] as $users) {
   foreach ($users as $user) {
    $uid = $user->getUID();
    $found[$uid] = ['id' => $uid, 'name' => $user->getDisplayName()];
    if (count($found) >= 40) { break 2; }
   }
  }
  return array_values($found);
 }

 /**
  * Target IDs are validated on the server; the browser never decides authorization.
  * 'group' is accepted as a legacy single-group input for existing API consumers.
  */
 private function targets(array $payload, string $field, string $legacyField, bool $required): array {
  $values = $payload[$field] ?? null;
  if ($values === null && $legacyField !== '' && isset($payload[$legacyField])) {
   $legacy = $payload[$legacyField];
   $values = is_string($legacy) && trim($legacy) !== '' ? [trim($legacy)] : [];
  }
  if ($values === null) { $values = []; }
  if (!is_array($values) || !array_is_list($values) || count($values) > self::MAX_TARGETS) {
   throw new InvalidArgumentException($this->l10n->t('Invalid selection for ') . $field . '.');
  }
  $ids = [];
  foreach ($values as $value) {
   if (!is_string($value) || trim($value) !== $value || $value === '' || mb_strlen($value) > 64) {
    throw new InvalidArgumentException($this->l10n->t('Invalid identifier in ') . $field . '.');
   }
   $ids[$value] = $value;
  }
  if ($required && $ids === []) { throw new InvalidArgumentException($this->l10n->t('Select at least one recipient.')); }
  return array_values($ids);
 }

 public function validate(array $payload): array {
  $l10n = $this->l10n;
  $text = static function (string $key, int $max, bool $required = false) use ($payload, $l10n): string {
   $value = $payload[$key] ?? '';
   if (!is_string($value)) { throw new InvalidArgumentException($l10n->t('Invalid field: ') . $key); }
   $value = trim($value);
   if (($required && $value === '') || mb_strlen($value) > $max) {
    throw new InvalidArgumentException($l10n->t('Review the field ') . $key . $l10n->t(' (maximum ') . $max . $l10n->t(' characters).'));
   }
   return $value;
  };
  $name = $text('name', 160, true);
  $description = $text('description', 700);
  $headline = $text('headline', 180, true);
  $message = $text('message', 4000, true);
  $ctaLabel = $text('cta_label', 80);
  $ctaUrl = $text('cta_url', 1900);
  if (($ctaLabel === '') !== ($ctaUrl === '')) {
   throw new InvalidArgumentException($this->l10n->t('Enter both button text and URL.'));
  }
  if ($ctaUrl !== '' && !CtaUrl::isAllowed($ctaUrl)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid link. Use HTTP(S), mailto:, tel: or a safe internal path.'));
  }
  $trigger = $text('trigger_key', 64, true);
  if (!$this->triggerCatalog->supports($trigger)) { throw new InvalidArgumentException($this->l10n->t('Trigger unavailable. Verify that the integration is enabled.')); }
  $presentation = $text('presentation', 12, true);
  if (!in_array($presentation, self::PRESENTATIONS, true)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid presentation format.'));
  }
  $audience = $text('audience_type', 20, true);
  if (!in_array($audience, ['all', 'admins', 'group', 'users'], true)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid target audience.'));
  }
  $groups = $audience === 'group'
   ? $this->targets($payload, 'audience_groups', 'audience_group', true) : [];
  $users = $audience === 'users'
   ? $this->targets($payload, 'audience_users', '', true) : [];
  foreach ($groups as $group) {
   if ($this->groupManager->get($group) === null) {
    throw new InvalidArgumentException($this->l10n->t('Selected group does not exist: ') . $group);
   }
  }
  foreach ($users as $uid) {
   if ($this->userManager->get($uid) === null) {
    throw new InvalidArgumentException($this->l10n->t('Selected user does not exist.'));
   }
  }
  $excluded = $this->targets($payload, 'excluded_groups', '', false);
  foreach ($excluded as $groupId) {
   if ($this->groupManager->get($groupId) === null) {
    throw new InvalidArgumentException($this->l10n->t('An excluded group does not exist.'));
   }
  }
  $groupMatch = $text('group_match', 8) ?: 'any';
  if (!in_array($groupMatch, ['any', 'all'], true)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid group matching criteria.'));
  }
  $excludedMatch = $text('excluded_match', 8) ?: 'any';
  if (!in_array($excludedMatch, ['any', 'all'], true)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid group exclusion criteria.'));
  }
  $placement = $text('placement', 32) ?: 'any';
  if (!$this->triggerCatalog->supportsPlacement($placement)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid display location.'));
  }
  $appTargetMode = $text('app_target_mode', 12) ?: 'legacy';
  if (!in_array($appTargetMode, ['legacy','all','only','exclude'], true)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid application selection mode.'));
  }
  $appTargetApps = [];
  if ($trigger === 'nextcloud.page.opened' || in_array($presentation,['board','none'],true)) {
   $appTargetMode = 'legacy';
  } elseif (in_array($appTargetMode, ['only','exclude'], true)) {
   $appTargetApps = $this->targets($payload,'app_target_apps','',true);
   foreach ($appTargetApps as $appId) {
    if ($appId==='any' || !$this->triggerCatalog->supportsPlacement($appId)) {
     throw new InvalidArgumentException($this->l10n->t('Selected application is unavailable: ').$appId);
    }
   }
  }
  $pageRoute = $text('page_route', 255);
  $pageFilters = $payload['page_filters'] ?? [];
  if (!is_array($pageFilters)) { throw new InvalidArgumentException($this->l10n->t('Invalid route filters.')); }
  if ($trigger === 'nextcloud.page.opened') {
   // Page triggers need a concrete navigable app even when its route is wildcard.
   // Otherwise the saved campaign can never match a browser visit.
   if (in_array($placement,['any','settings','admin_settings'],true)) {
    throw new InvalidArgumentException($this->l10n->t('Select an app with pages for the navigation trigger.'));
   }
   if ($pageRoute !== '' && !$this->webRoutes->isSelectable($placement,$pageRoute)) {
    throw new InvalidArgumentException($this->l10n->t('Select a valid web route rather than an API endpoint.'));
   }
   $pageFilters = $this->webRoutes->normalizeFilters($pageRoute, $pageFilters);
  } else {
   $pageRoute = '';
   $pageFilters = [];
  }
  $frequency = $text('frequency', 12) ?: 'event';
  if (!in_array($frequency, self::FREQUENCIES, true)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid frequency.'));
  }
  $category = $text('category', 16) ?: 'institutional';
  if (!in_array($category, self::CATEGORIES, true)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid campaign category.'));
  }
  $boardVisible = ($payload['board_visible'] ?? false) === true || ($payload['board_visible'] ?? 0) === 1 || $presentation === 'board';
  $guestVisible = $payload['guest_visible'] ?? false;
  if (!in_array($guestVisible,[true,false,0,1],true)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid anonymous-visitor option.'));
  }
  $guestVisible = $guestVisible===true || $guestVisible===1;
  if ($guestVisible && ($audience!=='all'
      || $presentation!=='banner' || $trigger!=='nextcloud.time.window'
      || $category!=='institutional' || $placement!=='any'
      || !in_array($appTargetMode,['legacy','all'],true)
      || $boardVisible)) {
   throw new InvalidArgumentException($this->l10n->t('Anonymous visitors require a time-scheduled institutional banner for everyone without app restrictions.'));
  }
  $notificationFlags = [];
  foreach (['notify_users','notify_activity','notify_email'] as $key) {
   $value = $payload[$key] ?? false;
   if (!in_array($value,[true,false,0,1],true)) {
    throw new InvalidArgumentException($this->l10n->t('Invalid channel configuration: ').$key);
   }
   $notificationFlags[$key] = ($value === true || $value === 1) ? 1 : 0;
  }
  $gatewayChannels=$payload['gateway_channels'] ?? [];
  if (!is_array($gatewayChannels) || !array_is_list($gatewayChannels)
    || count($gatewayChannels)>count(GatewayTransport::LABELS)
    || count($gatewayChannels)!==count(array_unique($gatewayChannels, SORT_REGULAR))) {
   throw new InvalidArgumentException($this->l10n->t('Invalid Two-Factor Gateway channels.'));
  }
  foreach ($gatewayChannels as $gateway) {
   if (!is_string($gateway) || !isset(GatewayTransport::LABELS[$gateway])) {
    throw new InvalidArgumentException('Provedor Two-Factor Gateway desconhecido.');
   }
  }
  $hasOutbound=array_sum($notificationFlags)>0 || $gatewayChannels!==[];
  if ($guestVisible && $hasOutbound) {
   throw new InvalidArgumentException($this->l10n->t('Anonymous public banners do not send individual messages.'));
  }
  // Any trigger may deliver without a visual surface. All channels follow
  // the same event occurrence; a recipient-scoped outbox prevents global
  // sends when an individual action is observed.
  if ($presentation==='none' && !$boardVisible && !$hasOutbound) {
   throw new InvalidArgumentException($this->l10n->t('Select at least one delivery destination.'));
  }
  $commentsEnabled = ($payload['comments_enabled'] ?? true) !== false && ($payload['comments_enabled'] ?? 1) !== 0;
  $dismissible = ($payload['dismissible'] ?? true) !== false && ($payload['dismissible'] ?? 1) !== 0;
  $alignment = $text('text_align', 8) ?: 'left';
  if (!in_array($alignment, ['left','center','right'], true)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid alignment.'));
  }
  $textColor = $text('text_color', 9);
  if ($textColor !== '' && !preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/D', $textColor)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid text color.'));
  }
  if (strlen($textColor) === 4) {
   $textColor = '#' . $textColor[1] . $textColor[1]
    . $textColor[2] . $textColor[2] . $textColor[3] . $textColor[3];
  }
  $manualOrder = $payload['manual_order'] ?? 0;
  if (!is_int($manualOrder) && (!is_string($manualOrder) || !preg_match('/^-?[0-9]{1,6}$/D', $manualOrder))) {
   throw new InvalidArgumentException($this->l10n->t('Invalid presentation order.'));
  }
  $manualOrder = (int)$manualOrder;
  if ($manualOrder < -1000000 || $manualOrder > 1000000) { throw new InvalidArgumentException('Ordem fora do limite.'); }
  $translated = $payload['translated_content'] ?? [];
  if (!is_array($translated)) { throw new InvalidArgumentException($this->l10n->t('Invalid translations.')); }
  if (count($translated) > 12) { throw new InvalidArgumentException($this->l10n->t('Too many languages.')); }
  $safeTranslations = [];
  foreach ($translated as $locale => $item) {
   if (!is_string($locale) || !preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/D', $locale)
       || !is_array($item) || array_is_list($item) || count($item) > 3) {
    throw new InvalidArgumentException($this->l10n->t('Invalid translation format.'));
   }
   if (array_diff(array_keys($item), ['headline','message','cta_label']) !== []) {
    throw new InvalidArgumentException($this->l10n->t('Translation fields not allowed.'));
   }
   $values = [];
   foreach (['headline'=>180, 'message'=>5000, 'cta_label'=>80] as $field => $max) {
    if (!isset($item[$field])) { continue; }
    if (!is_string($item[$field]) || mb_strlen($item[$field]) > $max) {
     throw new InvalidArgumentException($this->l10n->t('Invalid translated text.'));
    }
    $values[$field] = trim($item[$field]);
   }
   $safeTranslations[$locale] = $values;
  }
  // Every delivery surface is eligible for an immediate publication:
  // visual destinations appear on each eligible user's next visit;
  // asynchronous channels are queued separately on activation.
  // The legacy "board" presentation remains accepted for saved content.
  $theme = $text('theme', 12) ?: 'default';
  if (!in_array($theme, self::THEMES, true)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid theme.'));
  }
  $bannerColor = $text('banner_color', 9) ?: '#6748AC';
  if (!preg_match('/^#(?:[a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/D', $bannerColor)) {
   throw new InvalidArgumentException($this->l10n->t('Color must use #RGB, #RRGGBB or #RRGGBBAA.'));
  }
  if (strlen($bannerColor) === 4) {
   $bannerColor = '#' . $bannerColor[1] . $bannerColor[1]
    . $bannerColor[2] . $bannerColor[2] . $bannerColor[3] . $bannerColor[3];
  }
  $bannerColor = strtoupper($bannerColor);
  $bannerIcon = $text('banner_icon', 12) ?: 'auto';
  if (!in_array($bannerIcon, self::BANNER_ICONS, true)) {
   throw new InvalidArgumentException($this->l10n->t('Select an allowed icon or the no-icon option.'));
  }
  $integer = static function (string $name, int $max) use ($payload, $l10n): int {
   $value = $payload[$name] ?? 0;
   if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 0 || (int)$value > $max) {
    throw new InvalidArgumentException($l10n->t('Invalid number: ') . $name);
   }
   return (int)$value;
  };
  $minInterval = $integer('min_interval_minutes', 43200);
  $maxTotal = $integer('max_total', 10000);
  $maxPerDay = $integer('max_per_day', 100);
  if ($category === 'sponsored' && $maxPerDay === 0) {
   $maxPerDay = 2; // Conservative ceiling for this POC
  }
  $status = $text('status', 12, true);
  if (!in_array($status, self::STATUSES, true)) {
   throw new InvalidArgumentException($l10n->t('Invalid campaign status.'));
  }
  $priority = $payload['priority'] ?? 10;
  if (filter_var($priority, FILTER_VALIDATE_INT) === false || (int)$priority < 0 || (int)$priority > 100) {
   throw new InvalidArgumentException($this->l10n->t('Priority must be between 0 and 100.'));
  }
  $parseTimestamp = static function (string $key) use ($payload, $l10n): ?int {
   $value = $payload[$key] ?? null;
   if ($value === null || $value === '') { return null; }
   if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 0) {
    throw new InvalidArgumentException($l10n->t('Invalid date: ') . $key);
   }
   return (int)$value;
  };
  $starts = $parseTimestamp('starts_at');
  $ends = $parseTimestamp('ends_at');
  if ($trigger === 'nextcloud.time.window' && $starts === null) {
   throw new InvalidArgumentException($this->l10n->t('Scheduled campaigns require a start date.'));
  }
  if ($starts !== null && $ends !== null && $ends <= $starts) {
   throw new InvalidArgumentException($this->l10n->t('The end date must be after the start date.'));
  }
  return [
   'name' => $name, 'description' => $description, 'headline' => $headline,
   'message' => $message, 'cta_label' => $ctaLabel, 'cta_url' => $ctaUrl,
   'trigger_key' => $trigger, 'audience_type' => $audience,
   'audience_group' => $groups[0] ?? '',
   'audience_groups' => json_encode($groups, JSON_THROW_ON_ERROR),
   'audience_users' => json_encode($users, JSON_THROW_ON_ERROR),
   'excluded_groups' => json_encode($excluded, JSON_THROW_ON_ERROR),
   'group_match' => $groupMatch, 'excluded_match' => $excludedMatch, 'placement' => $placement,
   'app_target_mode' => $appTargetMode,
   'app_target_apps' => json_encode($appTargetApps, JSON_THROW_ON_ERROR),
   'page_route' => $pageRoute,
   'page_filters' => json_encode($pageFilters, JSON_THROW_ON_ERROR),
   'frequency' => $frequency,
   'min_interval_minutes' => $minInterval, 'max_total' => $maxTotal,
   'max_per_day' => $maxPerDay, 'category' => $category, 'theme' => $theme,
   'board_visible' => $boardVisible ? 1 : 0,
   'guest_visible' => $guestVisible ? 1 : 0,
   ...$notificationFlags,
   'gateway_channels'=>json_encode($gatewayChannels,JSON_THROW_ON_ERROR),
   'comments_enabled' => $commentsEnabled ? 1 : 0,
   'dismissible' => $dismissible ? 1 : 0,
   'text_align' => $alignment, 'text_color' => strtoupper($textColor),
   'manual_order' => $manualOrder,
   'translated_content' => json_encode($safeTranslations, JSON_THROW_ON_ERROR),
   'banner_color' => $bannerColor, 'banner_icon' => $bannerIcon,
   'presentation' => $presentation, 'status' => $status, 'priority' => (int)$priority,
   'starts_at' => $starts, 'ends_at' => $ends,
  ];
 }

 public function create(array $data, string $uid): array {
  $validated = $this->validate($data);
  $this->nativeSubscriptions->assertCapacity($validated['trigger_key'], $validated['status']);
  $now = time();
  $validated['created_by'] = $uid;
  $validated['created_at'] = $now;
  $validated['published_at'] = $validated['status'] === 'active' ? $now : 0;
  $validated['updated_at'] = $now;
  $qb = $this->db->getQueryBuilder();
  $values = [];
  foreach ($validated as $column => $value) {
   $type = is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR;
   $values[$column] = $qb->createNamedParameter($value, $type);
  }
  $qb->insert(self::TABLE)->values($values)->executeStatement();
  // Capture the ID before refreshing subscriptions, which may write app config.
  $id = $this->db->lastInsertId(self::TABLE);
  if (str_starts_with($validated['trigger_key'], 'php_event.') && $validated['status'] === 'active') {
   $this->nativeSubscriptions->refresh();
  }
  $created = $this->find($id);
  if ($created === null) {
   throw new \RuntimeException('Created publication could not be loaded.');
  }
  return $created;
 }

 public function update(int $id, array $data): ?array {
  $original = $this->find($id);
  if ($original === null) { return null; }
  $validated = $this->validate($data);
  $this->nativeSubscriptions->assertCapacity($validated['trigger_key'], $validated['status'], $id);
  $validated['updated_at'] = time();
  $validated['published_at'] = ($original['status'] !== 'active' && $validated['status'] === 'active')
   ? time() : $original['published_at'];
  $qb = $this->db->getQueryBuilder();
  $qb->update(self::TABLE)
   ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
  foreach ($validated as $column => $value) {
   $qb->set($column, $qb->createNamedParameter($value, is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR));
  }
  $qb->executeStatement();
  if (str_starts_with($original['trigger_key'], 'php_event.')
      || str_starts_with($validated['trigger_key'], 'php_event.')) {
   $this->nativeSubscriptions->refresh();
  }
  return $this->find($id);
 }

 /**
  * Reorder non-archived campaigns; preserve audience, triggers and every
  * other property. A transaction prevents partially persisted ordering.
  * Keyboard buttons and drag-and-drop share this API.
  */
 public function move(int $id,string $direction,?int $targetId=null):?array {
  if (!in_array($direction,['up','down','before','after'],true)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid reordering operation.'));
  }
  $rows=array_values(array_filter($this->all(),
   static fn(array $c):bool=>$c['status']!=='archived'));
  usort($rows,static fn(array $a,array $b):int=>
   ($a['manual_order']<=>$b['manual_order'])
    ?:($b['priority']<=>$a['priority'])
    ?:($b['id']<=>$a['id']));
  $index=null;
  foreach ($rows as $i=>$c) { if ((int)$c['id']===$id) {$index=$i;break;} }
  if ($index===null) {return null;}
  if ($direction==='up' || $direction==='down') {
   $neighbor=$index+($direction==='up'?-1:1);
   if ($neighbor<0 || $neighbor>=count($rows)) {return $rows;}
   [$rows[$index],$rows[$neighbor]]=[$rows[$neighbor],$rows[$index]];
  } else {
   if ($targetId===null || $targetId<1) {
    throw new InvalidArgumentException($this->l10n->t('Select the destination campaign.'));
   }
   if ($targetId===$id) {return $rows;}
   $current=$rows[$index];
   array_splice($rows,$index,1);
   $position=null;
   foreach ($rows as $i=>$row) {
    if ((int)$row['id']===$targetId) {$position=$i;break;}
   }
   if ($position===null) {
    throw new InvalidArgumentException($this->l10n->t('Destination campaign unavailable.'));
   }
   array_splice($rows,$position+($direction==='after'?1:0),0,[$current]);
  }
  $this->db->beginTransaction();
  try {
   foreach ($rows as $i=>$c) {
    $order=($i+1)*10;
    if ((int)$c['manual_order']===$order) {continue;}
    $qb=$this->db->getQueryBuilder();
    $qb->update(self::TABLE)
     ->set('manual_order',$qb->createNamedParameter($order,IQueryBuilder::PARAM_INT))
     ->where($qb->expr()->eq('id',$qb->createNamedParameter((int)$c['id'],IQueryBuilder::PARAM_INT)))
     ->executeStatement();
   }
   $this->db->commit();
  } catch (\Throwable $error) {
   $this->db->rollBack();
   throw $error;
  }
  return $rows;
 }

 /**
  * Irreversible deletion is intentionally separate from the archive endpoint.
  * Only archived records can be deleted, and caller must confirm exact name.
  * Related delivery / publication evidence is removed transactionally; the
  * global event stream remains shared between publications and is retained.
  */
 public function purgeArchived(int $id,string $confirmName):bool {
  $original=$this->find($id);
  if ($original===null) {return false;}
  if ($original['status']!=='archived') {
   throw new InvalidArgumentException($this->l10n->t('Archive the publication before deleting it permanently.'));
  }
  if ($confirmName==='' || !hash_equals($original['name'],$confirmName)) {
   throw new InvalidArgumentException($this->l10n->t('Enter the exact internal name to permanently delete the publication.'));
  }
  $this->db->beginTransaction();
  try {
   foreach (['cc_board_comments','cc_board_pub','cc_board_sent',
     'cc_inbox_reads','cc_deliveries','cc_leases'] as $table) {
    $qb=$this->db->getQueryBuilder();
    $qb->delete($table)
     ->where($qb->expr()->eq('campaign_id',
       $qb->createNamedParameter($id,IQueryBuilder::PARAM_INT)))
     ->executeStatement();
   }
   $qb=$this->db->getQueryBuilder();
   $deleted=$qb->delete(self::TABLE)
    ->where($qb->expr()->eq('id',$qb->createNamedParameter($id,IQueryBuilder::PARAM_INT)))
    ->andWhere($qb->expr()->eq('status',$qb->createNamedParameter('archived')))
    ->executeStatement();
   if ($deleted!==1) { throw new \RuntimeException($this->l10n->t('Concurrent deletion or changed state.')); }
   $this->db->commit();
  } catch (\Throwable $error) {
   $this->db->rollBack();
   throw $error;
  }
  if (str_starts_with($original['trigger_key'],'php_event.')) {
   $this->nativeSubscriptions->refresh();
  }
  return true;
 }

 public function setStatus(int $id, string $status): ?array {
  if (!in_array($status, self::STATUSES, true)) {
   throw new InvalidArgumentException($this->l10n->t('Invalid campaign status.'));
  }
  $original = $this->find($id);
  if ($original === null) { return null; }
  $this->nativeSubscriptions->assertCapacity($original['trigger_key'], $status, $id);
  $qb = $this->db->getQueryBuilder();
  $qb->update(self::TABLE)
   ->set('status', $qb->createNamedParameter($status))
   ->set('updated_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT));
  if ($status === 'active') {
   $qb->set('published_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT));
  }
  $qb
   ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
   ->executeStatement();
  if (str_starts_with($original['trigger_key'], 'php_event.')) {
   $this->nativeSubscriptions->refresh();
  }
  return $this->find($id);
 }

 private function castRow(array $row): array {
  // Legacy rows use the schema default marker for unrestricted pages.
  if (!isset($row['page_route']) || $row['page_route'] === '*') { $row['page_route'] = ''; }
  $rawFilters = json_decode($row['page_filters'] ?? '{}', true);
  $row['page_filters'] = is_array($rawFilters) ? $rawFilters : [];
  $row['text_color'] = $row['text_color'] ?? '';
  $row['excluded_match'] = $row['excluded_match'] ?? 'any';
  $translated = json_decode($row['translated_content'] ?? '{}', true);
  $row['translated_content'] = is_array($translated) && !array_is_list($translated) ? $translated : [];
  foreach (['board_visible','guest_visible','comments_enabled','dismissible','notify_users','notify_activity','notify_email'] as $flag) {
   $row[$flag] = (int)($row[$flag] ?? 0) === 1;
  }
  foreach (['id', 'priority', 'created_at', 'updated_at', 'published_at', 'min_interval_minutes', 'max_total', 'max_per_day','manual_order'] as $field) {
   $row[$field] = (int)$row[$field];
  }
  foreach (['starts_at', 'ends_at'] as $field) {
   $row[$field] = ($row[$field] === null || $row[$field] === '') ? null : (int)$row[$field];
  }
  foreach (['audience_groups', 'audience_users', 'excluded_groups', 'app_target_apps', 'gateway_channels'] as $field) {
   $value = $row[$field] ?? '[]';
   $decoded = json_decode($value, true);
   $row[$field] = is_array($decoded) && array_is_list($decoded)
    ? array_values(array_filter($decoded, 'is_string')) : [];
  }
  if ($row['audience_type'] === 'group' && $row['audience_groups'] === [] && $row['audience_group'] !== '') {
   $row['audience_groups'] = [$row['audience_group']];
  }
  return $row;
 }
}
