// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { afterEach, describe, expect, it, vi } from 'vitest'
import { announceCampaignChanged } from '../../src/services/campaignUpdates'

describe('publication update notifications', () => {
	afterEach(() => {
		vi.unstubAllGlobals()
		vi.restoreAllMocks()
	})

	it('broadcasts a content-free invalidation message and the storage fallback', () => {
		const messages: unknown[] = []
		const close = vi.fn()
		const setItem = vi.fn()
		vi.stubGlobal(
			'BroadcastChannel',
			class {
				constructor(public name: string) {
					expect(name).toBe('communications')
				}
				postMessage(data: unknown) {
					messages.push(data)
				}
				close() {
					close()
				}
			},
		)
		vi.stubGlobal('localStorage', { setItem })
		vi.spyOn(Date, 'now').mockReturnValue(456)
		announceCampaignChanged()
		expect(messages).toEqual([{ event: 'changed' }])
		expect(close).toHaveBeenCalledOnce()
		expect(setItem).toHaveBeenCalledWith('communications-campaign-update', '456')
	})

	it('keeps the storage signal when BroadcastChannel is blocked', () => {
		const setItem = vi.fn()
		vi.stubGlobal(
			'BroadcastChannel',
			class {
				constructor() {
					throw new Error('Channel blocked')
				}
			},
		)
		vi.stubGlobal('localStorage', { setItem })
		announceCampaignChanged()
		expect(setItem).toHaveBeenCalledTimes(1)
	})

	it('does not crash if both notifications are unavailable', () => {
		vi.stubGlobal('BroadcastChannel', undefined)
		vi.stubGlobal('localStorage', {
			setItem: () => {
				throw new Error('Storage denied')
			},
		})
		expect(() => announceCampaignChanged()).not.toThrow()
	})
})
