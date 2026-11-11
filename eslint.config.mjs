// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import eslint from '@eslint/js'
import vue from 'eslint-plugin-vue'
import globals from 'globals'
import tsParser from '@typescript-eslint/parser'

export default [
  { ignores: ['node_modules/**', 'vendor/**', 'js/**', 'css/**', 'dist*/**', 'playwright-report/**', 'test-results/**'] },
  ...vue.configs['flat/essential'],
  { files: ['src/**/*.vue'], languageOptions: { parserOptions: { parser: tsParser } } },
  {
    files: ['src/**/*.{js,vue}', 'scripts/*.mjs', 'tests/**/*.{mjs,cjs,js}', 'vite*.js', 'playwright.config.mjs'],
    languageOptions: {
      ecmaVersion: 'latest',
      globals: { ...globals.browser, ...globals.node, OC: 'readonly' },
    },
    rules: {
      ...eslint.configs.recommended.rules,
      'no-unused-vars': 'off',
      'no-undef': 'error',
      'no-unreachable': 'error',
      'no-constant-condition': 'error',
    },
  },
]
