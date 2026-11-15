// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, expect, it, vi } from 'vitest'
vi.mock('../../src/services/automationRoutes', () => ({ fetchAppRoutes: vi.fn() }))
import { useAppRoutes } from '../../src/composables/useAppRoutes'
import type { PageRouteOption } from '../../src/types/communications'

function deferred<T>() {
	let resolve!: (value: T) => void
	let reject!: (error: Error) => void
	const promise = new Promise<T>((done, fail) => {
		resolve = done
		reject = fail
	})
	return { promise, resolve, reject }
}

const route = (value: string): PageRouteOption => ({
	value,
	label: value,
	description: '',
})

describe('app route selection', () => {
	it('clears loading when the user switches to a non-route trigger', async () => {
		const first = deferred<PageRouteOption[]>()
		const load = vi.fn().mockReturnValue(first.promise)
		const state = useAppRoutes(load)
		const outstanding = state.select('files')
		expect(state.loading.value).toBe(true)
		await state.select(null)
		expect(state.loading.value).toBe(false)
		expect(state.routes.value).toEqual([])
		first.resolve([route('/apps/files')])
		await outstanding
		expect(state.routes.value).toEqual([])
	})

	it('uses the newest request when navigating between applications', async () => {
		const files = deferred<PageRouteOption[]>()
		const dashboard = deferred<PageRouteOption[]>()
		const load = vi
			.fn()
			.mockReturnValueOnce(files.promise)
			.mockReturnValueOnce(dashboard.promise)
		const state = useAppRoutes(load)
		const old = state.select('files')
		const recent = state.select('dashboard')
		dashboard.resolve([route('/apps/dashboard')])
		await recent
		files.resolve([route('/apps/files')])
		await old
		expect(state.routes.value.map((r) => r.value)).toEqual(['/apps/dashboard'])
		expect(state.loading.value).toBe(false)
	})

	it('does not allow an obsolete error to replace a newer successful selection', async () => {
		const first = deferred<PageRouteOption[]>()
		const load = vi
			.fn()
			.mockReturnValueOnce(first.promise)
			.mockResolvedValueOnce([route('current')])
		const state = useAppRoutes(load)
		const obsolete = state.select('files')
		await state.select('dashboard')
		first.reject(new Error('old error'))
		await obsolete
		expect(state.failed.value).toBe(false)
		expect(state.routes.value.map((r) => r.value)).toEqual(['current'])
	})

	it('exposes a current request failure and resets it on selection change', async () => {
		const load = vi.fn().mockRejectedValue(new Error('403'))
		const state = useAppRoutes(load)
		await state.select('files')
		expect(state.failed.value).toBe(true)
		expect(state.loading.value).toBe(false)
		await state.select(null)
		expect(state.failed.value).toBe(false)
	})
})
