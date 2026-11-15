// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'

const read = (path: string): string => readFileSync(path, 'utf8')

describe('upstream Nextcloud integration', () => {
	it('provides contrasting monochrome app icons instead of a fixed brand color', () => {
		const light = read('img/app.svg')
		const dark = read('img/app-dark.svg')
		expect(light).toContain('fill="#fff"')
		expect(dark).toContain('fill="currentColor"')
		expect(light).not.toMatch(/#00806b|#158570/i)
		expect(dark).not.toMatch(/#[0-9a-f]{6}/i)
		expect(light.match(/d="(M[^"]+)"/)?.[1]).toBe(
			dark.match(/d="(M[^"]+)"/)?.[1],
		)
	})

	it('prepares Transifex resources and excludes generated code from source extraction', () => {
		const config = read('.tx/config')
		const ignore = read('.l10nignore')
		expect(config).toContain('[o:nextcloud:p:nextcloud:r:communications]')
		expect(config).toContain('translationfiles/<lang>/communications.po')
		expect(config).toContain('source_lang = en')
		for (const path of [
			'js/',
			'css/',
			'dist/',
			'node_modules/',
			'vendor/',
			'tests/',
		]) {
			expect(ignore).toContain(path)
		}
		expect(JSON.parse(read('l10n/pt_BR.json'))).toHaveProperty('translations')
	})
})
