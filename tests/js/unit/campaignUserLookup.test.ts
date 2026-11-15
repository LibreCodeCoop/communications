// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, expect, it, vi } from 'vitest'
vi.mock('../../../src/services/campaigns', () => ({ lookupCampaignUsers: vi.fn() }))
import { useCampaignUserLookup } from '../../../src/composables/useCampaignUserLookup'
import type { UserSelection } from '../../../src/types/communications'

function deferred<T>() {
	let resolve!: (value: T) => void
	let reject!: (error: Error) => void
	const promise = new Promise<T>((done, fail) => {
		resolve = done
		reject = fail
	})
	return { promise, resolve, reject }
}

describe('campaign recipient search', () => {
	it('does not query for incomplete searches', async () => {
		const fetch = vi.fn()
		const lookup = useCampaignUserLookup(fetch)
		await lookup.search('a')
		expect(fetch).not.toHaveBeenCalled()
		expect(lookup.options.value).toEqual([])
	})

	it('does not restore users from a previously closed editor', async () => {
		const pending = deferred<UserSelection[]>()
		const lookup = useCampaignUserLookup(
			vi.fn().mockReturnValue(pending.promise),
		)
		const previous = lookup.search('alice')
		lookup.clear()
		pending.resolve([{ id: 'alice', displayName: 'Alice' }])
		await previous
		expect(lookup.options.value).toEqual([])
	})

	it('keeps the newer result and ignores a stale failure', async () => {
		const old = deferred<UserSelection[]>()
		const fetch = vi
			.fn()
			.mockReturnValueOnce(old.promise)
			.mockResolvedValueOnce([{ id: 'bob', displayName: 'Bob' }])
		const lookup = useCampaignUserLookup(fetch)
		const pending = lookup.search('alice')
		await lookup.search('bob')
		old.reject(new Error('stale 503'))
		await expect(pending).resolves.toBeUndefined()
		expect(lookup.options.value.map((user) => user.id)).toEqual(['bob'])
	})

	it('propagates a current error so the editor can display it', async () => {
		const lookup = useCampaignUserLookup(
			vi.fn().mockRejectedValue(new Error('Forbidden')),
		)
		await expect(lookup.search('alice')).rejects.toThrow('Forbidden')
	})
})
