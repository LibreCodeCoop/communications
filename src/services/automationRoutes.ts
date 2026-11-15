// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { requestApi } from './api'
import type { PageRouteOption } from '../types/communications'

export async function fetchAppRoutes(app: string): Promise<PageRouteOption[]> {
	const result = await requestApi<{ routes?: PageRouteOption[] }>(
		'/api/routes?app=' + encodeURIComponent(app),
	)
	return result.routes || []
}
