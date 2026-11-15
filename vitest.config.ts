/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { defineConfig } from 'vitest/config'

export default defineConfig({
	test: {
		include: ['tests/js/unit/**/*.test.ts'],
		environment: 'node',
		clearMocks: true,
		testTimeout: 10_000,
	},
})
