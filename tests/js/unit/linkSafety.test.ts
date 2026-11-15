// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, it, beforeEach, expect } from 'vitest'
import { safeCtaLink } from '../../../src/linkSafety.ts'

describe('saved message links cannot execute scripts or leave the allowlist', () => {
	beforeEach(() => {
		Object.defineProperty(globalThis, 'window', {
			value: { OC: { webroot: '/nc' } },
			configurable: true,
		})
	})
	for (const [name, input, expected] of [
		['HTTP link', 'https://example.test/info', 'https://example.test/info'],
		['plain HTTP link', 'http://example.test/info', 'http://example.test/info'],
		['telephone without plus', 'tel:5551234', 'tel:5551234'],
		[
			'maximum permitted length',
			'https://example.test/' + 'a'.repeat(1879),
			'https://example.test/' + 'a'.repeat(1879),
		],
		['internal path with webroot', '/apps/files/', '/nc/apps/files/'],
		['root-relative path already scoped', '/nc/apps/files/', '/nc/apps/files/'],
		['email', 'mailto:hi@example.test', 'mailto:hi@example.test'],
		['telephone', 'tel:+5521999888777', 'tel:+5521999888777'],
	])
		it(`accepts ${name}`, () => expect(safeCtaLink(input)).toBe(expected))

	for (const [name, input] of [
		['javascript URL', 'javascript:alert(1)'],
		['javascript with leading whitespace', ' javascript:alert(1)'],
		['data URL', 'data:text/html,<script>'],
		['network-path reference', '//evil.test/'],
		['embedded credentials', 'https://a:b@example.test/'],
		['HTML delimiters', 'https://example.test/<script>'],
		['control characters', 'https://example.test/\u0001'],
		['backslash', 'https://example.test/\\x'],
		['very long URL', 'https://example.test/' + 'a'.repeat(2000)],
		[
			'one byte above allowed length',
			'https://example.test/' + 'a'.repeat(1880),
		],
		['non-string number', 1234],
		['null input', null],
		['empty input', ''],
		['credential username only', 'https://user@example.test/'],
		['embedded HTTP protocol not at start', 'prefixhttps://example.test/'],
		['invalid escaped host', 'http://%ZZ/'],
		['email with second at-sign', 'mailto:hi@example.test@attacker.test'],
		['phone with invalid trailing characters', 'tel:+5521999888777ext'],
		['phone scheme not at start', 'prefixtel:+5521999888777'],
		['email scheme not at start', 'prefixmailto:hi@example.test'],
	] satisfies Array<[string, unknown]>)
		it(`rejects ${name}`, () => expect(safeCtaLink(input)).toBe(''))

	it('keeps safe relative paths when the Nextcloud OC object is absent', () => {
		Object.defineProperty(globalThis, 'window', {
			value: {},
			configurable: true,
		})
		expect(safeCtaLink('/apps/files')).toBe('/apps/files')
	})
})
