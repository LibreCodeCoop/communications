// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { inject } from 'vue'
import type { InjectionKey } from 'vue'
import type { useCampaignAdminController } from './useCampaignAdminController'

export type CampaignEditorContext = ReturnType<typeof useCampaignAdminController>
export const campaignEditorContextKey: InjectionKey<CampaignEditorContext> = Symbol(
	'campaign-editor-context',
)

export function useCampaignEditorContext(): CampaignEditorContext {
	const context = inject(campaignEditorContextKey)
	if (!context)
		throw new Error('Campaign editor must be rendered inside CampaignAdmin')
	return context
}
