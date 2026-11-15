// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { expect, test } from 'vitest'
import { readFileSync } from 'node:fs'
import { runInNewContext } from 'node:vm'

interface SyntheticElement {
	tag: string
	children: SyntheticElement[]
	textContent: string
	className: string
	href: string
	isConnected: boolean
	append: (...items: SyntheticElement[]) => void
	replaceChildren: (...items: SyntheticElement[]) => void
	remove: () => void
	setAttribute: (key: string, value: string) => void
	childElementCount: number
	[key: string]: unknown
}

function element(tag: string): SyntheticElement {
	const node = {
		tag,
		children: [] as SyntheticElement[],
		textContent: '',
		className: '',
		href: '',
		isConnected: false,
		attributes: {} as Record<string, string>,
		style: { setProperty: () => undefined },
		dataset: {} as Record<string, string>,
		append(...children: SyntheticElement[]) {
			this.children.push(...children)
		},
		replaceChildren(...children: SyntheticElement[]) {
			this.children = children
		},
		remove() {
			this.children = []
		},
		setAttribute(key: string, value: string) {
			this.attributes[key] = value
		},
		get childElementCount() {
			return this.children.length
		},
	}
	return node
}

function flatten(node: SyntheticElement): SyntheticElement[] {
	return [node, ...node.children.flatMap(flatten)]
}

async function renderPublicBanner(
	campaigns: Array<Record<string, unknown>>,
): Promise<SyntheticElement[]> {
	const body = {
		id: 'body-public',
		children: [] as SyntheticElement[],
		prepend(node: SyntheticElement) {
			node.isConnected = true
			this.children.unshift(node)
		},
	}
	const fetchRequests: string[] = []
	runInNewContext(readFileSync('src/guest.js', 'utf8'), {
		window: { OC: { webroot: '/nc' } },
		document: {
			body,
			readyState: 'complete',
			visibilityState: 'visible',
			createElement: element,
			addEventListener: () => undefined,
		},
		localStorage: { getItem: () => null },
		fetch: async (url: string) => {
			fetchRequests.push(url)
			return { ok: true, json: async () => ({ banners: campaigns }) }
		},
		setInterval: () => 1,
		URL,
	})
	await new Promise((resolve) => setTimeout(resolve, 0))
	expect(fetchRequests).toEqual([
		'/nc/index.php/apps/communications/api/public-banners',
	])
	return body.children.flatMap(flatten)
}

test('public banner renders safe CTA URLs but never unsafe scheme links', async () => {
	const common = {
		headline: 'Policy update',
		message: 'A public announcement',
		dismissible: false,
		type: 'info',
		version: 1,
	}
	const elements = await renderPublicBanner([
		{ ...common, id: 1, ctaLabel: 'Unsafe', ctaUrl: 'javascript:alert(1)' },
		{ ...common, id: 2, ctaLabel: 'Files', ctaUrl: '/apps/files/' },
		{
			...common,
			id: 3,
			ctaLabel: 'External',
			ctaUrl: 'https://example.test/info',
		},
		{
			...common,
			id: 4,
			ctaLabel: 'Credentials',
			ctaUrl: 'https://user:pass@example.test/',
		},
	])
	const links = elements.filter((item) => item.tag === 'a')
	expect(
		links.map((item) => ({ href: item.href, text: item.textContent })),
	).toEqual([
		{ href: '/nc/apps/files/', text: 'Files' },
		{ href: 'https://example.test/info', text: 'External' },
	])
	expect(links[1].target).toBe('_blank')
	expect(links[1].rel).toBe('noopener noreferrer')
})
