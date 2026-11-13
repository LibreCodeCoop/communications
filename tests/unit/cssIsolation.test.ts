/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { describe, expect, it } from 'vitest'
import { readFileSync, readdirSync } from 'node:fs'
import { join } from 'node:path'
import postcss from 'postcss'

const styles = readdirSync('src').filter(name => name.endsWith('.css'))

describe('global stylesheet isolation', () => {
  for (const name of styles) {
    it(`${name} uses the Communications namespace for all selectors`, () => {
      const root = postcss.parse(readFileSync(join('src', name), 'utf8'))
      const violations: string[] = []
      root.walkRules(rule => {
        if (rule.parent?.type === 'atrule' && ('name' in rule.parent) && /keyframes/i.test(String(rule.parent.name))) {
          return
        }
        for (const selector of rule.selectors) {
          if (!selector.trim().startsWith('.cc-') && !selector.trim().startsWith('#content.app-communications') && !selector.trim().startsWith('#communications-vue')) {
            violations.push(selector)
          }
        }
      })
      expect(violations, 'Unscoped CSS can affect unrelated Nextcloud apps').toEqual([])
    })
  }
})
