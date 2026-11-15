// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { CampaignCatalog, CampaignRecord } from '../types/communications'
import type { CampaignSavePayload } from '../services/campaignForm'
import {
	archiveCampaign,
	changeCampaignStatus,
	duplicateCampaign,
	fetchCampaignCatalog,
	fetchCampaigns,
	moveCampaign,
	persistCampaign,
	purgeCampaign,
} from '../services/campaigns'
import { announceCampaignChanged } from '../services/campaignUpdates'

export const useCampaignStore = defineStore('campaigns', () => {
	const campaigns = ref<CampaignRecord[]>([])
	const catalog = ref<CampaignCatalog>({
		groups: [],
		automation: { intents: [], sources: [], placements: [], applications: [] },
		deliveryChannels: [],
	})
	const loading = ref(true)
	let listRequest = 0

	async function refresh(): Promise<void> {
		const request = ++listRequest
		const rows = await fetchCampaigns()
		if (request === listRequest) campaigns.value = rows
	}

	async function load(): Promise<void> {
		loading.value = true
		try {
			const [metadata] = await Promise.all([fetchCampaignCatalog(), refresh()])
			catalog.value = metadata
		} finally {
			loading.value = false
		}
	}

	async function mutate<T>(action: () => Promise<T>): Promise<T> {
		const result = await action()
		try {
			await refresh()
		} finally {
			announceCampaignChanged()
		}
		return result
	}

	function updateStatus(id: number, status: string): Promise<object> {
		return mutate(() => changeCampaignStatus(id, status))
	}

	function reorder(
		id: number,
		direction: string,
		targetId: number | null = null,
	): Promise<object> {
		return mutate(() => moveCampaign(id, direction, targetId))
	}

	function duplicate(id: number): Promise<CampaignRecord> {
		return mutate(() => duplicateCampaign(id))
	}

	function archive(id: number): Promise<object> {
		return mutate(() => archiveCampaign(id))
	}

	function purge(id: number, name: string): Promise<object> {
		return mutate(() => purgeCampaign(id, name))
	}

	function save(
		payload: CampaignSavePayload,
		id: number | null,
	): Promise<CampaignRecord> {
		return mutate(() => persistCampaign(payload, id))
	}

	return {
		campaigns,
		catalog,
		loading,
		load,
		refresh,
		updateStatus,
		reorder,
		duplicate,
		archive,
		purge,
		save,
	}
})
