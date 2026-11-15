// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import eslint from '@eslint/js'
import vue from 'eslint-plugin-vue'
import globals from 'globals'
import tsParser from '@typescript-eslint/parser'

export default [
	{
		ignores: [
			// Dependencies and generated files are not source code.
			'node_modules/**',
			'vendor/**',
			'js/**',
			'css/**',
			'dist*/**',
			'playwright-report/**',
			'test-results/**',
		],
	},
	...vue.configs['flat/essential'],
	{
		files: [
			// TypeScript source and tests.
			'**/*.ts',
		],
		languageOptions: {
			parser: tsParser,
		},
	},
	{
		files: [
			// Vue single-file components.
			'src/**/*.vue',
		],
		languageOptions: {
			parserOptions: {
				parser: tsParser,
			},
		},
	},
	{
		files: [
			'src/**/*.{js,ts,vue}',
			'scripts/*.mjs',
			'tests/**/*.{ts,mjs,cjs,js}',
			'vite*.ts',
			'vitest.config.ts',
			'playwright.config.mjs',
		],
		languageOptions: {
			ecmaVersion: 'latest',
			globals: {
				...globals.browser,
				...globals.node,
				OC: 'readonly',
			},
		},
		rules: {
			...eslint.configs.recommended.rules,
			'no-unused-vars': 'off',
			'no-undef': 'error',
			'no-unreachable': 'error',
			'no-constant-condition': 'error',
			complexity: ['error', 15],
		},
	},
	{
		files: [
			// TypeScript and Vue identifiers are checked by their own parsers.
			'**/*.ts',
			'**/*.vue',
		],
		rules: {
			'no-undef': 'off',
		},
	},
]
