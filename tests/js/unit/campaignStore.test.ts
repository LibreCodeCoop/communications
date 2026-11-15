// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type {
	CampaignCatalog,
	CampaignRecord,
} from '../../../src/types/communications'

const mock = vi.hoisted(() => ({
	fetchCampaigns: vi.fn(),
	fetchCampaignCatalog: vi.fn(),
	changeCampaignStatus: vi.fn(),
	moveCampaign: vi.fn(),
	duplicateCampaign: vi.fn(),
	archiveCampaign: vi.fn(),
	purgeCampaign: vi.fn(),
	persistCampaign: vi.fn(),
	persistEditorGroups: vi.fn(),
	announce: vi.fn(),
}))
vi.mock('../../../src/services/campaigns', () => mock)
vi.mock('../../../src/services/campaignUpdates', () => ({
	announceCampaignChanged: mock.announce,
}))

import { useCampaignStore } from '../../../src/stores/campaigns'

const record = (id: number): CampaignRecord => ({ id }) as CampaignRecord
const catalog: CampaignCatalog = {
	groups: [],
	automation: { intents: [], sources: [], applications: [], placements: [] },
	deliveryChannels: [],
	canConfigureEditors: true,
}

function deferred<T>() {
	let resolve!: (value: T) => void
	let reject!: (error: Error) => void
	const promise = new Promise<T>((done, fail) => {
		resolve = done
		reject = fail
	})
	return { promise, resolve, reject }
}

describe('campaign management store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.resetAllMocks()
		mock.fetchCampaignCatalog.mockResolvedValue(catalog)
		mock.fetchCampaigns.mockResolvedValue([record(1)])
	})

	it('loads the catalog and the current list without mounting the editor', async () => {
		const store = useCampaignStore()
		expect(store.loading).toBe(true)
		await store.load()
		expect(store.loading).toBe(false)
		expect(store.catalog.canConfigureEditors).toBe(true)
		expect(store.campaigns.map((campaign) => campaign.id)).toEqual([1])
	})

	it('clears loading after failed initialization without inventing catalog data', async () => {
		const store = useCampaignStore()
		mock.fetchCampaignCatalog.mockRejectedValue(new Error('403'))
		await expect(store.load()).rejects.toThrow('403')
		expect(store.loading).toBe(false)
		expect(store.catalog.groups).toEqual([])
	})

	it('keeps the newest list when requests finish out of order', async () => {
		const first = deferred<CampaignRecord[]>()
		const second = deferred<CampaignRecord[]>()
		mock.fetchCampaigns.mockReset()
		mock.fetchCampaigns
			.mockReturnValueOnce(first.promise)
			.mockReturnValueOnce(second.promise)
		const store = useCampaignStore()
		const request1 = store.refresh()
		const request2 = store.refresh()
		second.resolve([record(7)])
		await request2
		first.resolve([record(3)])
		await request1
		expect(store.campaigns.map((campaign) => campaign.id)).toEqual([7])
	})

	it('broadcasts only after a successful mutation and refresh', async () => {
		const store = useCampaignStore()
		mock.changeCampaignStatus.mockResolvedValue({})
		await store.updateStatus(1, 'active')
		expect(mock.changeCampaignStatus).toHaveBeenCalledWith(1, 'active')
		expect(mock.fetchCampaigns).toHaveBeenCalledTimes(1)
		expect(mock.announce).toHaveBeenCalledTimes(1)
	})

	it('does not announce or refresh a rejected action', async () => {
		const store = useCampaignStore()
		mock.purgeCampaign.mockRejectedValue(new Error('403'))
		await expect(store.purge(4, 'Incorrect')).rejects.toThrow('403')
		expect(mock.fetchCampaigns).not.toHaveBeenCalled()
		expect(mock.announce).not.toHaveBeenCalled()
	})

	it('announces a committed change even when reloading the list fails', async () => {
		const store = useCampaignStore()
		mock.archiveCampaign.mockResolvedValue({})
		mock.fetchCampaigns.mockRejectedValue(new Error('Offline'))
		await expect(store.archive(9)).rejects.toThrow('Offline')
		expect(mock.announce).toHaveBeenCalledTimes(1)
	})

	it('keeps backend operations and returned objects intact', async () => {
		const store = useCampaignStore()
		mock.duplicateCampaign.mockResolvedValue(record(7))
		mock.persistCampaign.mockResolvedValue(record(8))
		mock.moveCampaign.mockResolvedValue({})
		await store.reorder(1, 'before', 2)
		expect(mock.moveCampaign).toHaveBeenCalledWith(1, 'before', 2)
		expect((await store.duplicate(1)).id).toBe(7)
		const payload = { name: 'A' } as Parameters<typeof store.save>[0]
		expect((await store.save(payload, null)).id).toBe(8)
		expect(mock.persistCampaign).toHaveBeenCalledWith(payload, null)
		expect(mock.announce).toHaveBeenCalledTimes(3)
	})
	it('updates editor permissions in the catalog only after persistence succeeds', async () => {
		const store = useCampaignStore()
		await store.load()
		mock.persistEditorGroups.mockResolvedValue(['reviewers'])
		await expect(store.updateEditorGroups(['reviewers'])).resolves.toEqual([
			'reviewers',
		])
		expect(mock.persistEditorGroups).toHaveBeenCalledWith(['reviewers'])
		expect(store.catalog.editorGroups).toEqual(['reviewers'])
		expect(mock.announce).not.toHaveBeenCalled()

		mock.persistEditorGroups.mockRejectedValue(new Error('403'))
		await expect(store.updateEditorGroups(['admins'])).rejects.toThrow('403')
		expect(store.catalog.editorGroups).toEqual(['reviewers'])
	})
})
