// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import type { CampaignRecord } from '../types/communications'

export function effectiveCampaignStatus(
	campaign: CampaignRecord,
	now: number,
): string {
	if (campaign.status !== 'active') return campaign.status
	if (campaign.starts_at !== null && campaign.starts_at > now) return 'scheduled'
	if (campaign.ends_at !== null && campaign.ends_at <= now) return 'ended'
	return 'active'
}

export function selectCampaigns(
	campaigns: readonly CampaignRecord[],
	status: string,
	search: string,
	now: number,
): CampaignRecord[] {
	const term = search.trim().toLocaleLowerCase()
	return campaigns
		.filter((campaign) => {
			if (status === 'all' && campaign.status === 'archived') return false
			if (
				status !== 'all'
				&& effectiveCampaignStatus(campaign, now) !== status
			)
				return false
			return (
				!term
				|| [
					campaign.name,
					campaign.headline,
					campaign.description,
					campaign.trigger_key,
				]
					.join(' ')
					.toLocaleLowerCase()
					.includes(term)
			)
		})
		.sort(
			(a, b) =>
				(a.manual_order || 0) - (b.manual_order || 0)
				|| b.priority - a.priority
				|| b.id - a.id,
		)
}
