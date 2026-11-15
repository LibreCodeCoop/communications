<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use InvalidArgumentException;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;

/**
 * Validates and normalizes editable publications without performing writes.
 * The repository owns persistence; this service owns the input contract.
 */
final class CampaignInputValidator {
	private const MAX_TARGETS = 40;
	private const SPONSORED_DEFAULT_DAILY_LIMIT = 2;

	public function __construct(
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private TriggerCatalog $triggerCatalog,
		private WebRouteCatalog $webRoutes,
		private IL10N $l10n,
	) {
	}

	public function validate(array $payload): array {
		$fields = new CampaignInputFields($payload, $this->l10n);
		$content = $this->content($fields);
		$audience = $this->audience($payload, $fields);
		$location = $this->location($payload, $fields, $audience['trigger_key'], $audience['presentation']);
		$channels = $this->channels($payload, $fields, $audience, $location);
		$appearance = $this->appearance($payload, $fields);
		$timing = $this->timing($payload, $fields, $audience['trigger_key'], $channels['category']);

		return [...$content, ...$audience, ...$location, ...$channels, ...$appearance, ...$timing];
	}

	private function targets(array $payload, string $field, bool $required): array {
		$values = $payload[$field] ?? [];
		if (!is_array($values) || !array_is_list($values) || count($values) > self::MAX_TARGETS) {
			// TRANSLATORS Prefix followed by a dynamic value in the publication input validation; preserve its trailing space.
			throw new InvalidArgumentException($this->l10n->t('Invalid selection for ') . $field . '.');
		}
		$ids = [];
		foreach ($values as $value) {
			if (!is_string($value) || trim($value) !== $value || $value === '' || mb_strlen($value) > 64) {
				// TRANSLATORS Prefix followed by a dynamic value in the publication input validation; preserve its trailing space.
				throw new InvalidArgumentException($this->l10n->t('Invalid identifier in ') . $field . '.');
			}
			$ids[$value] = $value;
		}
		if ($required && $ids === []) {
			// TRANSLATORS Action or instruction in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Select at least one recipient.'));
		}
		return array_values($ids);
	}

	private function content(CampaignInputFields $fields): array {
		$name = $fields->text('name', 160, true);
		$description = $fields->text('description', 700);
		$headline = $fields->text('headline', 180, true);
		$message = $fields->text('message', 4000, true);
		$ctaLabel = $fields->text('cta_label', 80);
		$ctaUrl = $fields->text('cta_url', 1900);
		if (($ctaLabel === '') !== ($ctaUrl === '')) {
			// TRANSLATORS Action or instruction in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Enter both button text and URL.'));
		}
		if ($ctaUrl !== '' && !CtaUrl::isAllowed($ctaUrl)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid link. Use HTTP(S), mailto:, tel: or a safe internal path.'));
		}
		return [
			'name' => $name, 'description' => $description, 'headline' => $headline,
			'message' => $message, 'cta_label' => $ctaLabel, 'cta_url' => $ctaUrl,
		];
	}

	private function audience(array $payload, CampaignInputFields $fields): array {
		$trigger = $fields->text('trigger_key', 64, true);
		if (!$this->triggerCatalog->supports($trigger)) {
			// TRANSLATORS Descriptive message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Trigger unavailable. Verify that the integration is enabled.'));
		}
		$presentation = $fields->text('presentation', 12, true);
		if (!in_array($presentation, CampaignRepository::PRESENTATIONS, true)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid presentation format.'));
		}
		$audience = $fields->text('audience_type', 20, true);
		if (!in_array($audience, ['all', 'admins', 'group', 'users'], true)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid target audience.'));
		}
		$groups = $audience === 'group' ? $this->audienceGroups($payload) : [];
		$users = $audience === 'users' ? $this->audienceUsers($payload) : [];
		$excluded = $this->excludedGroups($payload);
		$groupMatch = $fields->text('group_match', 8) ?: 'any';
		if (!in_array($groupMatch, ['any', 'all'], true)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid group matching criteria.'));
		}
		$excludedMatch = $fields->text('excluded_match', 8) ?: 'any';
		if (!in_array($excludedMatch, ['any', 'all'], true)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid group exclusion criteria.'));
		}
		return [
			'trigger_key' => $trigger, 'presentation' => $presentation,
			'audience_type' => $audience, 'audience_groups' => json_encode($groups, JSON_THROW_ON_ERROR),
			'audience_users' => json_encode($users, JSON_THROW_ON_ERROR),
			'excluded_groups' => json_encode($excluded, JSON_THROW_ON_ERROR),
			'group_match' => $groupMatch, 'excluded_match' => $excludedMatch,
		];
	}

	private function audienceGroups(array $payload): array {
		$groups = $this->targets($payload, 'audience_groups', true);
		foreach ($groups as $group) {
			if ($this->groupManager->get($group) === null) {
				// TRANSLATORS Prefix followed by the identifier of a missing group; keep the trailing space.
				throw new InvalidArgumentException($this->l10n->t('Selected group does not exist: ') . $group);
			}
		}
		return $groups;
	}

	private function audienceUsers(array $payload): array {
		$users = $this->targets($payload, 'audience_users', true);
		foreach ($users as $uid) {
			if ($this->userManager->get($uid) === null) {
				// TRANSLATORS Action or instruction in the publication input validation.
				throw new InvalidArgumentException($this->l10n->t('Selected user does not exist.'));
			}
		}
		return $users;
	}

	private function excludedGroups(array $payload): array {
		$groups = $this->targets($payload, 'excluded_groups', false);
		foreach ($groups as $group) {
			if ($this->groupManager->get($group) === null) {
				// TRANSLATORS Short label for an option or field in the publication input validation.
				throw new InvalidArgumentException($this->l10n->t('An excluded group does not exist.'));
			}
		}
		return $groups;
	}

	private function location(array $payload, CampaignInputFields $fields, string $trigger, string $presentation): array {
		$placement = $fields->text('placement', 32) ?: 'any';
		if (!$this->triggerCatalog->supportsPlacement($placement)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid display location.'));
		}
		[$appTargetMode, $appTargetApps] = $this->applicationTargets($payload, $fields, $trigger, $presentation);
		[$pageRoute, $pageFilters] = $this->pageSelection($payload, $fields, $trigger, $placement);
		return [
			'placement' => $placement, 'app_target_mode' => $appTargetMode,
			'app_target_apps' => json_encode($appTargetApps, JSON_THROW_ON_ERROR),
			'page_route' => $pageRoute,
			'page_filters' => json_encode($pageFilters, JSON_THROW_ON_ERROR),
		];
	}

	private function applicationTargets(
		array $payload, CampaignInputFields $fields, string $trigger, string $presentation,
	): array {
		$mode = $fields->text('app_target_mode', 12) ?: 'area';
		if (!in_array($mode, ['area', 'all', 'only', 'exclude'], true)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid application selection mode.'));
		}
		if ($trigger === 'nextcloud.page.opened' || $presentation === 'none') {
			return ['area', []];
		}
		if (!in_array($mode, ['only', 'exclude'], true)) {
			return [$mode, []];
		}
		$apps = $this->targets($payload, 'app_target_apps', true);
		foreach ($apps as $app) {
			if ($app === 'any' || !$this->triggerCatalog->supportsPlacement($app)) {
				// TRANSLATORS Prefix followed by a dynamic value in the publication input validation; preserve its trailing space.
				throw new InvalidArgumentException($this->l10n->t('Selected application is unavailable: ') . $app);
			}
		}
		return [$mode, $apps];
	}

	private function pageSelection(
		array $payload, CampaignInputFields $fields, string $trigger, string $placement,
	): array {
		$pageRoute = $fields->text('page_route', 255);
		$pageFilters = $payload['page_filters'] ?? [];
		if (!is_array($pageFilters)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid route filters.'));
		}
		if ($trigger !== 'nextcloud.page.opened') {
			return ['', []];
		}
		if (in_array($placement, ['any', 'settings', 'admin_settings'], true)) {
			// TRANSLATORS Action or instruction in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Select an app with pages for the navigation trigger.'));
		}
		if ($pageRoute !== '' && !$this->webRoutes->isSelectable($placement, $pageRoute)) {
			// TRANSLATORS Action or instruction in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Select a valid web route rather than an API endpoint.'));
		}
		return [$pageRoute, $this->webRoutes->normalizeFilters($pageRoute, $pageFilters)];
	}

	private function channels(array $payload, CampaignInputFields $fields, array $audience, array $location): array {
		$presentation = $audience['presentation'];
		$frequency = $fields->text('frequency', 12) ?: 'event';
		if (!in_array($frequency, CampaignRepository::FREQUENCIES, true)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid frequency.'));
		}
		$category = $fields->text('category', 16) ?: 'institutional';
		if (!in_array($category, CampaignRepository::CATEGORIES, true)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid campaign category.'));
		}
		$boardVisible = ($payload['board_visible'] ?? false) === true || ($payload['board_visible'] ?? 0) === 1;
		$guestVisible = $this->guestVisibility($payload, $audience, $location, $category, $boardVisible);
		$notificationFlags = $this->notificationFlags($payload);
		$gatewayChannels = $this->gatewayChannels($payload);
		$hasOutbound = array_sum($notificationFlags) > 0 || $gatewayChannels !== [];
		if ($guestVisible && $hasOutbound) {
			// TRANSLATORS Descriptive message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Anonymous public banners do not send individual messages.'));
		}
		if ($presentation === 'none' && !$boardVisible && !$hasOutbound) {
			// TRANSLATORS Action or instruction in the publication input validation.
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

	private function guestVisibility(
		array $payload, array $audience, array $location, string $category, bool $boardVisible,
	): bool {
		$value = $payload['guest_visible'] ?? false;
		if (!in_array($value, [true, false, 0, 1], true)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid anonymous-visitor option.'));
		}
		$guest = $value === true || $value === 1;
		if ($guest && !GuestBannerPolicy::supports([
			'audience_type' => $audience['audience_type'],
			'presentation' => $audience['presentation'],
			'trigger_key' => $audience['trigger_key'],
			'category' => $category,
			'placement' => $location['placement'],
			'app_target_mode' => $location['app_target_mode'],
			'board_visible' => $boardVisible,
		])) {
			// TRANSLATORS Explanatory help text shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Anonymous visitors require a time-scheduled institutional banner for everyone without app restrictions.'));
		}
		return $guest;
	}

	private function notificationFlags(array $payload): array {
		$flags = [];
		foreach (['notify_users', 'notify_activity', 'notify_email'] as $key) {
			$value = $payload[$key] ?? false;
			if (!in_array($value, [true, false, 0, 1], true)) {
				// TRANSLATORS Prefix followed by a dynamic value in the publication input validation; preserve its trailing space.
				throw new InvalidArgumentException($this->l10n->t('Invalid channel configuration: ') . $key);
			}
			$flags[$key] = ($value === true || $value === 1) ? 1 : 0;
		}
		return $flags;
	}

	private function gatewayChannels(array $payload): array {
		$channels = $payload['gateway_channels'] ?? [];
		if (!is_array($channels) || !array_is_list($channels)
			|| count($channels) > count(GatewayTransport::LABELS)
			|| count($channels) !== count(array_unique($channels, SORT_REGULAR))) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid Two-Factor Gateway channels.'));
		}
		foreach ($channels as $channel) {
			if (!is_string($channel) || !isset(GatewayTransport::LABELS[$channel])) {
				// TRANSLATORS Validation or failure message shown in the publication input validation.
				throw new InvalidArgumentException($this->l10n->t('Unknown Two-Factor Gateway provider.'));
			}
		}
		return $channels;
	}

	/** @return array<string,array<string,string>> */
	private function translations(array $payload): array {
		$translated = $payload['translated_content'] ?? [];
		if (!is_array($translated)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid translations.'));
		}
		if (count($translated) > 12) {
			// TRANSLATORS Short label for an option or field in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Too many languages.'));
		}
		$safeTranslations = [];
		foreach ($translated as $locale => $item) {
			if (!is_string($locale) || !preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/D', $locale)
				|| !is_array($item) || array_is_list($item) || count($item) > 3) {
				// TRANSLATORS Validation or failure message shown in the publication input validation.
				throw new InvalidArgumentException($this->l10n->t('Invalid translation format.'));
			}
			$safeTranslations[$locale] = $this->translatedFields($item);
		}
		return $safeTranslations;
	}

	private function translatedFields(array $item): array {
		if (array_diff(array_keys($item), ['headline', 'message', 'cta_label']) !== []) {
			// TRANSLATORS Short label for an option or field in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Translation fields not allowed.'));
		}
		$values = [];
		foreach (['headline' => 180, 'message' => 5000, 'cta_label' => 80] as $field => $max) {
			if (!isset($item[$field])) {
				continue;
			}
			if (!is_string($item[$field]) || mb_strlen($item[$field]) > $max) {
				// TRANSLATORS Validation or failure message shown in the publication input validation.
				throw new InvalidArgumentException($this->l10n->t('Invalid translated text.'));
			}
			$values[$field] = trim($item[$field]);
		}
		return $values;
	}

	private function appearance(array $payload, CampaignInputFields $fields): array {
		$commentsEnabled = ($payload['comments_enabled'] ?? true) !== false && ($payload['comments_enabled'] ?? 1) !== 0;
		$dismissible = ($payload['dismissible'] ?? true) !== false && ($payload['dismissible'] ?? 1) !== 0;
		$alignment = $fields->text('text_align', 8) ?: 'left';
		if (!in_array($alignment, ['left','center','right'], true)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid alignment.'));
		}
		$textColor = $this->textColor($fields);
		$manualOrder = $this->manualOrder($payload);
		$safeTranslations = $this->translations($payload);
		$theme = $fields->text('theme', 12) ?: 'info';
		if (!in_array($theme, CampaignRepository::THEMES, true)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid theme.'));
		}
		$bannerColor = $this->bannerColor($fields);
		$bannerIcon = $fields->text('banner_icon', 12) ?: 'auto';
		if (!in_array($bannerIcon, CampaignRepository::BANNER_ICONS, true)) {
			// TRANSLATORS Action or instruction in the publication input validation.
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

	private function textColor(CampaignInputFields $fields): string {
		$color = $fields->text('text_color', 9);
		if ($color !== '' && !preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/D', $color)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid text color.'));
		}
		return self::expandShortColor($color);
	}

	private function bannerColor(CampaignInputFields $fields): string {
		$color = $fields->text('banner_color', 9) ?: '#6748AC';
		if (!preg_match('/^#(?:[a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/D', $color)) {
			// TRANSLATORS Descriptive message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Color must use #RGB, #RRGGBB or #RRGGBBAA.'));
		}
		return strtoupper(self::expandShortColor($color));
	}

	private static function expandShortColor(string $color): string {
		return strlen($color) === 4
			? '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3]
			: $color;
	}

	private function manualOrder(array $payload): int {
		$value = $payload['manual_order'] ?? 0;
		if (!is_int($value) && (!is_string($value) || !preg_match('/^-?[0-9]{1,6}$/D', $value))) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid presentation order.'));
		}
		$value = (int)$value;
		if ($value < -1000000 || $value > 1000000) {
			// TRANSLATORS Descriptive message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Presentation order is out of range.'));
		}
		return $value;
	}

	private function timing(array $payload, CampaignInputFields $fields, string $trigger, string $category): array {
		$minInterval = $fields->number('min_interval_minutes', 43200);
		$maxTotal = $fields->number('max_total', 10000);
		$maxPerDay = $fields->number('max_per_day', 100);
		if ($category === 'sponsored' && $maxPerDay === 0) {
			$maxPerDay = self::SPONSORED_DEFAULT_DAILY_LIMIT;
		}
		$status = $fields->text('status', 12, true);
		if (!in_array($status, CampaignRepository::STATUSES, true)) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Invalid campaign status.'));
		}
		$priority = $payload['priority'] ?? 10;
		if (filter_var($priority, FILTER_VALIDATE_INT) === false || (int)$priority < 0 || (int)$priority > 100) {
			// TRANSLATORS Descriptive message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Priority must be between 0 and 100.'));
		}
		$starts = $fields->timestamp('starts_at');
		$ends = $fields->timestamp('ends_at');
		if ($trigger === 'nextcloud.time.window' && $starts === null) {
			// TRANSLATORS Descriptive message shown in the publication input validation.
			throw new InvalidArgumentException($this->l10n->t('Scheduled campaigns require a start date.'));
		}
		if ($starts !== null && $ends !== null && $ends <= $starts) {
			// TRANSLATORS Validation or failure message shown in the publication input validation.
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
