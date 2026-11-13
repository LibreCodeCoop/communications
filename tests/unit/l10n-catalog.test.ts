// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { test, expect } from 'vitest'
import { readFileSync, readdirSync } from 'node:fs'
import { join } from 'node:path'
import { runInNewContext } from 'node:vm'

const app = 'communications'
const catalog = JSON.parse(readFileSync('l10n/pt_BR.json', 'utf8')) as {translations: Record<string, string>; pluralForm: string}

function* sources(dir: string): Generator<string> {
  for (const item of readdirSync(dir, { withFileTypes: true })) {
    const filename = join(dir, item.name)
    if (item.isDirectory()) yield* sources(filename)
    else if (/\.(php|js|ts|vue)$/.test(filename)) yield filename
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
  expect(missing, 'Every explicit source message must have a translation').toEqual([])
})

test('JSON and browser registration contain the same catalog', () => {
  let registered: {name: string; entries: Record<string, string>; pluralForm: string} | undefined
  const script = readFileSync('l10n/pt_BR.js', 'utf8')
  runInNewContext(script, { OC: { L10N: { register: (name: string, entries: Record<string, string>, pluralForm: string) => {
    registered = { name, entries: JSON.parse(JSON.stringify(entries)), pluralForm }
  } } } })
  expect(registered?.name).toBe(app)
  expect(registered?.pluralForm).toBe(catalog.pluralForm)
  expect(registered?.entries).toEqual(catalog.translations)
})
