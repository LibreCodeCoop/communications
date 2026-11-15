// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import { makeDefaultCampaignForm } from '../../../src/services/campaignForm'
import { useCampaignEditorOptions } from '../../../src/composables/useCampaignEditorOptions'
import { useCampaignDeliveryState } from '../../../src/composables/useCampaignDeliveryState'
import { useCampaignWizardState } from '../../../src/composables/useCampaignWizardState'
import { useCampaignReview } from '../../../src/composables/useCampaignReview'
import { useCampaignContentState } from '../../../src/composables/useCampaignContentState'
import type {
	CampaignCatalog,
	GroupSelection,
	UserSelection,
} from '../../../src/types/communications'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, message: string) => message,
	getLanguage: () => 'en_US',
}))

function model() {
	const form = ref(makeDefaultCampaignForm())
	const catalog = ref<CampaignCatalog>({
		groups: [],
		automation: { intents: [], sources: [], applications: [], placements: [] },
		deliveryChannels: [],
	})
	const options = useCampaignEditorOptions(catalog)
	const delivery = useCampaignDeliveryState(form, catalog, options.bannerTypes)
	const selectedGroups = ref<GroupSelection[]>([])
	const selectedUsers = ref<UserSelection[]>([])
	const wizard = useCampaignWizardState(
		form,
		catalog,
		delivery.hasDestination,
		selectedGroups,
		selectedUsers,
	)
	return {
		form,
		catalog,
		options,
		delivery,
		selectedGroups,
		selectedUsers,
		wizard,
	}
}

describe('editor instance state', () => {
	it('creates independent drafts without shared audience or translation arrays', () => {
		const first = makeDefaultCampaignForm()
		const next = makeDefaultCampaignForm()
		first.gateway_channels.push('sms')
		first.audience_groups.push('editors')
		first.translated_content.pt_BR = { headline: 'Olá' }
		expect(next.gateway_channels).toEqual([])
		expect(next.audience_groups).toEqual([])
		expect(next.translated_content).toEqual({})
	})

	it('updates destinations reactively without enabling unavailable providers', () => {
		const { form, catalog, options, delivery } = model()
		form.value.presentation = 'none'
		expect(delivery.hasDestination.value).toBe(false)
		delivery.onDeliveryChannelChange({ id: 'email', enabled: true })
		expect(form.value.notify_email).toBe(false)

		catalog.value.deliveryChannels = [
			{
				id: 'email',
				ready: true,
				availability: 'ready',
				status: 'Ready',
				help: '',
			},
		]
		delivery.onDeliveryChannelChange({ id: 'email', enabled: true })
		expect(form.value.notify_email).toBe(true)
		expect(delivery.destinationCount.value).toBe(1)
		expect(delivery.deliveryReview.value).toContain('E-mail')
		expect(options.placementOptions.value).toEqual([])
		catalog.value.automation.placements = [
			{ label: 'Files', value: 'files', description: '' },
		]
		expect(options.placementOptions.value).toEqual([
			{ label: 'Files', value: 'files' },
		])

		form.value.guest_visible = true
		delivery.onDeliveryChannelChange({ id: 'email', enabled: false })
		expect(form.value.notify_email).toBe(true)
		form.value.guest_visible = false
		delivery.onDeliveryChannelChange({ id: 'email', enabled: false })
		expect(form.value.notify_email).toBe(false)
	})

	it('blocks navigation until trigger, message and recipients are valid', () => {
		const { form, selectedGroups, wizard } = model()
		form.value.trigger_key = 'nextcloud.time.window'
		wizard.continueWizard()
		expect(wizard.wizardStep.value).toBe(1)
		expect(wizard.errorMessage.value).toBe(
			'Choose when to deliver the publication.',
		)
		form.value.starts_at = new Date('2030-01-01T12:00:00Z')
		wizard.continueWizard()
		expect(wizard.wizardStep.value).toBe(2)
		expect(wizard.errorMessage.value).toBe('')
		form.value.name = 'News'
		form.value.headline = 'Update'
		form.value.message = 'Details'
		form.value.audience_type = 'group'
		wizard.continueWizard()
		expect(wizard.wizardStep.value).toBe(2)
		expect(wizard.errorMessage.value).toBe(
			'Select at least one recipient group.',
		)
		selectedGroups.value = [{ id: 'staff', displayName: 'Staff' }]
		wizard.continueWizard()
		expect(wizard.wizardStep.value).toBe(3)
		wizard.goToWizardStep(1)
		expect(wizard.wizardStep.value).toBe(1)
	})

	it('isolates translations by language while presenting live group summaries', () => {
		const { form, catalog, options, delivery, selectedGroups, selectedUsers } =
			model()
		const content = useCampaignContentState(form)
		content.translatedHeadline.value = 'Welcome'
		content.translationLocale.value = 'pt_BR'
		expect(content.translatedHeadline.value).toBe('')
		content.translatedHeadline.value = 'Bem-vindos'
		content.translationLocale.value = 'en_US'
		expect(content.translatedHeadline.value).toBe('Welcome')
		expect(form.value.translated_content.pt_BR.headline).toBe('Bem-vindos')

		const review = useCampaignReview(
			form,
			catalog,
			selectedGroups,
			selectedUsers,
			options.placementOptions,
			computed(() => []),
			options.frequencyOptions,
			delivery.inboxCopy,
			delivery.eventDrivenDelivery,
		)
		form.value.audience_type = 'group'
		form.value.group_match = 'all'
		selectedGroups.value = [
			{ id: 'a', displayName: 'Team A' },
			{ id: 'b', displayName: 'Team B' },
		]
		expect(review.audienceReview.value).toBe('Team A AND Team B')
		form.value.group_match = 'any'
		expect(review.audienceReview.value).toBe('Team A OR Team B')
		form.value.audience_type = 'users'
		selectedUsers.value = [{ id: 'alice', displayName: 'Alice' }]
		expect(review.audienceReview.value).toBe('Alice')
	})
})
