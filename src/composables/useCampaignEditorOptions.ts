// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { computed } from 'vue'
import type { Ref } from 'vue'
import { t } from '@nextcloud/l10n'
import type { CampaignCatalog } from '../types/communications'
import {
	mdiInformation,
	mdiCheckboxMarkedCircle,
	mdiAlert,
	mdiAlertDecagram,
	mdiPaletteOutline,
	mdiMinusCircleOutline,
} from '@mdi/js'

/** The editor's translated choices and server-provided route catalog. */
export function useCampaignEditorOptions(catalog: Readonly<Ref<CampaignCatalog>>) {
	const audienceOptions = [
		// TRANSLATORS Audience scope covering all authenticated Nextcloud accounts.
		{ label: t('communications', 'All signed-in users'), value: 'all' },
		// TRANSLATORS Audience scope restricted to Nextcloud administrators.
		{ label: t('communications', 'Administrators only'), value: 'admins' },
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{
			label: t('communications', 'One or more Nextcloud groups'),
			value: 'group',
		},
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ label: t('communications', 'Specific users'), value: 'users' },
	]
	const publishOptions = [
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{
			label: t('communications', 'Draft (do not publish or send)'),
			value: 'draft',
		},
		{
			// TRANSLATORS Descriptive message shown in the publications management screen.
			label: t('communications', 'Active (publish and send when eligible)'),
			value: 'active',
		},
		{
			// TRANSLATORS Action or instruction in the publications management screen.
			label: t('communications', 'Paused (do not display or send)'),
			value: 'paused',
		},
	]
	const placementOptions = computed(() =>
		(
			catalog.value.automation?.placements || [
				// TRANSLATORS Short label for an option or field in the publications management screen.
				{ label: t('communications', 'Any supported page'), value: 'any' },
				{ label: 'Files', value: 'files' },
				{ label: 'Dashboard', value: 'dashboard' },
				// TRANSLATORS Location inside the Nextcloud user settings pages.
				{
					label: t('communications', 'Personal settings'),
					value: 'settings',
				},
				{
					// TRANSLATORS Location inside the Nextcloud administrator settings.
					label: t('communications', 'Administration settings'),
					value: 'admin_settings',
				},
			]
		).map((p) => ({ label: p.label, value: p.value })),
	)
	const targetModeOptions = [
		{
			value: 'area',
			// TRANSLATORS Only display the publication within one chosen Nextcloud application area.
			label: t('communications', 'Selected application area'),
		},
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ value: 'all', label: t('communications', 'All supported applications') },
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ value: 'only', label: t('communications', 'Only selected applications') },
		{
			value: 'exclude',
			// TRANSLATORS Short label for an option or field in the publications management screen.
			label: t('communications', 'All except selected applications'),
		},
	]
	const targetAppOptions = computed(() =>
		placementOptions.value.filter((p) => p.value !== 'any'),
	)
	const frequencyOptions = [
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ label: t('communications', 'On each occurrence'), value: 'event' },
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{
			label: t('communications', 'Once per publication and user'),
			value: 'once',
		},
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{
			label: t('communications', 'At most once every 24 hours'),
			value: 'daily',
		},
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ label: t('communications', 'At most once every 7 days'), value: 'weekly' },
		// TRANSLATORS Frequency option with a user-defined minimum delay.
		{ label: t('communications', 'Custom interval'), value: 'custom' },
	]
	const excludedMatchOptions = [
		{
			// TRANSLATORS Short label for an option or field in the publications management screen.
			label: t('communications', 'Exclude members of any group (OR)'),
			value: 'any',
		},
		{
			// TRANSLATORS Descriptive message shown in the publications management screen.
			label: t('communications', 'Exclude only members of every group (AND)'),
			value: 'all',
		},
	]
	const groupMatchOptions = [
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ label: t('communications', 'Any selected group (OR)'), value: 'any' },
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ label: t('communications', 'All selected groups (AND)'), value: 'all' },
	]
	const categoryOptions = [
		{
			// TRANSLATORS Type of announcement sent by an organization, not a paid promotion.
			label: t('communications', 'Institutional announcement'),
			value: 'institutional',
		},
		// TRANSLATORS Label for promotional announcements that may require recipient consent.
		{ label: t('communications', 'Sponsored content'), value: 'sponsored' },
	]
	const themeOptions = [
		// TRANSLATORS Informational visual theme or notification style, not a menu command.
		{ label: t('communications', 'Information'), value: 'info' },
		// TRANSLATORS Visual theme used for a successful or positive announcement.
		{ label: t('communications', 'Success'), value: 'success' },
		// TRANSLATORS Visual theme used for an important cautionary announcement.
		{ label: t('communications', 'Warning'), value: 'warning' },
		// TRANSLATORS Visual theme for a problem or failure notice.
		{ label: t('communications', 'Error'), value: 'error' },
		// TRANSLATORS Visual theme without a particular status or emphasis.
		{ label: t('communications', 'Neutral'), value: 'neutral' },
		// TRANSLATORS Visual theme configurable by the editor.
		{ label: t('communications', 'Custom'), value: 'custom' },
	]
	// Match the native NcNoteCard variants and icons used by Nextcloud.
	const bannerTypes = [
		{
			value: 'info',
			// TRANSLATORS Informational visual theme or notification style, not a menu command.
			label: t('communications', 'Information'),
			icon: mdiInformation,
		},
		{
			value: 'success',
			// TRANSLATORS Visual theme used for a successful or positive announcement.
			label: t('communications', 'Success'),
			icon: mdiCheckboxMarkedCircle,
		},
		// TRANSLATORS Visual theme used for an important cautionary announcement.
		{ value: 'warning', label: t('communications', 'Warning'), icon: mdiAlert },
		{
			value: 'error',
			// TRANSLATORS Visual theme for a problem or failure notice.
			label: t('communications', 'Error'),
			icon: mdiAlertDecagram,
		},
		{
			value: 'neutral',
			// TRANSLATORS Visual theme without a particular status or emphasis.
			label: t('communications', 'Neutral'),
			icon: mdiMinusCircleOutline,
		},
		{
			value: 'custom',
			// TRANSLATORS Visual theme configurable by the editor.
			label: t('communications', 'Custom'),
			icon: mdiPaletteOutline,
		},
	]
	const selectable = (option: { value: string }): string => option.value

	return {
		audienceOptions,
		publishOptions,
		placementOptions,
		targetModeOptions,
		targetAppOptions,
		frequencyOptions,
		excludedMatchOptions,
		groupMatchOptions,
		categoryOptions,
		themeOptions,
		bannerTypes,
		selectable,
	}
}
