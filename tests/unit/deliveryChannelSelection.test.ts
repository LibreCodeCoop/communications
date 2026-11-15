// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, expect, it } from 'vitest'
import { channelChangePatch } from '../../src/services/deliveryChannelSelection'
import type {
	CampaignForm,
	CatalogDeliveryChannel,
} from '../../src/types/communications'

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
