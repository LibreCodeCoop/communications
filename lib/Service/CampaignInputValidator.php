<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use InvalidArgumentException;
use OCP\IGroupManager;
use OCP\IUserManager;

/**
 * Validates and normalizes editable publications without performing writes.
 * The repository owns persistence; this service owns the input contract.
 */
final class CampaignInputValidator {
	private const MAX_TARGETS = 40;

	public function __construct(
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private TriggerCatalog $triggerCatalog,
		private WebRouteCatalog $webRoutes,
		private \OCP\IL10N $l10n,
	) {
	}

	public function validate(array $payload): array {
		$text = $this->textReader($payload);
		$content = $this->content($text);
		$audience = $this->audience($payload, $text);
		$location = $this->location($payload, $text, $audience['trigger_key'], $audience['presentation']);
		$channels = $this->channels($payload, $text, $audience, $location);
		$appearance = $this->appearance($payload, $text);
		$timing = $this->timing($payload, $text, $audience['trigger_key'], $channels['category']);

		return [...$content, ...$audience, ...$location, ...$channels, ...$appearance, ...$timing];
	}

	private function textReader(array $payload): \Closure {
		$l10n = $this->l10n;
		return static function (string $key, int $max, bool $required = false) use ($payload, $l10n): string {
			$value = $payload[$key] ?? '';
			if (!is_string($value)) {
				throw new InvalidArgumentException($l10n->t('Invalid field: ') . $key);
			}
			$value = trim($value);
			if (($required && $value === '') || mb_strlen($value) > $max) {
				throw new InvalidArgumentException($l10n->t('Review the field ') . $key . $l10n->t(' (maximum ') . $max . $l10n->t(' characters).'));
			}
			return $value;
		};
	}

	private function targets(array $payload, string $field, string $legacyField, bool $required): array {
		$values = $payload[$field] ?? null;
		if ($values === null && $legacyField !== '' && isset($payload[$legacyField])) {
			$legacy = $payload[$legacyField];
			$values = is_string($legacy) && trim($legacy) !== '' ? [trim($legacy)] : [];
		}
		if ($values === null) {
			$values = [];
		}
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
		if ($required && $ids === []) {
			throw new InvalidArgumentException($this->l10n->t('Select at least one recipient.'));
		}
		return array_values($ids);
	}

	private function content(callable $text): array {
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
		return [
			'name' => $name, 'description' => $description, 'headline' => $headline,
			'message' => $message, 'cta_label' => $ctaLabel, 'cta_url' => $ctaUrl,
		];
	}

	private function audience(array $payload, callable $text): array {
		$trigger = $text('trigger_key', 64, true);
		if (!$this->triggerCatalog->supports($trigger)) {
			throw new InvalidArgumentException($this->l10n->t('Trigger unavailable. Verify that the integration is enabled.'));
		}
		$presentation = $text('presentation', 12, true);
		if (!in_array($presentation, CampaignRepository::PRESENTATIONS, true)) {
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
		return [
			'trigger_key' => $trigger, 'presentation' => $presentation,
			'audience_type' => $audience, 'audience_group' => $groups[0] ?? '',
			'audience_groups' => json_encode($groups, JSON_THROW_ON_ERROR),
			'audience_users' => json_encode($users, JSON_THROW_ON_ERROR),
			'excluded_groups' => json_encode($excluded, JSON_THROW_ON_ERROR),
			'group_match' => $groupMatch, 'excluded_match' => $excludedMatch,
		];
	}

	private function location(array $payload, callable $text, string $trigger, string $presentation): array {
		$placement = $text('placement', 32) ?: 'any';
		if (!$this->triggerCatalog->supportsPlacement($placement)) {
			throw new InvalidArgumentException($this->l10n->t('Invalid display location.'));
		}
		$appTargetMode = $text('app_target_mode', 12) ?: 'legacy';
		if (!in_array($appTargetMode, ['legacy','all','only','exclude'], true)) {
			throw new InvalidArgumentException($this->l10n->t('Invalid application selection mode.'));
		}
		$appTargetApps = [];
		if ($trigger === 'nextcloud.page.opened' || in_array($presentation, ['board','none'], true)) {
			$appTargetMode = 'legacy';
		} elseif (in_array($appTargetMode, ['only','exclude'], true)) {
			$appTargetApps = $this->targets($payload, 'app_target_apps', '', true);
			foreach ($appTargetApps as $appId) {
				if ($appId === 'any' || !$this->triggerCatalog->supportsPlacement($appId)) {
					throw new InvalidArgumentException($this->l10n->t('Selected application is unavailable: ') . $appId);
				}
			}
		}
		$pageRoute = $text('page_route', 255);
		$pageFilters = $payload['page_filters'] ?? [];
		if (!is_array($pageFilters)) {
			throw new InvalidArgumentException($this->l10n->t('Invalid route filters.'));
		}
		if ($trigger === 'nextcloud.page.opened') {
			// Page triggers need a concrete navigable app even when its route is wildcard.
			// Otherwise the saved campaign can never match a browser visit.
			if (in_array($placement, ['any','settings','admin_settings'], true)) {
				throw new InvalidArgumentException($this->l10n->t('Select an app with pages for the navigation trigger.'));
			}
			if ($pageRoute !== '' && !$this->webRoutes->isSelectable($placement, $pageRoute)) {
				throw new InvalidArgumentException($this->l10n->t('Select a valid web route rather than an API endpoint.'));
			}
			$pageFilters = $this->webRoutes->normalizeFilters($pageRoute, $pageFilters);
		} else {
			$pageRoute = '';
			$pageFilters = [];
		}
		return [
			'placement' => $placement, 'app_target_mode' => $appTargetMode,
			'app_target_apps' => json_encode($appTargetApps, JSON_THROW_ON_ERROR),
			'page_route' => $pageRoute,
			'page_filters' => json_encode($pageFilters, JSON_THROW_ON_ERROR),
		];
	}

	private function channels(array $payload, callable $text, array $audience, array $location): array {
		$trigger = $audience['trigger_key'];
		$presentation = $audience['presentation'];
		$audienceType = $audience['audience_type'];
		$placement = $location['placement'];
		$appTargetMode = $location['app_target_mode'];
		$frequency = $text('frequency', 12) ?: 'event';
		if (!in_array($frequency, CampaignRepository::FREQUENCIES, true)) {
			throw new InvalidArgumentException($this->l10n->t('Invalid frequency.'));
		}
		$category = $text('category', 16) ?: 'institutional';
		if (!in_array($category, CampaignRepository::CATEGORIES, true)) {
			throw new InvalidArgumentException($this->l10n->t('Invalid campaign category.'));
		}
		$boardVisible = ($payload['board_visible'] ?? false) === true || ($payload['board_visible'] ?? 0) === 1 || $presentation === 'board';
		$guestVisible = $payload['guest_visible'] ?? false;
		if (!in_array($guestVisible, [true,false,0,1], true)) {
			throw new InvalidArgumentException($this->l10n->t('Invalid anonymous-visitor option.'));
		}
		$guestVisible = $guestVisible === true || $guestVisible === 1;
		if ($guestVisible && ($audienceType !== 'all'
			|| $presentation !== 'banner' || $trigger !== 'nextcloud.time.window'
			|| $category !== 'institutional' || $placement !== 'any'
			|| !in_array($appTargetMode, ['legacy','all'], true)
			|| $boardVisible)) {
			throw new InvalidArgumentException($this->l10n->t('Anonymous visitors require a time-scheduled institutional banner for everyone without app restrictions.'));
		}
		$notificationFlags = [];
		foreach (['notify_users','notify_activity','notify_email'] as $key) {
			$value = $payload[$key] ?? false;
			if (!in_array($value, [true,false,0,1], true)) {
				throw new InvalidArgumentException($this->l10n->t('Invalid channel configuration: ') . $key);
			}
			$notificationFlags[$key] = ($value === true || $value === 1) ? 1 : 0;
		}
		$gatewayChannels = $payload['gateway_channels'] ?? [];
		if (!is_array($gatewayChannels) || !array_is_list($gatewayChannels)
	|| count($gatewayChannels) > count(GatewayTransport::LABELS)
	|| count($gatewayChannels) !== count(array_unique($gatewayChannels, SORT_REGULAR))) {
			throw new InvalidArgumentException($this->l10n->t('Invalid Two-Factor Gateway channels.'));
		}
		foreach ($gatewayChannels as $gateway) {
			if (!is_string($gateway) || !isset(GatewayTransport::LABELS[$gateway])) {
				throw new InvalidArgumentException('Provedor Two-Factor Gateway desconhecido.');
			}
		}
		$hasOutbound = array_sum($notificationFlags) > 0 || $gatewayChannels !== [];
		if ($guestVisible && $hasOutbound) {
			throw new InvalidArgumentException($this->l10n->t('Anonymous public banners do not send individual messages.'));
		}
		// Any trigger may deliver without a visual surface. All channels follow
		// the same event occurrence; a recipient-scoped outbox prevents global
		// sends when an individual action is observed.
		if ($presentation === 'none' && !$boardVisible && !$hasOutbound) {
			throw new InvalidArgumentException($this->l10n->t('Select at least one delivery destination.'));
		}
		return [
			'frequency' => $frequency, 'category' => $category,
			'board_visible' => $boardVisible ? 1 : 0,
			'guest_visible' => $guestVisible ? 1 : 0,
			...$notificationFlags,
			'gateway_channels' => json_encode($gatewayChannels, JSON_THROW_ON_ERROR),
		];
	}

	/** @return array<string,array<string,string>> */
	private function translations(array $payload): array {
		$translated = $payload['translated_content'] ?? [];
		if (!is_array($translated)) {
			throw new InvalidArgumentException($this->l10n->t('Invalid translations.'));
		}
		if (count($translated) > 12) {
			throw new InvalidArgumentException($this->l10n->t('Too many languages.'));
		}
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
			foreach (['headline' => 180, 'message' => 5000, 'cta_label' => 80] as $field => $max) {
				if (!isset($item[$field])) {
					continue;
				}
				if (!is_string($item[$field]) || mb_strlen($item[$field]) > $max) {
					throw new InvalidArgumentException($this->l10n->t('Invalid translated text.'));
				}
				$values[$field] = trim($item[$field]);
			}
			$safeTranslations[$locale] = $values;
		}
		return $safeTranslations;
	}

	private function appearance(array $payload, callable $text): array {
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
		if ($manualOrder < -1000000 || $manualOrder > 1000000) {
			throw new InvalidArgumentException('Ordem fora do limite.');
		}
		$safeTranslations = $this->translations($payload);
		$theme = $text('theme', 12) ?: 'default';
		if (!in_array($theme, CampaignRepository::THEMES, true)) {
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
		if (!in_array($bannerIcon, CampaignRepository::BANNER_ICONS, true)) {
			throw new InvalidArgumentException($this->l10n->t('Select an allowed icon or the no-icon option.'));
		}
		return [
			'comments_enabled' => $commentsEnabled ? 1 : 0,
			'dismissible' => $dismissible ? 1 : 0,
			'text_align' => $alignment, 'text_color' => strtoupper($textColor),
			'manual_order' => $manualOrder,
			'translated_content' => json_encode($safeTranslations, JSON_THROW_ON_ERROR),
			'theme' => $theme, 'banner_color' => $bannerColor,
			'banner_icon' => $bannerIcon,
		];
	}

	private function timing(array $payload, callable $text, string $trigger, string $category): array {
		$l10n = $this->l10n;
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
		if (!in_array($status, CampaignRepository::STATUSES, true)) {
			throw new InvalidArgumentException($l10n->t('Invalid campaign status.'));
		}
		$priority = $payload['priority'] ?? 10;
		if (filter_var($priority, FILTER_VALIDATE_INT) === false || (int)$priority < 0 || (int)$priority > 100) {
			throw new InvalidArgumentException($this->l10n->t('Priority must be between 0 and 100.'));
		}
		$parseTimestamp = static function (string $key) use ($payload, $l10n): ?int {
			$value = $payload[$key] ?? null;
			if ($value === null || $value === '') {
				return null;
			}
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
			'min_interval_minutes' => $minInterval, 'max_total' => $maxTotal,
			'max_per_day' => $maxPerDay, 'status' => $status,
			'priority' => (int)$priority,
			'starts_at' => $starts, 'ends_at' => $ends,
		];
	}
}
