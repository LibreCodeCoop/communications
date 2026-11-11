// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync, readdirSync } from 'node:fs'
import { join } from 'node:path'
import { runInNewContext } from 'node:vm'

const app = 'communications'
const catalog = JSON.parse(readFileSync('l10n/pt_BR.json', 'utf8'))

function* sources(dir) {
  for (const item of readdirSync(dir, { withFileTypes: true })) {
    const filename = join(dir, item.name)
    if (item.isDirectory()) yield* sources(filename)
    else if (/\.(php|js|vue)$/.test(filename)) yield filename
  }
}

test('Brazilian Portuguese catalog covers explicit source messages', () => {
  const missing = []
  const regex = /(?:\bt\(\s*'communications'\s*,\s*'((?:\\'|[^'])*)'|(?:\$this->l10n|\$localized)->t\(\s*'((?:\\'|[^'])*)')/g
  for (const filename of [...sources('src'), ...sources('lib')]) {
    const code = readFileSync(filename, 'utf8')
    for (const match of code.matchAll(regex)) {
      const key = (match[1] || match[2]).replaceAll("\\'", "'")
      if (!Object.hasOwn(catalog.translations, key)) missing.push(`${filename}: ${key}`)
    }
  }
  assert.deepEqual(missing, [], 'Every explicit source message must have a translation')
})

test('JSON and browser registration contain the same catalog', () => {
  let registered
  const script = readFileSync('l10n/pt_BR.js', 'utf8')
  runInNewContext(script, { OC: { L10N: { register: (name, entries, pluralForm) => {
    registered = { name, entries: JSON.parse(JSON.stringify(entries)), pluralForm }
  } } } })
  assert.equal(registered.name, app)
  assert.equal(registered.pluralForm, catalog.pluralForm)
  assert.deepEqual(registered.entries, catalog.translations)
})
