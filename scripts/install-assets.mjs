// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { copyFileSync, existsSync, mkdirSync, readdirSync } from 'node:fs'

for (const [dir, bundle, jsTarget, cssTarget] of [
  ['dist', 'management.js', 'js/management.js', 'css/management.css'],
  ['dist-delivery', 'campaigns.js', 'js/campaigns.js', 'css/campaigns.css'],
  ['dist-flow', 'flow.js', 'js/flow.js', 'css/flow.css'],
]) {
  if (!existsSync(dir + '/' + bundle)) throw new Error('Missing build output: '+dir+'/'+bundle)
  mkdirSync('js', { recursive: true })
  mkdirSync('css', { recursive: true })
  copyFileSync(dir+'/'+bundle, jsTarget)
  const css = readdirSync(dir).find(file=>file.endsWith('.css'))
  if (!css) throw new Error('Missing CSS build: '+dir)
  copyFileSync(dir+'/'+css, cssTarget)
  console.log('Installed '+jsTarget+' and '+cssTarget)
}
