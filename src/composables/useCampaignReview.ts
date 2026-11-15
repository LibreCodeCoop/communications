// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { computed } from 'vue'
import type { Ref, ComputedRef } from 'vue'
import { getLanguage, t } from '@nextcloud/l10n'
import type {
	CampaignForm,
	CampaignCatalog,
	GroupSelection,
	UserSelection,
} from '../types/communications'

type Option = { label: string; value: string }

/** Recipient-facing summary, derived only from the live draft and catalog. */
export function useCampaignReview(
	form: Ref<CampaignForm>,
	catalog: Readonly<Ref<CampaignCatalog>>,
	selectedGroups: Ref<GroupSelection[]>,
	selectedUsers: Ref<UserSelection[]>,
	placementOptions: ComputedRef<Option[]>,
	triggerOptions: ComputedRef<Option[]>,
	frequencyOptions: ReadonlyArray<Option>,
	inboxCopy: ComputedRef<boolean>,
	eventDrivenDelivery: ComputedRef<boolean>,
) {
	const summaryAction = computed(
		() =>
			triggerOptions.value.find((t) => t.value === form.value.trigger_key)
				?.label
			// TRANSLATORS Instruction to choose a triggering event for the publication.
			|| t('communications', 'Select an action'),
	)
	const sourceForReview = computed(() =>
		catalog.value.automation?.sources?.find((source) =>
			source.actions?.some((action) => action.key === form.value.trigger_key),
		),
	)
	const summaryWhen = computed(() => {
		// TRANSLATORS The publication trigger or delivery channel has not been configured.
		if (!form.value.trigger_key) return t('communications', 'Not configured')
		if (form.value.trigger_key === 'nextcloud.page.opened') {
			const place =
				placementOptions.value.find((p) => p.value === form.value.placement)
					?.label || form.value.placement
			const filters = Object.entries(form.value.page_filters || {})
				.map(
					([name, rule]) =>
						'{'
						+ name
						+ '} '
						+ ({
							equals: '=',
							// TRANSLATORS Operator for matching a URL route variable by its prefix.
							prefix: t('communications', 'starts with'),
							// TRANSLATORS Operator restricting a route variable to digits.
							digits: t('communications', 'numeric'),
						}[rule.operator] || rule.operator)
						+ ' '
						+ (rule.value || ''),
				)
				.join('; ')
			return (
				// TRANSLATORS Prefix before a destination page label in the publication review.
				t('communications', 'Open ')
				+ place
				+ (form.value.page_route
					? ' · ' + form.value.page_route
					: // TRANSLATORS Suffix meaning any page of the selected application will match.
						t('communications', ' · any page'))
				+ (filters ? ' · ' + filters : '')
			)
		}
		return sourceForReview.value
			? sourceForReview.value.label + ' → ' + summaryAction.value
			: summaryAction.value
	})
	const audienceReview = computed(() => {
		if (form.value.audience_type === 'users')
			return (
				selectedUsers.value.map((u) => u.displayName || u.id).join(', ')
				// TRANSLATORS Audience review when no individual account is selected.
				|| t('communications', 'No user selected')
			)
		if (form.value.audience_type === 'group') {
			const ids = selectedGroups.value.map((g) => g.displayName || g.id)
			return ids.length
				? ids.join(
						form.value.group_match === 'all'
							? // TRANSLATORS Logical operator joining required audience groups.
								t('communications', ' AND ')
							: // TRANSLATORS Logical operator joining alternative audience groups.
								t('communications', ' OR '),
					)
				: // TRANSLATORS Audience review when no group is selected.
					t('communications', 'No group selected')
		}
		// TRANSLATORS Audience scope covering all authenticated Nextcloud accounts.
		return t('communications', 'All signed-in users')
	})
	const locationReview = computed(() =>
		form.value.presentation === 'none'
			? inboxCopy.value
				? // TRANSLATORS Short label for an option or field in the publications management screen.
					t('communications', 'Inbox only; no banner or dialog')
				: // TRANSLATORS Publication that does not show a banner or modal.
					t('communications', 'No on-screen display')
			: placementOptions.value.find((x) => x.value === form.value.placement)
					?.label || form.value.placement,
	)
	function dailyFrequencyLabel(count: number): string {
		// TRANSLATORS {count} is the maximum number of deliveries allowed in 24 hours.
		return t('communications', '{count} in 24 hours', { count })
	}
	const frequencyReview = computed(() => {
		if (eventDrivenDelivery.value) {
			// TRANSLATORS Descriptive message shown in the publications management screen.
			return t(
				'communications',
				'On each occurrence, respecting frequency and deduplication limits',
			)
		}
		if (!['banner', 'modal'].includes(form.value.presentation)) {
			// TRANSLATORS Descriptive message shown in the publications management screen.
			return t('communications', 'Outbound channels: one batch per activation')
		}
		const freq =
			frequencyOptions.find((x) => x.value === form.value.frequency)?.label
			|| form.value.frequency
		const caps = [
			form.value.max_total > 0
				? // TRANSLATORS Prefix of a maximum total delivery limit.
					t('communications', 'maximum ')
					+ form.value.max_total
					// TRANSLATORS Suffix of a maximum total delivery limit for each recipient.
					+ t('communications', ' per user')
				: '',
			form.value.max_per_day > 0
				? dailyFrequencyLabel(form.value.max_per_day)
				: '',
		].filter(Boolean)
		return caps.length ? freq + '; ' + caps.join('; ') : freq
	})
	const scheduleReview = computed(() => {
		const from =
			form.value.starts_at instanceof Date
				? form.value.starts_at.toLocaleDateString(
						getLanguage().replace('_', '-'),
					)
				: ''
		const until =
			form.value.ends_at instanceof Date
				? form.value.ends_at.toLocaleDateString(
						getLanguage().replace('_', '-'),
					)
				: ''
		return from || until
			? // TRANSLATORS Prefix before the start date of the publication window.
				t('communications', 'From ')
					// TRANSLATORS Beginning of the publication window at the current time.
					+ (from || t('communications', 'now'))
					// TRANSLATORS Separator before the publication end date.
					+ t('communications', ' until ')
					// TRANSLATORS Publication window without an expiration date.
					+ (until || t('communications', 'no end date'))
			: // TRANSLATORS Publication with an unlimited availability window.
				t('communications', 'No date limit')
	})

	return {
		summaryAction,
		sourceForReview,
		summaryWhen,
		audienceReview,
		locationReview,
		dailyFrequencyLabel,
		frequencyReview,
		scheduleReview,
	}
}
