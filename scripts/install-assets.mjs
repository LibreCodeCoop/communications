// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { copyFileSync, existsSync, mkdirSync, readdirSync, rmSync } from 'node:fs'

// Both directories contain build output only; never preserve stale assets.
for (const dir of ['js', 'css']) rmSync(dir, { recursive: true, force: true })

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

// These lightweight, dependency-free browser adapters are maintained in src/.
// Keep their public filenames stable: Nextcloud registers 'guest' and 'loader'
// directly, and loader.js locates campaigns.js relative to its own URL.
for (const [source, target] of [
  ['src/guest.js', 'js/guest.js'],
  ['src/loader.js', 'js/loader.js'],
  ['src/guest.css', 'css/guest.css'],
]) {
  if (!existsSync(source)) throw new Error('Missing source asset: ' + source)
  copyFileSync(source, target)
  console.log('Installed ' + target)
}

