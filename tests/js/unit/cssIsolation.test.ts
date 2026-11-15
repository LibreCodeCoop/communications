/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { describe, expect, it } from 'vitest'
import { readFileSync, readdirSync } from 'node:fs'
import { join } from 'node:path'
import postcss from 'postcss'
import { compileString } from 'sass'

const styles = readdirSync('src').filter((name) => name.endsWith('.scss'))

describe('global stylesheet isolation', () => {
	for (const name of styles) {
		it(`${name} uses the Communications namespace for all selectors`, () => {
			const root = postcss.parse(
				compileString(readFileSync(join('src', name), 'utf8')).css,
			)
			const violations: string[] = []
			root.walkRules((rule) => {
				if (
					rule.parent?.type === 'atrule'
					&& 'name' in rule.parent
					&& /keyframes/i.test(String(rule.parent.name))
				) {
					return
				}
				for (const selector of rule.selectors) {
					if (
						!selector.trim().startsWith('.cc-')
						&& !selector.trim().startsWith('#content.app-communications')
						&& !selector.trim().startsWith('#communications-vue')
					) {
						violations.push(selector)
					}
				}
			})
			expect(
				violations,
				'Unscoped CSS can affect unrelated Nextcloud apps',
			).toEqual([])
		})
	}

	it('keeps all application component styles scoped to their Vue owners', () => {
		const componentPaths = (function collectVue(directory: string): string[] {
			return readdirSync(directory, { withFileTypes: true }).flatMap(
				(entry) => {
					const path = join(directory, entry.name)
					if (entry.isDirectory()) return collectVue(path)
					return entry.isFile() && path.endsWith('.vue') ? [path] : []
				},
			)
		})('src')
		expect(componentPaths.length).toBeGreaterThan(0)
		for (const componentPath of componentPaths) {
			const code = readFileSync(componentPath, 'utf8')
			expect(
				(code.match(/<style[^>]*>/g) ?? []).every((tag) =>
					tag.includes('scoped'),
				),
				`${componentPath} must not leak styles to other Nextcloud applications`,
			).toBe(true)
		}
		const globalCss = readFileSync('src/management.scss', 'utf8')
		expect(globalCss).toMatch(/#communications-vue/)
		expect(globalCss).not.toMatch(/\.cc-native-|\.cc-hub-|\.cc-automation-/)
	})
})
