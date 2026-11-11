// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, it, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import { safeCtaLink } from '../../src/linkSafety.js'

describe('saved message links cannot execute scripts or leave the allowlist', () => {
  beforeEach(() => { globalThis.window = { OC: { webroot: '/nc' } } })
  for (const [name, input, expected] of [
    ['HTTP link', 'https://example.test/info', 'https://example.test/info'],
    ['internal path with webroot', '/apps/files/', '/nc/apps/files/'],
    ['root-relative path already scoped', '/nc/apps/files/', '/nc/apps/files/'],
    ['email', 'mailto:hi@example.test', 'mailto:hi@example.test'],
    ['telephone', 'tel:+5521999888777', 'tel:+5521999888777'],
  ]) it(`accepts ${name}`, () => assert.equal(safeCtaLink(input), expected))

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
  ]) it(`rejects ${name}`, () => assert.equal(safeCtaLink(input), ''))
})
