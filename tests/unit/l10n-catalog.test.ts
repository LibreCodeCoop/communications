// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { test, expect } from 'vitest'
import { readFileSync, readdirSync } from 'node:fs'
import { join } from 'node:path'
import { runInNewContext } from 'node:vm'

const app = 'communications'
const catalog = JSON.parse(readFileSync('l10n/pt_BR.json', 'utf8')) as {
	translations: Record<string, string>
	pluralForm: string
}

function* sources(dir: string): Generator<string> {
	for (const item of readdirSync(dir, { withFileTypes: true })) {
		const filename = join(dir, item.name)
		if (item.isDirectory()) yield* sources(filename)
		else if (/\.(php|js|ts|vue)$/.test(filename)) yield filename
	}
}

test('Brazilian Portuguese catalog covers explicit source messages', () => {
	const missing = []
	const regex =
		/(?:\bt\(\s*'communications'\s*,\s*'((?:\\'|[^'])*)'|(?:\$this->l10n|\$localized)->t\(\s*'((?:\\'|[^'])*)')/g
	for (const filename of [...sources('src'), ...sources('lib')]) {
		const code = readFileSync(filename, 'utf8')
		for (const match of code.matchAll(regex)) {
			const key = (match[1] || match[2]).replaceAll("\\'", "'")
			if (!Object.hasOwn(catalog.translations, key))
				missing.push(`${filename}: ${key}`)
		}
	}
	expect(missing, 'Every explicit source message must have a translation').toEqual(
		[],
	)
})

test('JSON and browser registration contain the same catalog', () => {
	let registered:
		| { name: string; entries: Record<string, string>; pluralForm: string }
		| undefined
	const script = readFileSync('l10n/pt_BR.js', 'utf8')
	runInNewContext(script, {
		OC: {
			L10N: {
				register: (
					name: string,
					entries: Record<string, string>,
					pluralForm: string,
				) => {
					registered = {
						name,
						entries: JSON.parse(JSON.stringify(entries)),
						pluralForm,
					}
				},
			},
		},
	})
	expect(registered?.name).toBe(app)
	expect(registered?.pluralForm).toBe(catalog.pluralForm)
	expect(registered?.entries).toEqual(catalog.translations)
})

test('dynamic source messages explain each placeholder to translators', () => {
	const missing: string[] = []
	const translations =
		/\bt\(\s*'communications'\s*,\s*'([^']*\{[A-Za-z_][A-Za-z0-9_]*\}[^']*)'/g
	for (const filename of sources('src')) {
		if (!/\.(?:vue|ts|js)$/.test(filename)) continue
		const code = readFileSync(filename, 'utf8')
		const lines = code.split(/\r?\n/)
		for (const match of code.matchAll(translations)) {
			const line = code.slice(0, match.index).split(/\r?\n/).length
			const previous = lines[line - 2]?.trim() ?? ''
			if (!/^(?:\/\/|<!--)\s*TRANSLATORS\b/.test(previous)) {
				missing.push(`${filename}:${line}: ${match[1]}`)
			}
		}
	}
	expect(missing, 'Place a TRANSLATORS hint directly before each placeholder translation').toEqual([])
})
