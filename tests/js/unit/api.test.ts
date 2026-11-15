// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, expect, it, vi, beforeEach } from 'vitest'

const { request, errorPredicate } = vi.hoisted(() => ({
	request: vi.fn(),
	errorPredicate: vi.fn(
		(error: unknown) =>
			!!error && typeof error === 'object' && 'isAxiosError' in error,
	),
}))
vi.mock('@nextcloud/axios', () => ({
	default: { request },
	isAxiosError: errorPredicate,
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path: string) => '/nc/index.php' + path,
}))
vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, message: string, params: { status: number }) =>
		message.replace('{status}', String(params.status)),
}))

import { requestApi } from '../../../src/services/api.ts'

describe('generic Nextcloud API adapter', () => {
	beforeEach(() => {
		request.mockReset()
		errorPredicate.mockClear()
	})

	it('builds a Nextcloud-rooted URL and preserves typed response data', async () => {
		request.mockResolvedValue({ data: { campaigns: [{ id: 5 }] } })
		const data = await requestApi<{ campaigns: Array<{ id: number }> }>(
			'/api/campaigns',
		)
		expect(data.campaigns[0].id).toBe(5)
		expect(request).toHaveBeenCalledWith({
			url: '/nc/index.php/apps/communications/api/campaigns',
			method: 'GET',
			data: undefined,
			headers: { Accept: 'application/json' },
		})
	})

	it('delegates CSRF handling to the official Nextcloud Axios client', async () => {
		request.mockResolvedValue({ data: { created: true } })
		const payload = { audience_type: 'group', audience_groups: ['cooperados'] }
		expect(
			await requestApi<{ created: boolean }>(
				'/api/campaigns',
				'POST',
				payload,
			),
		).toEqual({ created: true })
		expect(request).toHaveBeenCalledWith(
			expect.objectContaining({ method: 'POST', data: payload }),
		)
	})

	it('preserves server error text for failed authorization', async () => {
		request.mockRejectedValue({
			isAxiosError: true,
			response: { status: 403, data: { error: 'Not authorized' } },
		})
		const error = await requestApi('/api/board').then(
			() => null,
			(failure: unknown) => failure,
		)
		expect(errorPredicate).toHaveBeenCalled()
		expect(error).toBeInstanceOf(Error)
		expect((error as Error).message).toBe('Not authorized')
	})

	it('reports status without leaking payloads for non-JSON HTTP errors', async () => {
		request.mockRejectedValue({
			isAxiosError: true,
			response: { status: 502, data: '<html>error</html>' },
		})
		const error = await requestApi('/api/campaigns').then(
			() => null,
			(failure: unknown) => failure,
		)
		expect(error).toBeInstanceOf(Error)
		expect((error as Error).message).toBe('HTTP failure 502')
	})

	it('does not obscure network or cancellation errors', async () => {
		const networkError = new Error('Connection refused')
		request.mockRejectedValue(networkError)
		const error = await requestApi('/api/board').then(
			() => null,
			(failure: unknown) => failure,
		)
		expect(error).toBe(networkError)
	})
})
