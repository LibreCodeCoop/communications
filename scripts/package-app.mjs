// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { execFileSync } from 'node:child_process'
import {
	cpSync,
	existsSync,
	lstatSync,
	mkdirSync,
	mkdtempSync,
	readFileSync,
	rmSync,
} from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const appId = 'communications'
const packageEntries = [
	'appinfo',
	'lib',
	'templates',
	'img',
	'l10n',
	'js',
	'css',
	'LICENSE',
	'LICENSES',
]

export const requiredFiles = [
	'appinfo/info.xml',
	'lib/AppInfo/Application.php',
	'templates/main.php',
	'img/app.svg',
	'js/management.js',
	'js/campaigns.js',
	'js/flow.js',
	'js/loader.js',
	'js/guest.js',
	'css/management.css',
	'css/campaigns.css',
	'css/flow.css',
	'css/guest.css',
	'LICENSE',
]

export function verifyInstallableTree(root) {
	for (const file of requiredFiles) {
		const path = join(root, file)
		if (
			!existsSync(path)
			|| !lstatSync(path).isFile()
			|| lstatSync(path).size === 0
		) {
			throw new Error(`Missing or empty installable asset: ${file}`)
		}
	}
	const xml = readFileSync(join(root, 'appinfo/info.xml'), 'utf8')
	if (!xml.includes(`<id>${appId}</id>`)) {
		throw new Error('Packaged appinfo/info.xml has an unexpected app ID')
	}
	const version = xml.match(/<version>([^<]+)<\/version>/)?.[1]
	if (
		!version
		|| !/^[0-9]+\.[0-9]+\.[0-9]+(?:[-.][A-Za-z0-9.]+)?$/.test(version)
	) {
		throw new Error('App version is missing or invalid in appinfo/info.xml')
	}
	return version
}

export function createInstallablePackage(
	root,
	outputDir = join(root, 'build', 'artifacts'),
) {
	const version = verifyInstallableTree(root)
	const stagingRoot = mkdtempSync(join(tmpdir(), 'communications-package-'))
	try {
		const appRoot = join(stagingRoot, appId)
		mkdirSync(appRoot)
		for (const entry of packageEntries) {
			const source = join(root, entry)
			if (!existsSync(source))
				throw new Error(`Missing package entry: ${entry}`)
			cpSync(source, join(appRoot, entry), { recursive: true })
		}
		verifyInstallableTree(appRoot)

		mkdirSync(outputDir, { recursive: true })
		const archive = join(resolve(outputDir), `${appId}-${version}.tar.gz`)
		execFileSync('tar', ['-czf', archive, '-C', stagingRoot, appId])
		const entries = execFileSync('tar', ['-tzf', archive], { encoding: 'utf8' })
			.trimEnd()
			.split('\n')
		for (const file of requiredFiles) {
			if (!entries.includes(`${appId}/${file}`)) {
				throw new Error(`Missing archive member: ${file}`)
			}
		}
		const forbidden = [
			'src/',
			'tests/',
			'.github/',
			'node_modules/',
			'dist/',
			'vendor/',
			'build/',
		]
		if (
			entries.some((entry) =>
				forbidden.some((dir) => entry.startsWith(`${appId}/${dir}`)),
			)
		) {
			throw new Error(
				'Development-only files were included in the installable archive',
			)
		}
		return archive
	} finally {
		rmSync(stagingRoot, { recursive: true, force: true })
	}
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	try {
		const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
		console.log(`Created ${createInstallablePackage(root)}`)
	} catch (error) {
		console.error(error.message)
		process.exitCode = 1
	}
}
