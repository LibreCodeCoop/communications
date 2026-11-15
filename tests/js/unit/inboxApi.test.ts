// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { GatewayPreferenceChannel } from '../../../src/types/communications'

const requestApi = vi.hoisted(() => vi.fn())
vi.mock('../../../src/services/api', () => ({ requestApi }))

import {
	addComment,
	deleteComment,
	fetchComments,
	fetchGatewayPreferences,
	fetchInbox,
	fetchMentions,
	markAnnouncementRead,
	persistGatewayPreferences,
	serializeGatewayPreferences,
} from '../../../src/services/inbox'
import { lookupCampaignUsers } from '../../../src/services/campaigns'
import { fetchAppRoutes } from '../../../src/services/automationRoutes'

const gateway = (id: string, enabled: boolean): GatewayPreferenceChannel => ({
	id,
	label: id,
	installed: true,
	available: true,
	enabled,
	destination: '  identifier@example.com  ',
	sponsored: true,
})

describe('recipient-scoped API services', () => {
	beforeEach(() => {
		requestApi.mockReset()
		requestApi.mockResolvedValue({})
	})

	it('encodes search terms and scopes comment actions to the selected item', async () => {
		await fetchInbox(3, ' alpha & beta ', 'unread')
		expect(requestApi).toHaveBeenCalledWith(
			'/api/board?page=3&search=alpha+%26+beta&filter=unread',
		)
		await markAnnouncementRead(21)
		expect(requestApi).toHaveBeenCalledWith('/api/board/21/read', 'POST', {})
		await addComment(21, 'Hello')
		expect(requestApi).toHaveBeenCalledWith('/api/board/21/comments', 'POST', {
			message: 'Hello',
		})
		await deleteComment(21, 8)
		expect(requestApi).toHaveBeenCalledWith(
			'/api/board/21/comments/8',
			'DELETE',
			{},
		)
		await fetchComments(21, 19)
		expect(requestApi).toHaveBeenCalledWith('/api/board/21/comments?before=19')
	})

	it('limits mention suggestions and URL-encodes the query', async () => {
		requestApi.mockResolvedValueOnce({
			users: Array.from({ length: 20 }, (_, i) => ({ id: String(i) })),
		})
		expect((await fetchMentions(32, 'user+tag')).length).toBe(10)
		expect(requestApi).toHaveBeenCalledWith(
			'/api/board/32/mentions?search=user%2Btag',
		)
	})

	it('keeps disabled channel identifiers and consent out of outbound preferences', async () => {
		const channels = [gateway('sms', true), gateway('telegram', false)]
		expect(serializeGatewayPreferences(channels)).toEqual({
			sms: {
				enabled: true,
				destination: 'identifier@example.com',
				sponsored: true,
			},
			telegram: { enabled: false, destination: '', sponsored: false },
		})
		await persistGatewayPreferences(channels)
		expect(requestApi).toHaveBeenCalledWith('/api/gateway-preferences', 'POST', {
			channels: serializeGatewayPreferences(channels),
		})
		await fetchGatewayPreferences()
		expect(requestApi).toHaveBeenCalledWith('/api/gateway-preferences')
	})

	it('uses dedicated domain endpoints with encoded application and user identifiers', async () => {
		await lookupCampaignUsers('Ana & B')
		expect(requestApi).toHaveBeenCalledWith('/api/users?search=Ana%20%26%20B')
		await fetchAppRoutes('files_extra+')
		expect(requestApi).toHaveBeenCalledWith('/api/routes?app=files_extra%2B')
	})
})
