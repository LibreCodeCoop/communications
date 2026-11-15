// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { ref } from 'vue'
import { lookupCampaignUsers } from '../services/campaigns'
import type { UserSelection } from '../types/communications'

export function useCampaignUserLookup(fetchUsers = lookupCampaignUsers) {
	const options = ref<UserSelection[]>([])
	let generation = 0

	function clear(): void {
		generation++
		options.value = []
	}

	async function search(term: string): Promise<void> {
		const request = ++generation
		if (term.trim().length < 2) {
			options.value = []
			return
		}
		try {
			const found = await fetchUsers(term)
			if (generation === request) options.value = found
		} catch (error) {
			// An obsolete failure must not overwrite or interrupt the new editor state.
			if (generation === request) throw error
		}
	}

	return { options, search, clear }
}
