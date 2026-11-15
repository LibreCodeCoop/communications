// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, expect, it } from 'vitest'
import {
	limitAppOptions,
	searchAutomationApps,
	updateRouteFilter,
} from '../../../src/services/automationSelection'
import type {
	NativeApplication,
	PageRouteFilters,
} from '../../../src/types/communications'

const app = (
	id: string,
	actions = [
		{ key: id + '.action', label: 'Signed', description: 'Document signed' },
	],
): NativeApplication => ({
	id,
	label: id,
	description: 'Events for ' + id,
	keywords: ['workflow'],
	actions,
	canEvents: true,
	canPages: false,
})

describe('automation trigger selection', () => {
	const apps = [app('files'), app('dashboard'), app('calendar')]

	it('searches labels, descriptions, keywords and action descriptions', () => {
		expect(
			searchAutomationApps(apps, '  DASHBOARD  ').map((row) => row.id),
		).toEqual(['dashboard'])
		expect(searchAutomationApps(apps, 'document signed')).toHaveLength(3)
		expect(searchAutomationApps(apps, 'workflow')).toHaveLength(3)
		expect(searchAutomationApps(apps, 'no-such-action')).toEqual([])
		expect(searchAutomationApps(apps, '').map((row) => row.id)).toEqual([
			'files',
			'dashboard',
			'calendar',
		])
	})

	it('keeps a selected app visible when outside the bounded search results', () => {
		expect(
			limitAppOptions(apps, app('not-in-results'), 2).map((row) => row.id),
		).toEqual(['not-in-results', 'files'])
		expect(limitAppOptions(apps, apps[1], 2).map((row) => row.id)).toEqual([
			'files',
			'dashboard',
		])
		expect(apps).toHaveLength(3)
	})

	it('updates route constraints immutably and removes unconstrained filters', () => {
		const original: PageRouteFilters = {
			id: { operator: 'equals', value: 'abc' },
		}
		const digits = updateRouteFilter(original, 'id', 'operator', 'digits')
		expect(digits).toEqual({ id: { operator: 'digits', value: '' } })
		expect(original).toEqual({ id: { operator: 'equals', value: 'abc' } })
		expect(updateRouteFilter(digits, 'id', 'value', '123').id.value).toBe('123')
		expect(updateRouteFilter(original, 'id', 'operator', 'any')).toEqual({})
	})
})
