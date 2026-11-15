// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { computed, ref } from 'vue'
import type { Ref, ComputedRef } from 'vue'
import { t } from '@nextcloud/l10n'
import { intentChangePatch, validateWizardStep } from '../services/campaignWizard'
import type { WizardIssue } from '../services/campaignWizard'
import { mdiApps, mdiMessageTextOutline, mdiCheckCircleOutline } from '@mdi/js'
import type {
	CampaignForm,
	CampaignCatalog,
	GroupSelection,
	UserSelection,
	TriggerIntent,
} from '../types/communications'

/** Sequential wizard navigation and validation, isolated from server mutations. */
export function useCampaignWizardState(
	form: Ref<CampaignForm>,
	catalog: Readonly<Ref<CampaignCatalog>>,
	hasDestination: ComputedRef<boolean>,
	selectedGroups: Ref<GroupSelection[]>,
	selectedUsers: Ref<UserSelection[]>,
) {
	const errorMessage = ref('')
	const wizardStep = ref(1)
	const wizardSteps = [
		{
			number: 1,
			// TRANSLATORS Short label for an option or field in the publications management screen.
			label: t('communications', 'When to publish'),
			// TRANSLATORS Short title of the wizard step for when a publication is delivered.
			shortLabel: t('communications', 'Timing'),
			icon: mdiApps,
		},
		{
			number: 2,
			// TRANSLATORS Short label for an option or field in the publications management screen.
			label: t('communications', 'Message and audience'),
			// TRANSLATORS Short title of the wizard step for message text and recipients.
			shortLabel: t('communications', 'Message'),
			icon: mdiMessageTextOutline,
		},
		{
			number: 3,
			// TRANSLATORS Short label for an option or field in the publications management screen.
			label: t('communications', 'Review and publish'),
			// TRANSLATORS Short title of the wizard review and activation step.
			shortLabel: t('communications', 'Publish'),
			icon: mdiCheckCircleOutline,
		},
	]
	const nextStepLabel = computed(() =>
		wizardStep.value === 1
			? // TRANSLATORS Action or instruction in the publications management screen.
				t('communications', 'Continue: Message and audience')
			: // TRANSLATORS Action or instruction in the publications management screen.
				t('communications', 'Continue: Review and publish'),
	)

	function wizardError(issue: WizardIssue): string {
		switch (issue) {
			case 'trigger':
				// TRANSLATORS Action or instruction in the publications management screen.
				return t('communications', 'Choose when to deliver the publication.')
			case 'startDate':
				// TRANSLATORS Action or instruction in the publications management screen.
				return t('communications', 'Enter the scheduled start date.')
			case 'content':
				// TRANSLATORS Action or instruction in the publications management screen.
				return t(
					'communications',
					'Enter the internal name, title, and message before continuing.',
				)
			case 'groups':
				// TRANSLATORS Action or instruction in the publications management screen.
				return t('communications', 'Select at least one recipient group.')
			case 'users':
				// TRANSLATORS Action or instruction in the publications management screen.
				return t('communications', 'Select at least one recipient user.')
			case 'cta':
				// TRANSLATORS Descriptive message shown in the publications management screen.
				return t(
					'communications',
					'To display a button, enter its text and URL.',
				)
			case 'destination':
				// TRANSLATORS Action or instruction in the publications management screen.
				return t('communications', 'Select at least one delivery method.')
		}
	}
	function checkWizardStep(step: number): boolean {
		const problem = validateWizardStep(step, form.value, {
			automationReady: automationReady.value,
			hasDestination: hasDestination.value,
			groupCount: selectedGroups.value.length,
			userCount: selectedUsers.value.length,
		})
		errorMessage.value = problem ? wizardError(problem) : ''
		return problem === null
	}
	function goToWizardStep(target: number): void {
		if (target < 1 || target > 3) return
		if (target > wizardStep.value) {
			for (let step = wizardStep.value; step < target; step++) {
				if (!checkWizardStep(step)) return
			}
		}
		wizardStep.value = target
		errorMessage.value = ''
	}
	function continueWizard() {
		goToWizardStep(wizardStep.value + 1)
	}
	function onIntentChange({
		from,
		to,
	}: {
		from: TriggerIntent | null
		to: TriggerIntent
	}): void {
		Object.assign(form.value, intentChangePatch(from, to))
	}

	const automationReady = computed(() => {
		const draft = form.value
		if (!draft.trigger_key) return false
		if (draft.trigger_key === 'nextcloud.board.published') return true
		if (draft.trigger_key === 'nextcloud.time.window') {
			return (
				draft.starts_at instanceof Date
				&& !Number.isNaN(draft.starts_at.getTime())
				&& (!(draft.ends_at instanceof Date)
					|| draft.ends_at > draft.starts_at)
			)
		}
		if (draft.trigger_key === 'nextcloud.page.opened') {
			const validApp = (catalog.value.automation?.applications || []).some(
				(app) => app.id === draft.placement && app.canPages,
			)
			const validFilters = Object.values(draft.page_filters || {}).every(
				(rule) =>
					rule
					&& ['equals', 'prefix', 'digits'].includes(rule.operator)
					&& (rule.operator === 'digits'
						|| /^[A-Za-z0-9._~-]{1,96}$/.test(rule.value || '')),
			)
			return validApp && validFilters
		}
		return (catalog.value.automation?.sources || []).some((source) =>
			source.actions?.some((action) => action.key === draft.trigger_key),
		)
	})
	const communicationReady = computed(() => {
		const draft = form.value
		return (
			hasDestination.value
			&& !!draft.name.trim()
			&& !!draft.headline.trim()
			&& !!draft.message.trim()
			&& (draft.audience_type !== 'group' || selectedGroups.value.length > 0)
			&& (draft.audience_type !== 'users' || selectedUsers.value.length > 0)
			&& Boolean(draft.cta_label.trim()) === Boolean(draft.cta_url.trim())
		)
	})
	const canContinue = computed(() =>
		wizardStep.value === 1 ? automationReady.value : communicationReady.value,
	)

	return {
		wizardStep,
		wizardSteps,
		nextStepLabel,
		wizardError,
		checkWizardStep,
		goToWizardStep,
		continueWizard,
		onIntentChange,
		automationReady,
		communicationReady,
		canContinue,
		errorMessage,
	}
}
