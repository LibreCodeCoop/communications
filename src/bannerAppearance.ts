// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
// A stable, explicit icon catalog; only paths from @mdi/js are rendered.
// IDs are compatible with the names offered in Announcement Banner 2.8.1.
// Do not load SVG, CSS, JavaScript or network assets from campaign records.
import {
	mdiInformation,
	mdiInformationOutline,
	mdiCheckboxMarkedCircle,
	mdiAlert,
	mdiAlertDecagram,
	mdiAlertCircle,
	mdiAlertOctagon,
	mdiBellOutline,
	mdiBellRingOutline,
	mdiBullhornOutline,
	mdiCalendarBlankOutline,
	mdiCalendarClock,
	mdiClockAlertOutline,
	mdiShieldCheckOutline,
	mdiShieldAlertOutline,
	mdiStarOutline,
	mdiLock,
	mdiWrench,
	mdiProgressWrench,
	mdiBroom,
	mdiServer,
	mdiServerOff,
	mdiDatabase,
	mdiCloud,
	mdiCloudAlert,
	mdiWifiOff,
	mdiSync,
	mdiUpdate,
	mdiDownload,
	mdiUpload,
	mdiEmail,
	mdiFlag,
	mdiGift,
	mdiPartyPopper,
	mdiRocketLaunch,
	mdiNewBox,
	mdiLightningBolt,
	mdiHelpCircle,
	mdiCheckCircle,
	mdiCheckDecagram,
	mdiCloseCircle,
	mdiCloseOctagon,
} from '@mdi/js'
import { t } from '@nextcloud/l10n'

export const DEFAULT_BANNER_COLOR = '#6748AC'
export const BANNER_COLOR_PALETTE = [
	// TRANSLATORS Name of a color option in the publication banner palette.
	{ color: '#6748AC', name: t('communications', 'Purple') },
	// TRANSLATORS Name of a color option in the publication banner palette.
	{ color: '#0B78B9', name: t('communications', 'Blue') },
	// TRANSLATORS Name of a color option in the publication banner palette.
	{ color: '#157347', name: t('communications', 'Green') },
	// TRANSLATORS Name of a color option in the publication banner palette.
	{ color: '#B65E0A', name: t('communications', 'Orange') },
	// TRANSLATORS Name of a color option in the publication banner palette.
	{ color: '#B33045', name: t('communications', 'Red') },
	// TRANSLATORS Name of a color option in the publication banner palette.
	{ color: '#596579', name: t('communications', 'Gray') },
]
// Own labeling, native MDI vector paths, ID parity with the reference app.
const ICONS: ReadonlyArray<readonly [string, string, string]> = Object.freeze([
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['megaphone', t('communications', 'Megaphone'), mdiBullhornOutline],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['bullhorn', t('communications', 'Loudspeaker'), mdiBullhornOutline],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['bell', t('communications', 'Bell'), mdiBellOutline],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['bell-ring', t('communications', 'Ringing bell'), mdiBellRingOutline],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['alert', t('communications', 'Alert'), mdiAlert],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['alert-circle', t('communications', 'Circular alert'), mdiAlertCircle],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['alert-octagon', t('communications', 'Octagonal alert'), mdiAlertOctagon],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['information', t('communications', 'Information'), mdiInformation],
	[
		'information-outline',
		// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
		t('communications', 'Outlined information'),
		mdiInformationOutline,
	],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['check-circle', t('communications', 'Confirmation'), mdiCheckCircle],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['check-decagram', t('communications', 'Confirmation seal'), mdiCheckDecagram],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['close-circle', t('communications', 'Circular error'), mdiCloseCircle],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['close-octagon', t('communications', 'Octagonal error'), mdiCloseOctagon],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['shield-alert', t('communications', 'Warning shield'), mdiShieldAlertOutline],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['shield-check', t('communications', 'Verified shield'), mdiShieldCheckOutline],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['lock', t('communications', 'Lock'), mdiLock],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['wrench', t('communications', 'Tool'), mdiWrench],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['progress-wrench', t('communications', 'Maintenance'), mdiProgressWrench],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['broom', t('communications', 'Cleanup'), mdiBroom],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['server', t('communications', 'Server'), mdiServer],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['server-off', t('communications', 'Server unavailable'), mdiServerOff],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['database', t('communications', 'Database'), mdiDatabase],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['cloud', t('communications', 'Cloud'), mdiCloud],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['cloud-alert', t('communications', 'Cloud warning'), mdiCloudAlert],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['wifi-off', t('communications', 'Network disconnected'), mdiWifiOff],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['sync', t('communications', 'Synchronization'), mdiSync],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['update', t('communications', 'Update'), mdiUpdate],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['download', t('communications', 'Download'), mdiDownload],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['upload', t('communications', 'Upload'), mdiUpload],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['calendar', t('communications', 'Calendar'), mdiCalendarBlankOutline],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['calendar-clock', t('communications', 'Date and time'), mdiCalendarClock],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['clock-alert', t('communications', 'Urgent deadline'), mdiClockAlertOutline],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['email', t('communications', 'Email'), mdiEmail],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['flag', t('communications', 'Flag'), mdiFlag],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['star', t('communications', 'Star'), mdiStarOutline],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['gift', t('communications', 'Gift'), mdiGift],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['party-popper', t('communications', 'Celebration'), mdiPartyPopper],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['rocket-launch', t('communications', 'Launch'), mdiRocketLaunch],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['new-box', t('communications', 'New'), mdiNewBox],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['lightning-bolt', t('communications', 'Energy'), mdiLightningBolt],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['help-circle', t('communications', 'Help'), mdiHelpCircle],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['info', t('communications', 'Information (legacy)'), mdiInformation],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['success', t('communications', 'Success (legacy)'), mdiCheckboxMarkedCircle],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['warning', t('communications', 'Warning (legacy)'), mdiAlert],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['error', t('communications', 'Error (legacy)'), mdiAlertDecagram],
	// TRANSLATORS Name of a pictogram in the selectable publication banner icon list.
	['shield', t('communications', 'Shield (legacy)'), mdiShieldCheckOutline],
])
const ICON_PATHS: Readonly<Record<string, string>> = Object.freeze(
	Object.fromEntries(ICONS.map(([id, _label, path]) => [id, path])),
)
export const BANNER_ICON_OPTIONS = [
	// TRANSLATORS Option controlling whether a publication banner displays an icon.
	{ value: 'auto', label: t('communications', 'Icon based on message type') },
	// TRANSLATORS Option controlling whether a publication banner displays an icon.
	{ value: 'none', label: t('communications', 'No icon') },
	...ICONS.map(([value, label, path]) => ({ value, label, path })),
]
const SEMANTIC_TYPES = new Set(['info', 'success', 'warning', 'error'])

export interface BannerAppearanceInput {
	theme?: unknown
	banner_icon?: unknown
	banner_color?: unknown
}

export interface BannerAppearanceResult {
	type: 'info' | 'success' | 'warning' | 'error'
	noteClass: Record<string, boolean>
	style: Record<string, string>
	hideIcon: boolean
	iconPath: string | null
}

export function bannerAppearance(
	campaign: BannerAppearanceInput = {},
): BannerAppearanceResult {
	const theme = typeof campaign.theme === 'string' ? campaign.theme : 'default'
	const nativeType = (
		SEMANTIC_TYPES.has(theme) ? theme : 'info'
	) as BannerAppearanceResult['type']
	const choice =
		typeof campaign.banner_icon === 'string'
		&& BANNER_ICON_OPTIONS.some((i) => i.value === campaign.banner_icon)
			? campaign.banner_icon
			: 'auto'
	const rawColor =
		typeof campaign.banner_color === 'string' ? campaign.banner_color : ''
	const color = /^#[0-9a-fA-F]{3}$/.test(rawColor)
		? '#' + [...rawColor.slice(1)].map((char) => char + char).join('')
		: /^#[0-9a-fA-F]{6}(?:[0-9a-fA-F]{2})?$/.test(rawColor)
			? rawColor
			: DEFAULT_BANNER_COLOR
	const noIcon =
		choice === 'none'
		|| (choice === 'auto' && (theme === 'neutral' || theme === 'custom'))
	const background =
		'color-mix(in srgb, ' + color + ' 22%, var(--color-main-background))'
	const accent =
		'color-mix(in srgb, ' + color + ' 45%, var(--color-main-text) 55%)'
	const style = (
		theme === 'neutral'
			? {
					'--note-background': 'var(--color-main-background)',
					'--note-theme': 'var(--color-border-dark, var(--color-border))',
					'background-color': 'var(--color-main-background) !important',
					'border-inline-start':
						'2px solid var(--color-border) !important',
				}
			: theme === 'custom'
				? {
						'--cc-selected-color': color,
						'--note-background': background,
						'--note-theme': accent,
						'background-color': background + ' !important',
					}
				: {}
	) as Record<string, string>
	return {
		type: nativeType,
		noteClass: {
			'cc-note-neutral': theme === 'neutral',
			'cc-note-custom': theme === 'custom',
			'cc-note-no-icon': noIcon,
		},
		style,
		hideIcon: noIcon,
		iconPath: choice !== 'auto' && choice !== 'none' ? ICON_PATHS[choice] : null,
	}
}
