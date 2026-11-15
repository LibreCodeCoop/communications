// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, expect, it } from 'vitest'
import {
	channelChangePatch,
	gatewayChannelOption,
	noticeChannelOption,
} from '../../../src/services/deliveryChannelSelection'
import type {
	CampaignForm,
	CatalogDeliveryChannel,
} from '../../../src/types/communications'

const base = (changes: Partial<CampaignForm> = {}): CampaignForm =>
	({
		trigger_key: 'nextcloud.board.published',
		presentation: 'none',
		frequency: 'event',
		board_visible: false,
		guest_visible: false,
		gateway_channels: ['sms'],
		notify_email: false,
		...changes,
	}) as CampaignForm
const availability = (id: string, ready: boolean): CatalogDeliveryChannel => ({
	id,
	ready,
	availability: ready ? 'ready' : 'missing_app',
	status: '',
	help: '',
})
const channels = [
	availability('gateway:sms', true),
	availability('gateway:telegram', false),
	availability('email', true),
]
const gateways = ['gateway:sms', 'gateway:telegram']
const notices = [{ id: 'email', field: 'notify_email' as const }]

function apply(form: CampaignForm, id: string, enabled: boolean, status = channels) {
	return channelChangePatch(form, { id, enabled }, status, gateways, notices)
}

describe('editor delivery channel business rules', () => {
	it('defaults the first immediate visual destination to once per user', () => {
		expect(apply(base(), 'banner', true)).toEqual({
			presentation: 'banner',
			frequency: 'once',
		})
		expect(apply(base({ frequency: 'daily' }), 'modal', true)).toEqual({
			presentation: 'modal',
			guest_visible: false,
		})
	})

	it('keeps modal and banner mutually exclusive and clears guest visibility on switching', () => {
		expect(
			apply(
				base({ presentation: 'banner', guest_visible: true }),
				'modal',
				true,
			),
		).toEqual({
			presentation: 'modal',
			guest_visible: false,
		})
		expect(
			apply(
				base({ presentation: 'banner', guest_visible: true }),
				'banner',
				false,
			),
		).toEqual({
			presentation: 'none',
			guest_visible: false,
		})
	})

	it('does not make Inbox visible to guests or allow unknown channel activation', () => {
		expect(apply(base({ guest_visible: true }), 'inbox', true)).toEqual({})
		expect(apply(base(), 'inbox', true)).toEqual({ board_visible: true })
		expect(apply(base(), 'unrecognized', true)).toEqual({})
	})

	it('allows opting out of unavailable gateways while blocking new opt-ins', () => {
		expect(apply(base(), 'gateway:telegram', true)).toEqual({})
		expect(apply(base(), 'gateway:sms', false)).toEqual({ gateway_channels: [] })
		expect(apply(base(), 'gateway:telegram', false)).toEqual({
			gateway_channels: ['sms'],
		})
		expect(apply(base(), 'gateway:unknown', true)).toEqual({})
	})

	it('deduplicates selected gateways and rejects guest outbound delivery', () => {
		expect(apply(base(), 'gateway:sms', true)).toEqual({
			gateway_channels: ['sms'],
		})
		expect(apply(base({ guest_visible: true }), 'gateway:sms', true)).toEqual({})
	})

	it('allows removing a notification after the app becomes unavailable', () => {
		expect(apply(base(), 'email', true)).toEqual({ notify_email: true })
		expect(apply(base(), 'email', true, [])).toEqual({})
		expect(apply(base({ notify_email: true }), 'email', false, [])).toEqual({
			notify_email: false,
		})
		expect(apply(base({ guest_visible: true }), 'email', true)).toEqual({})
	})
})

const labels = {
	noRecipients: 'No recipients',
	selectedUnavailable: 'Selected · unavailable',
	checking: 'Checking…',
	guestGatewayHelp: 'Guest banners have no personal transport',
	checkingGateway: 'Checking gateway',
	noIndividualDelivery: 'No individual delivery',
	guestNoticeHelp: 'Guest banners cannot notify individuals',
	checkingNotice: 'Checking notice channel',
}

describe('delivery channel availability presentation', () => {
	it('offers available gateways and preserves selected gateways', () => {
		const gateway = gatewayChannelOption(
			{ id: 'gateway:sms', label: 'SMS' },
			base(),
			channels,
			labels,
		)
		expect(gateway).toMatchObject({
			id: 'gateway:sms',
			gatewayId: 'sms',
			selected: true,
			ready: true,
			selectable: true,
			availability: 'ready',
		})
	})

	it('does not advertise unavailable or anonymous guest gateway delivery', () => {
		const unavailable = gatewayChannelOption(
			{ id: 'gateway:telegram' },
			base({ gateway_channels: ['telegram'] }),
			channels,
			labels,
		)
		expect(unavailable).toMatchObject({
			selected: true,
			ready: false,
			selectable: true,
			status: 'Selected · unavailable',
		})
		const guest = gatewayChannelOption(
			{ id: 'gateway:sms' },
			base({ guest_visible: true }),
			channels,
			labels,
		)
		expect(guest).toMatchObject({
			selectable: false,
			availability: 'guest',
			status: 'No recipients',
			help: 'Guest banners have no personal transport',
		})
	})

	it('allows removing selected unavailable notice channels but not adding them', () => {
		const definition = { id: 'email', field: 'notify_email' as const }
		const unavailable = noticeChannelOption(
			definition,
			base({ notify_email: true }),
			[],
			labels,
		)
		expect(unavailable).toMatchObject({
			selected: true,
			ready: false,
			selectable: true,
			status: 'Selected · unavailable',
		})
		const unselected = noticeChannelOption(definition, base(), [], labels)
		expect(unselected.selectable).toBe(false)
		const guest = noticeChannelOption(
			definition,
			base({ notify_email: true, guest_visible: true }),
			channels,
			labels,
		)
		expect(guest).toMatchObject({
			selected: true,
			selectable: false,
			availability: 'guest',
			status: 'No individual delivery',
			help: 'Guest banners cannot notify individuals',
		})
	})
})
