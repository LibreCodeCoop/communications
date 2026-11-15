// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { requestApi } from './api'
import type { CampaignSavePayload } from './campaignForm'
import type {
	CampaignCatalog,
	CampaignMetrics,
	CampaignRecord,
	UserSelection,
} from '../types/communications'

export async function fetchCampaigns(): Promise<CampaignRecord[]> {
	const response = await requestApi<{ campaigns?: CampaignRecord[] }>(
		'/api/campaigns',
	)
	return response.campaigns || []
}

export function fetchCampaignCatalog(): Promise<CampaignCatalog> {
	return requestApi<CampaignCatalog>('/api/catalog')
}

export function changeCampaignStatus(id: number, status: string): Promise<object> {
	return requestApi('/api/campaigns/' + id + '/status', 'POST', { status })
}

export function moveCampaign(
	id: number,
	direction: string,
	targetId: number | null,
): Promise<object> {
	return requestApi('/api/campaigns/' + id + '/move', 'POST', {
		direction,
		target_id: targetId,
	})
}

export async function duplicateCampaign(id: number): Promise<CampaignRecord> {
	const response = await requestApi<{ campaign: CampaignRecord }>(
		'/api/campaigns/' + id + '/duplicate',
		'POST',
		{},
	)
	return response.campaign
}

export function purgeCampaign(id: number, confirmName: string): Promise<object> {
	return requestApi('/api/campaigns/' + id + '/purge', 'POST', { confirmName })
}

export function archiveCampaign(id: number): Promise<object> {
	return requestApi('/api/campaigns/' + id, 'DELETE', {})
}

export async function persistCampaign(
	payload: CampaignSavePayload,
	id: number | null,
): Promise<CampaignRecord> {
	const response = await requestApi<{ campaign: CampaignRecord }>(
		id === null ? '/api/campaigns' : '/api/campaigns/' + id,
		id === null ? 'POST' : 'PUT',
		payload,
	)
	return response.campaign
}

export async function fetchCampaignMetrics(id: number): Promise<CampaignMetrics> {
	const response = await requestApi<{ metrics: CampaignMetrics }>(
		'/api/campaigns/' + id + '/metrics',
	)
	return response.metrics
}

export async function persistEditorGroups(groups: string[]): Promise<string[]> {
	const response = await requestApi<{ groups: string[] }>(
		'/api/editor-groups',
		'POST',
		{ groups },
	)
	return response.groups
}

export async function lookupCampaignUsers(search: string): Promise<UserSelection[]> {
	const response = await requestApi<{
		users?: Array<{ id: string; name: string }>
	}>('/api/users?search=' + encodeURIComponent(search))
	return (response.users || []).map((user) => ({
		id: user.id,
		displayName: user.name,
		user: user.id,
	}))
}
