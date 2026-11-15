/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({ t: (_app: string, key: string) => key }))

import {
	bannerAppearance,
	BANNER_ICON_OPTIONS,
	DEFAULT_BANNER_COLOR,
} from '../../../src/bannerAppearance.ts'

describe('banner appearance contract', () => {
	it('uses the canonical theme without trusting an unknown saved icon', () => {
		const value = bannerAppearance({
			theme: 'success',
			banner_icon: '__invalid',
		})
		expect(value.type).toBe('success')
		expect(value.iconPath).toBe(null)
		expect(value.noteClass['cc-note-no-icon']).toBe(false)
	})

	it('removes the icon for neutral and explicit no-icon messages', () => {
		expect(bannerAppearance({ theme: 'neutral' }).hideIcon).toBe(true)
		expect(bannerAppearance({ banner_icon: 'none' }).hideIcon).toBe(true)
	})

	it('disallows a campaign-selected SVG or script in the icon catalog', () => {
		const values = BANNER_ICON_OPTIONS.map((x) => x.value)
		expect(values).not.toContain('data:image/svg+xml')
		expect(
			bannerAppearance({ banner_icon: 'javascript:alert(1)' }).iconPath,
		).toBeNull()
		expect(bannerAppearance({ banner_icon: '<svg />' }).iconPath).toBeNull()
	})

	it('accepts 3-, 6- and 8-digit colors but rejects injected CSS', () => {
		expect(
			bannerAppearance({ theme: 'custom', banner_color: '#abc' }).style[
				'--cc-selected-color'
			],
		).toBe('#aabbcc')
		expect(
			bannerAppearance({ theme: 'custom', banner_color: '#aabbccdd' }).style[
				'--cc-selected-color'
			],
		).toBe('#aabbccdd')
		expect(
			bannerAppearance({
				theme: 'custom',
				banner_color: 'red; background:url(x)',
			}).style['--cc-selected-color'],
		).toBe(DEFAULT_BANNER_COLOR)
	})
})
