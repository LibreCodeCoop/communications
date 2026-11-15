// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, expect, it } from 'vitest'
import {
	intentChangePatch,
	validateWizardStep,
} from '../../src/services/campaignWizard'
import type { CampaignForm } from '../../src/types/communications'

const form = (changes: Partial<CampaignForm> = {}): CampaignForm =>
	({
		trigger_key: 'nextcloud.board.published',
		name: 'Draft',
		headline: 'News',
		message: 'Description',
		audience_type: 'all',
		cta_label: '',
		cta_url: '',
		starts_at: null,
		ends_at: null,
		...changes,
	}) as CampaignForm

const ready = {
	automationReady: true,
	hasDestination: true,
	groupCount: 1,
	userCount: 1,
}

describe('publication wizard business rules', () => {
	it('rejects an unavailable trigger before checking dates', () => {
		expect(
			validateWizardStep(1, form({ trigger_key: 'nextcloud.time.window' }), {
				...ready,
				automationReady: false,
			}),
		).toBe('trigger')
	})

	it('rejects missing or invalid scheduled dates and accepts a valid start', () => {
		expect(
			validateWizardStep(
				1,
				form({ trigger_key: 'nextcloud.time.window' }),
				ready,
			),
		).toBe('startDate')
		expect(
			validateWizardStep(
				1,
				form({
					trigger_key: 'nextcloud.time.window',
					starts_at: new Date('invalid'),
				}),
				ready,
			),
		).toBe('startDate')
		expect(
			validateWizardStep(
				1,
				form({
					trigger_key: 'nextcloud.time.window',
					starts_at: new Date(10000),
				}),
				ready,
			),
		).toBeNull()
	})

	it('validates content and recipients before allowing navigation', () => {
		expect(validateWizardStep(2, form({ name: ' ' }), ready)).toBe('content')
		expect(
			validateWizardStep(2, form({ audience_type: 'group' }), {
				...ready,
				groupCount: 0,
			}),
		).toBe('groups')
		expect(
			validateWizardStep(2, form({ audience_type: 'users' }), {
				...ready,
				userCount: 0,
			}),
		).toBe('users')
		expect(validateWizardStep(2, form({ cta_label: 'Open' }), ready)).toBe('cta')
		expect(
			validateWizardStep(2, form(), { ...ready, hasDestination: false }),
		).toBe('destination')
		expect(validateWizardStep(2, form(), ready)).toBeNull()
	})

	it('does not block reviewing a valid final step', () => {
		expect(
			validateWizardStep(3, form(), {
				...ready,
				automationReady: false,
				hasDestination: false,
			}),
		).toBeNull()
	})

	it('clears stale schedule and anonymous-access settings when switching intents', () => {
		expect(intentChangePatch('schedule', 'app')).toEqual({
			guest_visible: false,
			starts_at: null,
			ends_at: null,
		})
		expect(intentChangePatch('board', 'schedule')).toEqual({})
		expect(intentChangePatch(null, 'board')).toEqual({
			presentation: 'none',
			guest_visible: false,
		})
	})
})
