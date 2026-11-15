// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

// Signal only that eligibility must be rechecked; do not expose campaign or recipient data.
export function announceCampaignChanged(): void {
	try {
		if (typeof BroadcastChannel !== 'undefined') {
			const channel = new BroadcastChannel('communications')
			try {
				channel.postMessage({ event: 'changed' })
			} finally {
				channel.close()
			}
		}
	} catch {
		// Storage and BroadcastChannel may be unavailable in private browsing.
	}
	try {
		localStorage.setItem('communications-campaign-update', String(Date.now()))
	} catch {
		// Browser delivery adapters also refresh at their normal interval.
	}
}
