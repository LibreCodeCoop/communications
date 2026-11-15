// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { requestApi } from './api'
import type {
	GatewayPreferenceChannel,
	InboxAnnouncement,
	InboxComment,
	MentionOption,
} from '../types/communications'

export interface InboxPage {
	announcements?: InboxAnnouncement[]
	total?: number
	unreadTotal?: number
	hasMore?: boolean
}

export interface InboxComments {
	comments: InboxComment[]
	hasMore?: boolean
	nextBefore?: number | null
}

export function fetchInbox(
	page: number,
	search: string,
	filter: string,
): Promise<InboxPage> {
	const query = new URLSearchParams({
		page: String(page),
		search: search.trim(),
		filter,
	})
	return requestApi<InboxPage>('/api/board?' + query)
}

export async function fetchAnnouncement(id: number): Promise<InboxAnnouncement> {
	const data = await requestApi<{ announcement: InboxAnnouncement }>(
		'/api/board/' + id,
	)
	return data.announcement
}

export function markAnnouncementRead(id: number): Promise<object> {
	return requestApi('/api/board/' + id + '/read', 'POST', {})
}

export function fetchComments(
	id: number,
	before: number | null,
): Promise<InboxComments> {
	const suffix = before === null ? '' : '?before=' + before
	return requestApi<InboxComments>('/api/board/' + id + '/comments' + suffix)
}

export function addComment(id: number, message: string): Promise<object> {
	return requestApi('/api/board/' + id + '/comments', 'POST', { message })
}

export function deleteComment(id: number, commentId: number): Promise<object> {
	return requestApi('/api/board/' + id + '/comments/' + commentId, 'DELETE', {})
}

export async function fetchMentions(
	id: number,
	search: string,
): Promise<MentionOption[]> {
	const response = await requestApi<{ users?: MentionOption[] }>(
		'/api/board/' + id + '/mentions?' + new URLSearchParams({ search }),
	)
	return (response.users || []).slice(0, 10)
}

export async function fetchGatewayPreferences(): Promise<
	GatewayPreferenceChannel[]
> {
	const response = await requestApi<{ channels?: GatewayPreferenceChannel[] }>(
		'/api/gateway-preferences',
	)
	return response.channels || []
}

export function serializeGatewayPreferences(
	channels: readonly GatewayPreferenceChannel[],
): Record<string, { enabled: boolean; destination: string; sponsored: boolean }> {
	return Object.fromEntries(
		channels.map((channel) => [
			channel.id,
			{
				enabled: !!channel.enabled,
				destination: channel.enabled ? channel.destination.trim() : '',
				sponsored: !!channel.enabled && !!channel.sponsored,
			},
		]),
	)
}

export async function persistGatewayPreferences(
	channels: readonly GatewayPreferenceChannel[],
): Promise<GatewayPreferenceChannel[]> {
	const response = await requestApi<{ channels?: GatewayPreferenceChannel[] }>(
		'/api/gateway-preferences',
		'POST',
		{ channels: serializeGatewayPreferences(channels) },
	)
	return response.channels || []
}
