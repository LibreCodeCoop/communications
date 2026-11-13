/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @type {import('@stryker-mutator/api/core').PartialStrykerOptions} */
export default {
  packageManager: 'npm',
  testRunner: 'vitest',
  reporters: ['clear-text', 'progress', 'json'],
  coverageAnalysis: 'perTest',
  ignorePatterns: [
    '.ci/**', 'vendor/**', 'node_modules/**', '.git/**',
    'js/**', 'css/**', 'dist*/**', 'test-results/**',
    'playwright-report/**', 'coverage/**',
  ],
  mutate: ['src/linkSafety.ts'],
  vitest: {
    configFile: 'vitest.config.ts',
  },
  concurrency: 2,
  thresholds: {
    high: 85,
    low: 65,
    break: 85,
  },
}
