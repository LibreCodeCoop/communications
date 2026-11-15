// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { expect, test } from 'vitest'
import { readFileSync } from 'node:fs'
import { runInNewContext } from 'node:vm'

interface ClaimOptions {
	method: string
	credentials: string
	headers: Record<string, string>
	body: string
}
interface VisitPayload {
	page: string
	path: string
	newVisit: boolean
}

function runLoader(
	fetchCampaign: (url: string, options: ClaimOptions) => Promise<object>,
) {
	const events = new Map<string, () => void>()
	const requests: VisitPayload[] = []
	const timeouts: Array<{ fn: () => void; ms: number }> = []
	const location = {
		href: 'https://nextcloud.test/index.php/apps/files/',
		origin: 'https://nextcloud.test',
		pathname: '/index.php/apps/files/',
	}
	const document = {
		currentScript: {
			src: 'https://nextcloud.test/nc/custom_apps/communications/js/loader.js',
		},
		body: { id: 'body-user' },
		readyState: 'complete',
		visibilityState: 'visible',
		querySelector: () => null,
		createElement: () => ({}),
		head: { appendChild: () => undefined },
		addEventListener: (name: string, fn: () => void) => events.set(name, fn),
		removeEventListener: (name: string) => events.delete(name),
	}
	const browser = {
		OC: { webroot: '/nc', requestToken: 'csrf-test-token' },
		addEventListener: (name: string, fn: () => void) => events.set(name, fn),
		removeEventListener: (name: string) => events.delete(name),
	}
	runInNewContext(readFileSync('src/loader.js', 'utf8'), {
		document,
		window: browser,
		location,
		URL,
		console: { warn: () => undefined },
		fetch: async (url: string, options: ClaimOptions) => {
			expect(url).toBe('/nc/index.php/apps/communications/api/claim')
			expect(options.method).toBe('POST')
			expect(options.credentials).toBe('same-origin')
			expect(options.headers.requesttoken).toBe('csrf-test-token')
			requests.push(JSON.parse(options.body) as VisitPayload)
			return fetchCampaign(url, options)
		},
		setInterval: () => 2,
		clearInterval: () => undefined,
		setTimeout: (fn: () => void, ms: number) => {
			timeouts.push({ fn, ms })
			return 3
		},
		clearTimeout: () => undefined,
	})
	return { events, requests, timeouts }
}

const settle = async () => {
	await new Promise((resolve) => setTimeout(resolve, 0))
}

test('loader sends authenticated Nextcloud route context with first-visit flag', async () => {
	const result = runLoader(async () => ({
		ok: true,
		json: async () => ({ campaign: null }),
	}))
	await settle()
	expect(result.requests).toEqual([
		{ page: 'files', path: '/index.php/apps/files/', newVisit: true },
	])
	result.events.get('nextcloud.navigation.entered')?.()
	await settle()
	expect(result.requests.at(-1)).toEqual({
		page: 'files',
		path: '/index.php/apps/files/',
		newVisit: false,
	})
})

test('loader bounds an unsuccessful preflight to its first scheduled retry', async () => {
	let attempts = 0
	const result = runLoader(async () => {
		attempts++
		if (attempts === 1) throw new Error('Temporary Nextcloud token failure')
		return { ok: true, json: async () => ({ campaign: null }) }
	})
	await settle()
	expect(result.timeouts.map((entry) => entry.ms)).toEqual([1000])
	result.timeouts[0].fn()
	await settle()
	expect(result.requests).toHaveLength(2)
	expect(result.requests[1].newVisit).toBe(true)
	expect(result.timeouts).toHaveLength(1)
})
