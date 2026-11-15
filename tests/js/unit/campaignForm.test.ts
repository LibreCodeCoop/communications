// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, expect, it, vi } from 'vitest'
import {
	campaignRecordToForm,
	serializeCampaignForm,
	validateCampaignPayload,
} from '../../../src/services/campaignForm'
import type { CampaignSavePayload } from '../../../src/services/campaignForm'
import type { CampaignForm, CampaignRecord } from '../../../src/types/communications'

vi.mock('@nextcloud/l10n', () => ({ t: (_app: string, message: string) => message }))

function validForm(): CampaignForm {
	return {
		name: 'Roadmap',
		description: '',
		headline: 'Project update',
		message: 'Published update',
		cta_label: '',
		cta_url: '',
		trigger_key: 'nextcloud.board.published',
		page_route: '',
		page_filters: {},
		audience_type: 'all',
		audience_groups: [],
		audience_users: [],
		presentation: 'banner',
		status: 'draft',
		priority: 10,
		placement: 'any',
		app_target_mode: 'area',
		app_target_apps: [],
		frequency: 'event',
		min_interval_minutes: 0,
		max_total: 0,
		max_per_day: 0,
		category: 'institutional',
		theme: 'info',
		banner_color: '',
		banner_icon: 'auto',
		board_visible: false,
		guest_visible: false,
		comments_enabled: true,
		dismissible: true,
		notify_users: false,
		notify_activity: false,
		notify_email: false,
		gateway_channels: [],
		text_align: 'left',
		text_color: '',
		manual_order: 0,
		translated_content: {},
		group_match: 'any',
		excluded_match: 'any',
		excluded_groups: [],
		starts_at: null,
		ends_at: null,
	}
}

describe('pure campaign editor data contracts', () => {
	it('converts persisted timestamps without passing record IDs into editor form', () => {
		const record = {
			...validForm(),
			id: 42,
			created_at: 1,
			updated_at: 3,
			published_at: 4,
			audience_type: 'group',
			audience_groups: ['cooperados'],
			status: 'archived',
			presentation: 'none',
			starts_at: 100,
			ends_at: 200,
		} as CampaignRecord
		const form = campaignRecordToForm(record, ['cooperados'], '#fff')
		expect(form.audience_groups).toEqual(['cooperados'])
		expect(form.status).toBe('draft')
		expect(form.presentation).toBe('none')
		expect(form.starts_at).toEqual(new Date(100000))
		expect(form.ends_at).toEqual(new Date(200000))
		expect(form.banner_color).toBe('#fff')
		expect('id' in form).toBe(false)
	})

	it('serializes selected recipient and exclusion IDs rather than UI labels', () => {
		const form = {
			...validForm(),
			audience_type: 'group',
			starts_at: new Date(120500),
			ends_at: new Date(240000),
		}
		const payload = serializeCampaignForm(
			form,
			[
				{ id: 'cooperados', displayName: 'Cooperados' },
				{ id: 'equipe', displayName: 'Team' },
			],
			[{ id: 'alice', displayName: 'Alice' }],
			[{ id: 'blocked', displayName: 'Blocked' }],
		)
		expect(payload.audience_groups).toEqual(['cooperados', 'equipe'])
		expect(payload.audience_users).toEqual([])
		expect(payload.excluded_groups).toEqual(['blocked'])
		expect(payload.starts_at).toBe(120)
		expect(payload.ends_at).toBe(240)
	})

	it('does not persist a malformed editor date or an unrelated audience selection', () => {
		const form = {
			...validForm(),
			starts_at: new Date('invalid'),
			audience_type: 'users',
		}
		const result = serializeCampaignForm(
			form,
			[{ id: 'other', displayName: 'Other' }],
			[{ id: 'alice', displayName: 'Alice' }],
			[],
		)
		expect(result.starts_at).toBeNull()
		expect(result.audience_groups).toEqual([])
		expect(result.audience_users).toEqual(['alice'])
	})

	const invalidCases: Array<{
		label: string
		overrides: Partial<CampaignSavePayload>
		expected: string
		hasDestination?: boolean
	}> = [
		{
			label: 'empty headline',
			overrides: { headline: '' },
			expected: 'Internal name, title, and message are required.',
		},
		{
			label: 'empty trigger',
			overrides: { trigger_key: '' },
			expected: 'Choose when to deliver the publication.',
		},
		{
			label: 'missing recipient group',
			overrides: { audience_type: 'group', audience_groups: [] },
			expected: 'Select at least one group.',
		},
		{
			label: 'missing recipient user',
			overrides: { audience_type: 'users', audience_users: [] },
			expected: 'Select at least one user.',
		},
		{
			label: 'empty targeted app list',
			overrides: { app_target_mode: 'only', app_target_apps: [] },
			expected: 'Select at least one app for this display rule.',
		},
		{
			label: 'incomplete CTA',
			overrides: { cta_label: 'Open', cta_url: '' },
			expected: 'To display a button, enter its text and URL.',
		},
		{
			label: 'scheduled delivery without start',
			overrides: { trigger_key: 'nextcloud.time.window', starts_at: null },
			expected: 'The schedule needs a start date.',
		},
		{
			label: 'non-increasing time range',
			overrides: { starts_at: 200, ends_at: 100 },
			expected: 'The end date must be after the start date.',
		},
		{
			label: 'missing delivery',
			overrides: {},
			hasDestination: false,
			expected: 'Select at least one delivery method.',
		},
		{
			label: 'guest message with private email',
			overrides: { guest_visible: true, notify_email: true },
			expected: 'Public banners cannot send individual notifications.',
		},
	]

	for (const {
		label,
		overrides,
		expected,
		hasDestination = true,
	} of invalidCases) {
		it(`rejects ${label}`, () => {
			const payload = {
				...serializeCampaignForm(validForm(), [], [], []),
				...overrides,
			}
			expect(validateCampaignPayload(payload, hasDestination)).toBe(expected)
		})
	}

	it('allows a valid draft through to server-side authorization checks', () => {
		const payload = serializeCampaignForm(validForm(), [], [], [])
		expect(validateCampaignPayload(payload, true)).toBeNull()
	})
})
