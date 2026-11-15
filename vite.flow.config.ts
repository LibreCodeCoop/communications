// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
	plugins: [vue()],
	base: './',
	define: {
		appName: JSON.stringify('communications'),
		appVersion: JSON.stringify('0.29.0'),
		'process.env.NODE_ENV': JSON.stringify('production'),
		'process.env': '{}',
	},
	build: {
		outDir: 'dist-flow',
		emptyOutDir: true,
		cssCodeSplit: false,
		lib: {
			entry: 'src/flow.ts',
			name: 'LibreCodeContextualCampaignFlow',
			formats: ['iife'],
			fileName: () => 'flow.js',
		},
		rollupOptions: {
			output: { inlineDynamicImports: true, assetFileNames: 'flow[extname]' },
		},
	},
})
