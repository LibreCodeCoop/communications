// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import type {
	CampaignForm,
	CatalogDeliveryChannel,
	ChannelNoticeField,
} from '../types/communications'

export interface NoticeChannelDefinition {
	id: string
	field: ChannelNoticeField
}

export interface ChannelChange {
	id: string
	enabled: boolean
}

export function channelChangePatch(
	form: CampaignForm,
	change: ChannelChange,
	available: readonly CatalogDeliveryChannel[],
	gateways: readonly string[],
	notices: readonly NoticeChannelDefinition[],
): Partial<CampaignForm> {
	const { id, enabled } = change
	if (id === 'banner' || id === 'modal') {
		return visualChange(form, id, enabled)
	}
	if (id === 'inbox') {
		return form.guest_visible ? {} : { board_visible: enabled }
	}
	if (id.startsWith('gateway:')) {
		return gatewayChange(form, id, enabled, available, gateways)
	}
	const notice = notices.find((channel) => channel.id === id)
	if (!notice || form.guest_visible || (enabled && !isReady(available, id)))
		return {}
	return { [notice.field]: enabled } as Partial<CampaignForm>
}

function isReady(channels: readonly CatalogDeliveryChannel[], id: string): boolean {
	return channels.some((channel) => channel.id === id && channel.ready === true)
}

function visualChange(
	form: CampaignForm,
	presentation: 'banner' | 'modal',
	enabled: boolean,
): Partial<CampaignForm> {
	const patch: Partial<CampaignForm> = {}
	if (enabled) {
		if (
			form.trigger_key === 'nextcloud.board.published'
			&& form.presentation === 'none'
			&& form.frequency === 'event'
		) {
			patch.frequency = 'once'
		}
		patch.presentation = presentation
	} else if (form.presentation === presentation) {
		patch.presentation = 'none'
	}
	if (presentation !== 'banner' || !enabled) patch.guest_visible = false
	return patch
}

function gatewayChange(
	form: CampaignForm,
	id: string,
	enabled: boolean,
	available: readonly CatalogDeliveryChannel[],
	gateways: readonly string[],
): Partial<CampaignForm> {
	if (!gateways.includes(id)) return {}
	if (enabled && (form.guest_visible || !isReady(available, id))) return {}
	const gateway = id.slice('gateway:'.length)
	const selected = form.gateway_channels || []
	return {
		gateway_channels: enabled
			? [...new Set([...selected, gateway])]
			: selected.filter((item) => item !== gateway),
	}
}

export interface ChannelAvailabilityLabels {
	noRecipients: string
	selectedUnavailable: string
	checking: string
	guestGatewayHelp: string
	checkingGateway: string
	noIndividualDelivery: string
	guestNoticeHelp: string
	checkingNotice: string
}

function channelAvailability(
	guest: boolean,
	meta: CatalogDeliveryChannel | undefined,
): string {
	return guest ? 'guest' : meta?.availability || 'loading'
}

function channelStatus(
	guest: boolean,
	selected: boolean,
	ready: boolean,
	meta: CatalogDeliveryChannel | undefined,
	labels: ChannelAvailabilityLabels,
	guestStatus: string,
): string {
	if (guest) return guestStatus
	if (selected && !ready) return labels.selectedUnavailable
	return meta?.status || labels.checking
}

function channelHelp(
	guest: boolean,
	meta: CatalogDeliveryChannel | undefined,
	guestHelp: string,
	checkingHelp: string,
): string {
	return guest ? guestHelp : meta?.help || checkingHelp
}

/** Derive the gateway display from published availability and recipient selection. */
export function gatewayChannelOption<T extends { id: string }>(
	definition: T,
	form: CampaignForm,
	channels: readonly CatalogDeliveryChannel[],
	labels: ChannelAvailabilityLabels,
) {
	const meta = channels.find((channel) => channel.id === definition.id)
	const gatewayId = definition.id.slice('gateway:'.length)
	const selected = (form.gateway_channels || []).includes(gatewayId)
	const ready = meta?.ready === true
	const selectable = !form.guest_visible && (ready || selected)
	const availability = channelAvailability(form.guest_visible, meta)
	const status = channelStatus(
		form.guest_visible,
		selected,
		ready,
		meta,
		labels,
		labels.noRecipients,
	)
	const help = channelHelp(
		form.guest_visible,
		meta,
		labels.guestGatewayHelp,
		labels.checkingGateway,
	)
	return {
		...definition,
		gatewayId,
		selected,
		ready,
		selectable,
		availability,
		status,
		help,
	}
}

/** Notice channels can be removed even when their upstream app is unavailable. */
export function noticeChannelOption<
	T extends { id: string; field: ChannelNoticeField | null },
>(
	definition: T,
	form: CampaignForm,
	channels: readonly CatalogDeliveryChannel[],
	labels: ChannelAvailabilityLabels,
) {
	const meta = channels.find((channel) => channel.id === definition.id)
	const selected = definition.field !== null && !!form[definition.field]
	const ready = meta?.ready === true && definition.field !== null
	const selectable = !form.guest_visible && (ready || selected)
	const availability = channelAvailability(form.guest_visible, meta)
	const status = channelStatus(
		form.guest_visible,
		selected,
		ready,
		meta,
		labels,
		labels.noIndividualDelivery,
	)
	const help = channelHelp(
		form.guest_visible,
		meta,
		labels.guestNoticeHelp,
		labels.checkingNotice,
	)
	return {
		...definition,
		selected,
		ready,
		selectable,
		availability,
		status,
		help,
	}
}
