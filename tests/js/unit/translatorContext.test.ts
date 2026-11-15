// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { expect, test } from 'vitest'
import { readFileSync } from 'node:fs'

/** Check the context supplied to translators for short, ambiguous choice labels. */
function withoutHints(filename: string, start: string, end: string): string[] {
	const content = readFileSync(filename, 'utf8')
	const from = content.indexOf(start)
	const to = content.indexOf(end, from + start.length)
	if (from < 0 || to < 0) throw new Error('Unknown translation source section')
	const lines = content.slice(from, to).split(/\r?\n/)
	return lines.flatMap((line, index) => {
		const match = line.match(/\bt\('communications', '([^']+)'\)/)
		if (!match) return []
		return /\/\/ TRANSLATORS\b/.test(lines[index - 1] ?? '') ? [] : [match[1]]
	})
}

test('short status, visual-theme and icon choices explain their UI context', () => {
	const missing = [
		...withoutHints(
			'src/components/CampaignOverview.vue',
			'const statuses = [',
			'const search',
		),
		...withoutHints(
			'src/composables/useCampaignEditorOptions.ts',
			'const themeOptions = [',
			'const selectable',
		),
		...withoutHints(
			'src/bannerAppearance.ts',
			'export const BANNER_COLOR_PALETTE = [',
			'const ICON_PATHS',
		),
	]
	expect(
		missing,
		'Ambiguous choice labels need adjacent TRANSLATORS comments',
	).toEqual([])
})
