/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { t } from '@nextcloud/l10n'
import type {
	CampaignForm,
	CampaignRecord,
	GroupSelection,
	UserSelection,
} from '../types/communications'

export type CampaignSavePayload = Omit<CampaignForm, 'starts_at' | 'ends_at'> & {
	starts_at: number | null
	ends_at: number | null
}

/** Convert persisted timestamps to editor-local dates. Never attach DB IDs to the editor form. */
export function campaignRecordToForm(
	c: CampaignRecord,
	groupIds: string[],
	defaultBannerColor: string,
): CampaignForm {
	return {
		name: c.name,
		description: c.description,
		headline: c.headline,
		message: c.message,
		cta_label: c.cta_label,
		cta_url: c.cta_url,
		trigger_key: c.trigger_key,
		page_route: c.page_route || '',
		page_filters: c.page_filters || {},
		audience_type: c.audience_type,
		audience_groups: groupIds,
		audience_users: c.audience_users || [],
		presentation: c.presentation,
		status: c.status === 'archived' ? 'draft' : c.status,
		priority: c.priority,
		placement: c.placement || 'any',
		app_target_mode: c.app_target_mode || 'area',
		app_target_apps: c.app_target_apps || [],
		frequency: c.frequency || 'event',
		min_interval_minutes: c.min_interval_minutes || 0,
		max_total: c.max_total || 0,
		max_per_day: c.max_per_day || 0,
		category: c.category || 'institutional',
		theme: c.theme || 'info',
		banner_color: c.banner_color || defaultBannerColor,
		banner_icon: c.banner_icon || 'auto',
		board_visible: !!c.board_visible,
		guest_visible: !!c.guest_visible,
		comments_enabled: c.comments_enabled !== false,
		notify_users: !!c.notify_users,
		notify_activity: !!c.notify_activity,
		notify_email: !!c.notify_email,
		gateway_channels: Array.isArray(c.gateway_channels)
			? c.gateway_channels
			: [],
		dismissible: c.dismissible !== false,
		text_align: c.text_align || 'left',
		text_color: c.text_color || '',
		manual_order: c.manual_order || 0,
		translated_content: c.translated_content || {},
		group_match: c.group_match || 'any',
		excluded_match: c.excluded_match || 'any',
		excluded_groups: c.excluded_groups || [],
		starts_at: c.starts_at === null ? null : new Date(c.starts_at * 1000),
		ends_at: c.ends_at === null ? null : new Date(c.ends_at * 1000),
	}
}

/** Explicit audience selections replace transient search suggestions. */
export function serializeCampaignForm(
	form: CampaignForm,
	groups: GroupSelection[],
	users: UserSelection[],
	excludedGroups: GroupSelection[],
): CampaignSavePayload {
	return {
		...form,
		audience_groups:
			form.audience_type === 'group' ? groups.map((option) => option.id) : [],
		audience_users:
			form.audience_type === 'users' ? users.map((option) => option.id) : [],
		excluded_groups: excludedGroups.map((option) => option.id),
		starts_at:
			form.starts_at instanceof Date && !Number.isNaN(form.starts_at.getTime())
				? Math.floor(form.starts_at.getTime() / 1000)
				: null,
		ends_at:
			form.ends_at instanceof Date && !Number.isNaN(form.ends_at.getTime())
				? Math.floor(form.ends_at.getTime() / 1000)
				: null,
	}
}

/** Reject missing context before a publication reaches the API. Server repeats authorization checks. */
export function validateCampaignPayload(
	data: CampaignSavePayload,
	hasDestination: boolean,
): string | null {
	if (!data.name.trim() || !data.headline.trim() || !data.message.trim()) {
		// TRANSLATORS Descriptive message shown in the publication form validation.
		return t('communications', 'Internal name, title, and message are required.')
	}
	if (!data.trigger_key) {
		// TRANSLATORS Action or instruction in the publication form validation.
		return t('communications', 'Choose when to deliver the publication.')
	}
	if (data.audience_type === 'group' && !data.audience_groups.length) {
		// TRANSLATORS Action or instruction in the publication form validation.
		return t('communications', 'Select at least one group.')
	}
	if (data.audience_type === 'users' && !data.audience_users.length) {
		// TRANSLATORS Action or instruction in the publication form validation.
		return t('communications', 'Select at least one user.')
	}
	if (
		['only', 'exclude'].includes(data.app_target_mode)
		&& data.trigger_key !== 'nextcloud.page.opened'
		&& !data.app_target_apps.length
	) {
		// TRANSLATORS Action or instruction in the publication form validation.
		return t('communications', 'Select at least one app for this display rule.')
	}
	if (Boolean(data.cta_label.trim()) !== Boolean(data.cta_url.trim())) {
		// TRANSLATORS Descriptive message shown in the publication form validation.
		return t('communications', 'To display a button, enter its text and URL.')
	}
	if (data.trigger_key === 'nextcloud.time.window' && data.starts_at === null) {
		// TRANSLATORS Short label for an option or field in the publication form validation.
		return t('communications', 'The schedule needs a start date.')
	}
	if (
		data.starts_at !== null
		&& data.ends_at !== null
		&& data.ends_at <= data.starts_at
	) {
		// TRANSLATORS Validation or failure message shown in the publication form validation.
		return t('communications', 'The end date must be after the start date.')
	}
	if (!hasDestination) {
		// TRANSLATORS Action or instruction in the publication form validation.
		return t('communications', 'Select at least one delivery method.')
	}

	if (
		data.guest_visible
		&& (data.board_visible
			|| data.notify_users
			|| data.notify_activity
			|| data.notify_email
			|| data.gateway_channels.length)
	) {
		// TRANSLATORS Descriptive message shown in the publication form validation.
		return t(
			'communications',
			'Public banners cannot send individual notifications.',
		)
	}
	return null
}
