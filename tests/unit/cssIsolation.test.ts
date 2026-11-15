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

  it('keeps all application component styles scoped to their Vue owners', () => {
    const componentPaths = [
      'src/AppHub.vue',
      'src/CampaignAdmin.vue',
      'src/CommunicationsBoard.vue',
      'src/CampaignDelivery.vue',
      'src/components/AutomationBuilder.vue',
      'src/components/CampaignPresentation.vue',
      'src/components/DeliveryChannelOption.vue',
      'src/components/FlowCampaignPicker.vue',
      'src/components/MarkdownEditor.vue',
    ]
    for (const componentPath of componentPaths) {
      const code = readFileSync(componentPath, 'utf8')
      expect(code, `${componentPath} must own its CSS`).toMatch(/<style[^>]+scoped[^>]*>/)
      expect(code.match(/<style[^>]*>/g)?.every(tag => tag.includes('scoped'))).toBe(true)
    }
    const globalCss = readFileSync('src/management.css', 'utf8')
    expect(globalCss).toMatch(/#communications-vue/)
    expect(globalCss).not.toMatch(/\.cc-native-|\.cc-hub-|\.cc-automation-/)
  })

})
