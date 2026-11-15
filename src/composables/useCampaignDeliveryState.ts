// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { computed } from 'vue'
import type { Ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { BANNER_COLOR_PALETTE, BANNER_ICON_OPTIONS } from '../bannerAppearance'
import {
	channelChangePatch,
	gatewayChannelOption,
	noticeChannelOption,
} from '../services/deliveryChannelSelection'
import type {
	CampaignForm,
	CampaignCatalog,
	ChannelNoticeField,
} from '../types/communications'

/**
 * Keep destination choices and their availability tied to the current draft.
 * The server catalog is authoritative about configured providers.
 */
export function useCampaignDeliveryState(
	form: Ref<CampaignForm>,
	catalog: Readonly<Ref<CampaignCatalog>>,
	bannerTypes: ReadonlyArray<{ value: string }>,
) {
	// TRANSLATORS Availability indicator when a messaging provider app is absent.
	const adminAppNotInstalledLabel = t('communications', 'App not installed')
	const hasAdditionalNotices = computed(
		() =>
			!!(
				form.value.notify_users
				|| form.value.notify_activity
				|| form.value.notify_email
				|| (form.value.gateway_channels || []).length
			),
	)
	const eventDrivenDelivery = computed(
		() =>
			!!form.value.trigger_key
			&& !['nextcloud.board.published', 'nextcloud.time.window'].includes(
				form.value.trigger_key,
			),
	)
	const hasDestination = computed(
		() =>
			['banner', 'modal'].includes(form.value.presentation)
			|| inboxCopy.value
			|| hasAdditionalNotices.value,
	)
	const destinationCount = computed(
		() =>
			(['banner', 'modal'].includes(form.value.presentation) ? 1 : 0)
			+ (inboxCopy.value ? 1 : 0)
			+ [
				form.value.notify_users,
				form.value.notify_activity,
				form.value.notify_email,
			].filter(Boolean).length
			+ (form.value.gateway_channels || []).length,
	)
	const inboxCopy = computed({
		get: () => !!form.value.board_visible,
		set: (enabled) => {
			if (form.value.guest_visible) return
			form.value.board_visible = !!enabled
		},
	})
	const deliveryChannelDefinitions: Array<{
		id: string
		field: ChannelNoticeField
		label: string
		description: string
	}> = [
		{
			id: 'notifications',
			field: 'notify_users',
			// TRANSLATORS Delivery channel using the Nextcloud notification bell.
			label: t('communications', 'Nextcloud Notifications'),
			// TRANSLATORS Descriptive message shown in the publications management screen.
			description: t(
				'communications',
				'Notification in the bell menu. Requires the Notifications app.',
			),
		},
		{
			id: 'activity',
			field: 'notify_activity',
			// TRANSLATORS Delivery channel recording an entry in the Nextcloud Activity app.
			label: t('communications', 'Activity feed'),
			// TRANSLATORS Descriptive message shown in the publications management screen.
			description: t(
				'communications',
				'Records the publication in the Activity feed without showing a banner or dialog.',
			),
		},
		{
			id: 'email',
			field: 'notify_email',
			label: 'E-mail',
			// TRANSLATORS Action or instruction in the publications management screen.
			description: t(
				'communications',
				'Sends the message to recipients with valid addresses.',
			),
		},
	]
	const gatewayDefinitions = [
		{
			id: 'gateway:sms',
			label: 'SMS',
			// TRANSLATORS Descriptive message shown in the publications management screen.
			description: t(
				'communications',
				'International phone number, e.g. +5521999999999.',
			),
		},
		{
			id: 'gateway:signal',
			label: 'Signal',
			// TRANSLATORS Descriptive message shown in the publications management screen.
			description: t('communications', 'Signal number, e.g. +5521999999999.'),
		},
		{
			id: 'gateway:telegram',
			label: 'Telegram',
			// TRANSLATORS Short label for an option or field in the publications management screen.
			description: t('communications', 'Private bot chat identifier.'),
		},
		{
			id: 'gateway:gowhatsapp',
			label: 'WhatsApp',
			// TRANSLATORS Descriptive message shown in the publications management screen.
			description: t(
				'communications',
				'WhatsApp number in international format.',
			),
		},
		{
			id: 'gateway:whatsappbusiness',
			label: 'WhatsApp Business',
			// TRANSLATORS Help text for WhatsApp Business; sending depends on provider templates and policies.
			description: t(
				'communications',
				"Subject to the provider's policies and approved templates.",
			),
		},
		{
			id: 'gateway:xmpp',
			label: 'XMPP',
			// TRANSLATORS Short label for an option or field in the publications management screen.
			description: t('communications', 'Recipient JID address.'),
		},
	]
	const channelLabels = {
		// TRANSLATORS Status shown when a channel has no eligible recipients.
		noRecipients: t('communications', 'No recipients'),
		// TRANSLATORS Status for a selected delivery channel whose provider is unavailable.
		selectedUnavailable: t('communications', 'Selected · unavailable'),
		// TRANSLATORS Provider availability status while integration checks run.
		checking: t('communications', 'Checking…'),
		// TRANSLATORS Explanation that public visitor banners do not use personal gateway addresses.
		guestGatewayHelp: t(
			'communications',
			'Anonymous public banners cannot send individual messages.',
		),
		// TRANSLATORS Status while an external gateway provider is checked.
		checkingGateway: t('communications', 'Checking provider availability.'),
		// TRANSLATORS Delivery channel status when there is no individual recipient.
		noIndividualDelivery: t('communications', 'No individual delivery'),
		// TRANSLATORS Explanation that anonymous banners cannot also send notifications.
		guestNoticeHelp: t(
			'communications',
			'Anonymous public banners do not send individual messages. Disable guest mode to configure recipients and channels.',
		),
		// TRANSLATORS Help while a Nextcloud delivery app is being checked.
		checkingNotice: t(
			'communications',
			'Availability of this channel is still being checked.',
		),
	}
	const gatewayOptions = computed(() =>
		gatewayDefinitions.map((definition) =>
			gatewayChannelOption(
				definition,
				form.value,
				catalog.value.deliveryChannels || [],
				channelLabels,
			),
		),
	)
	const gatewayGroup = computed(() =>
		(catalog.value.deliveryChannels || []).find(
			(item) => item.id === 'twofactor_gateway',
		),
	)
	const gatewayReadyCount = computed(
		() => gatewayOptions.value.filter((x) => x.ready).length,
	)

	const gatewayProviderCountLabel = computed(() => {
		if (!gatewayGroup.value?.installed) return adminAppNotInstalledLabel
		// TRANSLATORS {ready} is the number of configured messaging providers; {total} is the total number of available providers.
		return t('communications', '{ready} of {total} providers configured', {
			ready: gatewayReadyCount.value,
			total: gatewayOptions.value.length,
		})
	})

	const deliveryChannelOptions = computed(() =>
		deliveryChannelDefinitions.map((definition) =>
			noticeChannelOption(
				definition,
				form.value,
				catalog.value.deliveryChannels || [],
				channelLabels,
			),
		),
	)
	function setNoticeChannel(field: ChannelNoticeField, enabled: boolean): void {
		const channel = deliveryChannelDefinitions.find(
			(item) => item.field === field,
		)
		if (channel) onDeliveryChannelChange({ id: channel.id, enabled })
	}
	function onDeliveryChannelChange(change: {
		id: string
		enabled: boolean
	}): void {
		Object.assign(
			form.value,
			channelChangePatch(
				form.value,
				change,
				catalog.value.deliveryChannels,
				gatewayDefinitions.map((gateway) => gateway.id),
				deliveryChannelDefinitions,
			),
		)
	}
	const noticeUsers = computed({
		get: () => !!form.value.notify_users,
		set: (enabled) => setNoticeChannel('notify_users', enabled),
	})
	const noticeActivity = computed({
		get: () => !!form.value.notify_activity,
		set: (enabled) => setNoticeChannel('notify_activity', enabled),
	})
	const noticeEmail = computed({
		get: () => !!form.value.notify_email,
		set: (enabled) => setNoticeChannel('notify_email', enabled),
	})
	// All destinations use the same channel-style switch. Only these two
	// visual destinations are mutually exclusive, enforced by presentation.
	const visualDeliveryOptions = computed(() => [
		{
			id: 'banner',
			// TRANSLATORS Display mode showing a message inline on a Nextcloud page.
			label: t('communications', 'Contextual banner'),
			// TRANSLATORS Descriptive message shown in the publications management screen.
			description: t(
				'communications',
				'Non-intrusive notice on the page during the next eligible visit or event.',
			),
			selected: form.value.presentation === 'banner',
			selectable: true,
			availability: 'ready',
			status:
				form.value.presentation === 'banner'
					? // TRANSLATORS Status of a delivery method or visual destination selected by the editor.
						t('communications', 'Selected')
					: // TRANSLATORS Status of a delivery method or visual destination that can be used.
						t('communications', 'Available'),
			// TRANSLATORS Explanatory help text shown in the publications management screen.
			help: t(
				'communications',
				'The banner appears during navigation. Selecting it disables the modal but keeps other channels.',
			),
		},
		{
			id: 'modal',
			// TRANSLATORS Display mode showing a message in an overlay.
			label: t('communications', 'Modal dialog'),
			// TRANSLATORS Descriptive message shown in the publications management screen.
			description: t(
				'communications',
				'Dialog that requires interaction on the next eligible visit or event.',
			),
			selected: form.value.presentation === 'modal',
			selectable: !form.value.guest_visible,
			availability: form.value.guest_visible ? 'guest' : 'ready',
			status: form.value.guest_visible
				? // TRANSLATORS Empty state or unavailable-condition message in the publications management screen.
					t('communications', 'Not available to guests')
				: form.value.presentation === 'modal'
					? // TRANSLATORS Status of a delivery method or visual destination selected by the editor.
						t('communications', 'Selected')
					: // TRANSLATORS Status of a delivery method or visual destination that can be used.
						t('communications', 'Available'),
			// TRANSLATORS Explanatory help text shown in the publications management screen.
			help: t(
				'communications',
				'Modal and banner are alternatives. Selecting one disables the other; email, history, and notifications remain.',
			),
		},
	])
	const inboxDeliveryOption = computed(() => ({
		id: 'inbox',
		// TRANSLATORS The Communications app message Inbox, distinct from the Nextcloud Mail app.
		label: t('communications', 'Inbox'),
		// TRANSLATORS Descriptive message shown in the publications management screen.
		description: t(
			'communications',
			'Keeps the publication available in Nextcloud, with optional comments.',
		),
		selected: inboxCopy.value,
		selectable: !form.value.guest_visible,
		availability: form.value.guest_visible ? 'guest' : 'ready',
		status: form.value.guest_visible
			? // TRANSLATORS Empty state or unavailable-condition message in the publications management screen.
				t('communications', 'Not available to guests')
			: inboxCopy.value
				? // TRANSLATORS Status of a delivery method or visual destination selected by the editor.
					t('communications', 'Selected')
				: // TRANSLATORS Status of a delivery method or visual destination that can be used.
					t('communications', 'Available'),
		// TRANSLATORS Explanatory help text shown in the publications management screen.
		help: t(
			'communications',
			'Inbox is an independent destination. If disabled, messages sent through other channels open only their authorized details.',
		),
	}))

	const deliveryReview = computed(() => {
		const surfaces = []
		if (form.value.presentation === 'banner')
			// TRANSLATORS Display mode showing a message inline on a Nextcloud page.
			surfaces.push(t('communications', 'Contextual banner'))
		if (form.value.presentation === 'modal')
			// TRANSLATORS Display mode showing a message in an overlay.
			surfaces.push(t('communications', 'Modal dialog'))
		// TRANSLATORS The Communications app message Inbox, distinct from the Nextcloud Mail app.
		if (inboxCopy.value) surfaces.push(t('communications', 'Inbox'))

		if (form.value.notify_users)
			// TRANSLATORS Delivery channel using the Nextcloud notification bell.
			surfaces.push(t('communications', 'Nextcloud Notifications'))
		if (form.value.notify_activity)
			// TRANSLATORS Delivery channel recording an entry in the Nextcloud Activity app.
			surfaces.push(t('communications', 'Activity feed'))
		if (form.value.notify_email) surfaces.push('E-mail')
		for (const id of form.value.gateway_channels || []) {
			const gateway = gatewayDefinitions.find(
				(item) => item.id === 'gateway:' + id,
			)
			if (gateway) surfaces.push(gateway.label + ' (Two-Factor Gateway)')
		}
		// TRANSLATORS Empty state or unavailable-condition message in the publications management screen.
		return surfaces.join(' · ') || t('communications', 'No destination selected')
	})
	const selectedBannerType = computed(() =>
		bannerTypes.some((item) => item.value === form.value.theme)
			? form.value.theme
			: 'info',
	)
	const bannerIconOptions = BANNER_ICON_OPTIONS.map(({ value, label }) => ({
		value,
		label,
	}))
	const colorPickerPalette = BANNER_COLOR_PALETTE.map(({ color }) => color)

	return {
		hasAdditionalNotices,
		eventDrivenDelivery,
		hasDestination,
		destinationCount,
		inboxCopy,
		deliveryChannelDefinitions,
		gatewayDefinitions,
		channelLabels,
		gatewayOptions,
		gatewayGroup,
		gatewayReadyCount,
		gatewayProviderCountLabel,
		deliveryChannelOptions,
		setNoticeChannel,
		onDeliveryChannelChange,
		noticeUsers,
		noticeActivity,
		noticeEmail,
		visualDeliveryOptions,
		inboxDeliveryOption,
		deliveryReview,
		selectedBannerType,
		bannerIconOptions,
		colorPickerPalette,
		adminAppNotInstalledLabel,
	}
}
