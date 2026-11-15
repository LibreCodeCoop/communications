/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios, { isAxiosError } from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'

export type ApiMethod = 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH'

/** Nextcloud supplies CSRF/session headers, URL rewriting and typed responses. */
export async function requestApi<T extends object>(
	path: string,
	method: ApiMethod = 'GET',
	body?: unknown,
): Promise<T> {
	try {
		const { data } = await axios.request<T>({
			url: generateUrl(`/apps/communications${path}`),
			method,
			data: body,
			headers: { Accept: 'application/json' },
		})
		return data
	} catch (error) {
		if (isAxiosError(error) && error.response) {
			const payload: unknown = error.response.data
			const detail =
				payload && typeof payload === 'object'
					? 'error' in payload && typeof payload.error === 'string'
						? payload.error
						: 'message' in payload && typeof payload.message === 'string'
							? payload.message
							: undefined
					: undefined
			throw new Error(
				detail
					|| t('communications', 'HTTP failure {status}', {
						status: error.response.status,
					}),
				{ cause: error },
			)
		}
		throw error
	}
}
