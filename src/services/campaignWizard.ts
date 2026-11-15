// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import type { CampaignForm, TriggerIntent } from '../types/communications'

export type WizardIssue =
	'trigger' | 'startDate' | 'content' | 'groups' | 'users' | 'cta' | 'destination'

export interface WizardContext {
	automationReady: boolean
	hasDestination: boolean
	groupCount: number
	userCount: number
}

export function validateWizardStep(
	step: number,
	form: CampaignForm,
	context: WizardContext,
): WizardIssue | null {
	if (step === 1) return validateTriggerStep(form, context)
	if (step === 2) return validateAudienceStep(form, context)
	return null
}

function validateTriggerStep(
	form: CampaignForm,
	context: WizardContext,
): WizardIssue | null {
	if (!context.automationReady) return 'trigger'
	if (
		form.trigger_key === 'nextcloud.time.window'
		&& (!(form.starts_at instanceof Date)
			|| Number.isNaN(form.starts_at.getTime()))
	) {
		return 'startDate'
	}
	return null
}

function validateAudienceStep(
	form: CampaignForm,
	context: WizardContext,
): WizardIssue | null {
	if (!form.name.trim() || !form.headline.trim() || !form.message.trim())
		return 'content'
	if (form.audience_type === 'group' && context.groupCount === 0) return 'groups'
	if (form.audience_type === 'users' && context.userCount === 0) return 'users'
	if (Boolean(form.cta_label.trim()) !== Boolean(form.cta_url.trim())) return 'cta'
	if (!context.hasDestination) return 'destination'
	return null
}

export function intentChangePatch(
	from: TriggerIntent | null,
	to: TriggerIntent,
): Partial<CampaignForm> {
	const patch: Partial<CampaignForm> = {}
	if (to === 'board' && from === null) patch.presentation = 'none'
	if (to !== 'schedule') patch.guest_visible = false
	if (from === 'schedule' && to !== 'schedule') {
		patch.starts_at = null
		patch.ends_at = null
	}
	return patch
}
