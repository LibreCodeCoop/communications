// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import {
	existsSync,
	mkdirSync,
	mkdtempSync,
	readFileSync,
	rmSync,
	writeFileSync,
} from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { afterEach, test } from 'node:test'
import { fileURLToPath } from 'node:url'

const root = mkdtempSync(join(tmpdir(), 'communications-assets-test-'))
afterEach(() => rmSync(root, { recursive: true, force: true }))

function write(path, content) {
	mkdirSync(dirname(join(root, path)), { recursive: true })
	writeFileSync(join(root, path), content)
}

test('installs all five JS and four CSS outputs without stale assets', () => {
	for (const [dir, js, css] of [
		['dist', 'management.js', 'management.css'],
		['dist-delivery', 'campaigns.js', 'campaigns.css'],
		['dist-flow', 'flow.js', 'flow.css'],
	]) {
		write(`${dir}/${js}`, `compiled ${js}`)
		write(`${dir}/${css}`, `compiled ${css}`)
	}
	for (const file of ['guest.js', 'loader.js', 'guest.css']) {
		write(`src/${file}`, `source ${file}`)
	}
	write('js/stale.js', 'obsolete')
	write('css/stale.css', 'obsolete')

	const installer = fileURLToPath(
		new URL('../../scripts/install-assets.mjs', import.meta.url),
	)
	execFileSync(process.execPath, [installer], { cwd: root })

	for (const name of ['management.js', 'campaigns.js', 'flow.js']) {
		assert.equal(
			readFileSync(join(root, 'js', name), 'utf8'),
			`compiled ${name}`,
		)
	}
	for (const name of ['loader.js', 'guest.js']) {
		assert.equal(readFileSync(join(root, 'js', name), 'utf8'), `source ${name}`)
	}
	for (const name of ['management.css', 'campaigns.css', 'flow.css']) {
		assert.equal(
			readFileSync(join(root, 'css', name), 'utf8'),
			`compiled ${name}`,
		)
	}
	assert.equal(
		readFileSync(join(root, 'css/guest.css'), 'utf8'),
		'source guest.css',
	)
	assert(!existsSync(join(root, 'js/stale.js')))
	assert(!existsSync(join(root, 'css/stale.css')))
})
