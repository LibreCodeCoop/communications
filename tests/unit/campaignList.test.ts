// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, expect, it } from 'vitest'
import {
	effectiveCampaignStatus,
	selectCampaigns,
} from '../../src/services/campaignList'
import type { CampaignRecord } from '../../src/types/communications'

function row(id: number, changes: Partial<CampaignRecord> = {}): CampaignRecord {
	return {
		id,
		name: 'Campaign ' + id,
		headline: 'Notice',
		description: '',
		trigger_key: 'nextcloud.board.published',
		status: 'active',
		starts_at: null,
		ends_at: null,
		priority: 10,
		manual_order: 0,
		...changes,
	} as CampaignRecord
}

describe('publication list policies', () => {
	it('treats schedule boundaries consistently', () => {
		expect(effectiveCampaignStatus(row(1, { starts_at: 101 }), 100)).toBe(
			'scheduled',
		)
		expect(effectiveCampaignStatus(row(1, { starts_at: 100 }), 100)).toBe(
			'active',
		)
		expect(effectiveCampaignStatus(row(1, { ends_at: 100 }), 100)).toBe('ended')
		expect(
			effectiveCampaignStatus(row(1, { status: 'paused', ends_at: 50 }), 100),
		).toBe('paused')
	})

	it('excludes archived campaigns by default but allows an explicit archive filter', () => {
		const records = [row(1), row(2, { status: 'archived' })]
		expect(selectCampaigns(records, 'all', '', 100).map((c) => c.id)).toEqual([
			1,
		])
		expect(
			selectCampaigns(records, 'archived', '', 100).map((c) => c.id),
		).toEqual([2])
	})

	it('filters by effective status and searches without mutating incoming data', () => {
		const records = [
			row(1, { manual_order: 30, name: 'Closing' }),
			row(2, { manual_order: 0, headline: 'Planning', starts_at: 150 }),
			row(3, { manual_order: 0, priority: 20, headline: 'PLANNING' }),
		]
		expect(
			selectCampaigns(records, 'scheduled', '', 100).map((c) => c.id),
		).toEqual([2])
		expect(
			selectCampaigns(records, 'all', ' planning ', 100).map((c) => c.id),
		).toEqual([3, 2])
		expect(records.map((c) => c.id)).toEqual([1, 2, 3])
	})

	it('orders ties deterministically by priority and identifier', () => {
		const records = [row(1), row(4), row(2, { priority: 20 })]
		expect(selectCampaigns(records, 'all', '', 10).map((c) => c.id)).toEqual([
			2, 4, 1,
		])
	})
})
