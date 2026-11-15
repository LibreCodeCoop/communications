// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { mkdirSync, mkdtempSync, rmSync, unlinkSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { afterEach, test } from 'node:test'
import {
	createInstallablePackage,
	requiredFiles,
	verifyInstallableTree,
} from '../../scripts/package-app.mjs'

const roots = []
afterEach(() => {
	for (const root of roots.splice(0))
		rmSync(root, { recursive: true, force: true })
})

function fixture() {
	const root = mkdtempSync(join(tmpdir(), 'communications-package-test-'))
	roots.push(root)
	for (const file of requiredFiles) {
		mkdirSync(dirname(join(root, file)), { recursive: true })
		writeFileSync(
			join(root, file),
			file === 'appinfo/info.xml'
				? '<info><id>communications</id><version>0.29.0</version></info>'
				: 'test fixture\n',
		)
	}
	mkdirSync(join(root, 'LICENSES'), { recursive: true })
	writeFileSync(join(root, 'LICENSES', 'AGPL-3.0-or-later.txt'), 'license fixture')
	mkdirSync(join(root, 'l10n'), { recursive: true })
	writeFileSync(join(root, 'l10n', 'pt_BR.js'), 'translation fixture')
	mkdirSync(join(root, 'src'), { recursive: true })
	writeFileSync(
		join(root, 'src', 'private.ts'),
		'development source not for distribution',
	)
	mkdirSync(join(root, 'tests'), { recursive: true })
	writeFileSync(
		join(root, 'tests', 'private.test.js'),
		'development test not for distribution',
	)
	return root
}

test('accepts a complete synthetic installable app', () => {
	assert.equal(verifyInstallableTree(fixture()), '0.29.0')
})

test('rejects missing generated entry points and empty guest styles', () => {
	const root = fixture()
	unlinkSync(join(root, 'js/loader.js'))
	assert.throws(() => verifyInstallableTree(root), /js\/loader\.js/)
	writeFileSync(join(root, 'js/loader.js'), 'test fixture')
	writeFileSync(join(root, 'css/guest.css'), '')
	assert.throws(() => verifyInstallableTree(root), /css\/guest\.css/)
})

test('creates a root-correct archive without development-only source', () => {
	const root = fixture()
	const archive = createInstallablePackage(root)
	const files = execFileSync('tar', ['-tzf', archive], { encoding: 'utf8' }).split(
		'\n',
	)
	assert(files.includes('communications/js/loader.js'))
	assert(files.includes('communications/css/guest.css'))
	assert(files.includes('communications/lib/AppInfo/Application.php'))
	assert(!files.some((file) => file.startsWith('communications/src/')))
	assert(!files.some((file) => file.startsWith('communications/tests/')))
})
