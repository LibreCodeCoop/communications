// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import type { NativeApplication, PageRouteFilters } from '../types/communications'

export function searchAutomationApps(
	applications: readonly NativeApplication[],
	search: string,
): NativeApplication[] {
	const term = search.trim().toLocaleLowerCase()
	if (!term) return [...applications]
	return applications.filter((app) =>
		[
			app.label,
			app.description,
			...(app.keywords || []),
			...(app.actions || []).flatMap((action) => [
				action.label,
				action.description,
			]),
		]
			.join(' ')
			.toLocaleLowerCase()
			.includes(term),
	)
}

export function limitAppOptions(
	applications: readonly NativeApplication[],
	selected: NativeApplication | null,
	maxOptions: number,
): NativeApplication[] {
	const first = applications.slice(0, maxOptions)
	return selected && !first.some((app) => app.id === selected.id)
		? [selected, ...first.slice(0, maxOptions - 1)]
		: first
}

export function updateRouteFilter(
	filters: PageRouteFilters,
	name: string,
	field: 'operator' | 'value',
	value: string,
): PageRouteFilters {
	const next = { ...filters }
	if (field === 'operator' && value === 'any') {
		delete next[name]
		return next
	}
	const rule = {
		...(next[name] || { operator: 'equals' as const, value: '' }),
		[field]: value,
	}
	if (field === 'operator' && value === 'digits') rule.value = ''
	next[name] = rule
	return next
}
