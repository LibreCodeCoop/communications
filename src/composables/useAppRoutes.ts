// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { ref } from 'vue'
import { fetchAppRoutes } from '../services/automationRoutes'
import type { PageRouteOption } from '../types/communications'

export function useAppRoutes(load = fetchAppRoutes) {
	const routes = ref<PageRouteOption[]>([])
	const loading = ref(false)
	const failed = ref(false)
	let requestId = 0

	async function select(app: string | null): Promise<void> {
		const current = ++requestId
		routes.value = []
		failed.value = false
		loading.value = app !== null
		if (app === null) return
		try {
			const result = await load(app)
			if (requestId === current) routes.value = result
		} catch {
			if (requestId === current) failed.value = true
		} finally {
			if (requestId === current) loading.value = false
		}
	}

	return { routes, loading, failed, select }
}
