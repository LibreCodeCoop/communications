// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { t } from '@nextcloud/l10n'
import { useCampaignEditorOptions } from './useCampaignEditorOptions'
import { useCampaignDeliveryState } from './useCampaignDeliveryState'
import { useCampaignReview } from './useCampaignReview'
import { useCampaignContentState } from './useCampaignContentState'
import { useCampaignManagement } from './useCampaignManagement'
import { useCampaignWizardState } from './useCampaignWizardState'
import {
	campaignRecordToForm,
	makeDefaultCampaignForm,
	serializeCampaignForm,
	validateCampaignPayload,
} from '../services/campaignForm'
import type { CampaignSavePayload } from '../services/campaignForm'
import { useCampaignStore } from '../stores/campaigns'
import { storeToRefs } from 'pinia'
import { useCampaignUserLookup } from '../composables/useCampaignUserLookup'

import { computed, onMounted, ref, watch } from 'vue'
import type { Ref } from 'vue'
import type {
	CampaignRecord,
	CampaignForm,
	CampaignCatalog,
	GroupSelection,
	UserSelection,
	ChannelNoticeField,
	TranslatedPublicationField,
	TriggerIntent,
} from '../types/communications'
// An isolated editor instance owns its transient draft state. The shared Pinia
// store continues to own server-backed campaigns and mutation transactions.
import { DEFAULT_BANNER_COLOR } from '../bannerAppearance.ts'
import { mdiWindowMaximize, mdiDockTop } from '@mdi/js'

export function useCampaignAdminController(
	createRequest: Readonly<Ref<number>>,
	onEditorView: (view: string) => void,
) {
	watch(createRequest, (value, previous) => {
		if (value !== previous) openCreate()
	})
	const campaignStore = useCampaignStore()
	const { campaigns, catalog, loading } = storeToRefs(campaignStore)
	const {
		audienceOptions,
		publishOptions,
		placementOptions,
		targetModeOptions,
		targetAppOptions,
		frequencyOptions,
		excludedMatchOptions,
		groupMatchOptions,
		categoryOptions,
		themeOptions,
		bannerTypes,
		selectable,
	} = useCampaignEditorOptions(catalog)
	const saving = ref(false)
	const view = ref('list')
	watch(view, onEditorView, { immediate: true })
	const editorId = ref<number | null>(null)
	const previewOpen = ref(false)
	const feedback = ref('')
	const feedbackType = ref<'info' | 'success' | 'warning' | 'error'>('info')
	const {
		confirmArchiveOpen,
		confirmDeleteOpen,
		deleteTarget,
		deleteName,
		deleting,
		archiveTarget,
		archiveConfirmationMessage,
		orderSaving,
		editorGroupValues,
		editorConfigLoading,
		metricsOpen,
		metricsLoading,
		metricsData,
		metricsCampaign,
		saveEditorGroups,
		updateStatus,
		openMetrics,
		moveCampaign,
		duplicate,
		askDelete,
		deletePermanently,
		askArchive,
		archive,
	} = useCampaignManagement(
		campaignStore,
		catalog,
		flash,
		errorMessageFor,
		openEdit,
	)
	const previewCampaign = ref<CampaignRecord | null>(null)
	const selectedGroups = ref<GroupSelection[]>([])
	const selectedExcludedGroups = ref<GroupSelection[]>([])
	const selectedUsers = ref<UserSelection[]>([])
	const userLookup = useCampaignUserLookup()
	const userSearchOptions = userLookup.options
	const form = ref<CampaignForm>(makeDefaultCampaignForm())
	const {
		hasAdditionalNotices,
		eventDrivenDelivery,
		hasDestination,
		destinationCount,
		inboxCopy,
		deliveryChannelDefinitions,
		gatewayDefinitions,
		channelLabels,
		gatewayOptions,
		gatewayGroup,
		gatewayReadyCount,
		gatewayProviderCountLabel,
		deliveryChannelOptions,
		setNoticeChannel,
		onDeliveryChannelChange,
		noticeUsers,
		noticeActivity,
		noticeEmail,
		visualDeliveryOptions,
		inboxDeliveryOption,
		deliveryReview,
		selectedBannerType,
		bannerIconOptions,
		colorPickerPalette,
		adminAppNotInstalledLabel,
	} = useCampaignDeliveryState(form, catalog, bannerTypes)

	const {
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
	} = useCampaignWizardState(
		form,
		catalog,
		hasDestination,
		selectedGroups,
		selectedUsers,
	)

	// Outbound channels, Inbox history and presentation are independent.
	const {
		languageOptions,
		translationLocale,
		translatedField,
		translatedHeadline,
		translatedMessage,
		translatedCta,
		selectedTextColor,
		alignments,
	} = useCampaignContentState(form)
	const initialFormSnapshot = ref('')
	// TRANSLATORS Descriptive message shown in the publications management screen.
	const contentFormattingHelp = t(
		'communications',
		'Markdown with formatting and links. Custom HTML and CSS are not executed.',
	)
	const formDirty = computed(
		() => JSON.stringify(serializableForm()) !== initialFormSnapshot.value,
	)
	const editMode = computed(() => editorId.value !== null)

	const makeDefault = makeDefaultCampaignForm

	function errorMessageFor(error: unknown): string {
		return error instanceof Error ? error.message : String(error)
	}
	function flash(
		text: string,
		type: 'info' | 'success' | 'warning' | 'error' = 'info',
	): void {
		feedback.value = text
		feedbackType.value = type
	}
	const triggerOptions = computed(() =>
		(catalog.value.automation?.sources || []).flatMap((source) =>
			(source.actions || []).map((action) => ({
				label: action.label,
				value: action.key,
			})),
		),
	)
	const groupOptions = computed(() =>
		(catalog.value.groups || []).map((g) => ({ label: g.name, value: g.id })),
	)
	const groupChoices = computed(() =>
		groupOptions.value.map((group) => ({
			id: group.value,
			displayName: group.label,
			isNoUser: true,
		})),
	)
	const userChoices = computed(() => {
		const ids = new Set()
		return [...selectedUsers.value, ...userSearchOptions.value].filter(
			(option) => {
				if (ids.has(option.id)) return false
				ids.add(option.id)
				return true
			},
		)
	})
	async function searchUsers(search: string): Promise<void> {
		try {
			await userLookup.search(search)
		} catch (error) {
			flash(errorMessageFor(error), 'error')
		}
	}
	function openPreview(campaign: CampaignRecord | null = null): void {
		previewCampaign.value = campaign
		previewOpen.value = true
	}
	function closePreview() {
		previewOpen.value = false
		previewCampaign.value = null
	}

	const {
		summaryAction,
		sourceForReview,
		summaryWhen,
		audienceReview,
		locationReview,
		dailyFrequencyLabel,
		frequencyReview,
		scheduleReview,
	} = useCampaignReview(
		form,
		catalog,
		selectedGroups,
		selectedUsers,
		placementOptions,
		triggerOptions,
		frequencyOptions,
		inboxCopy,
		eventDrivenDelivery,
	)
	function openCreate() {
		selectedGroups.value = []
		selectedExcludedGroups.value = []
		selectedUsers.value = []
		userLookup.clear()
		form.value = makeDefaultCampaignForm()
		initialFormSnapshot.value = JSON.stringify(serializableForm())
		editorId.value = null
		errorMessage.value = ''
		feedback.value = ''
		wizardStep.value = 1
		view.value = 'editor'
	}
	function openEdit(c: CampaignRecord) {
		const groupIds = c.audience_groups
		selectedGroups.value = groupIds.map((id) => {
			const label =
				groupChoices.value.find((option) => option.id === id)?.displayName
				|| id
			return { id, displayName: label, isNoUser: true }
		})
		selectedUsers.value = (c.audience_users || []).map((id) => ({
			id,
			displayName: id,
			user: id,
		}))
		selectedExcludedGroups.value = (c.excluded_groups || []).map((id) => {
			const label =
				groupChoices.value.find((option) => option.id === id)?.displayName
				|| id
			return { id, displayName: label, isNoUser: true }
		})
		userLookup.clear()
		form.value = campaignRecordToForm(c, groupIds, DEFAULT_BANNER_COLOR)
		initialFormSnapshot.value = JSON.stringify(serializableForm())
		editorId.value = c.id
		errorMessage.value = ''
		feedback.value = ''
		wizardStep.value = 1
		view.value = 'editor'
	}
	function cancelEdit() {
		if (
			formDirty.value
			// TRANSLATORS Question presented to the user in the publications management screen.
			&& !window.confirm(t('communications', 'Discard unsaved changes?'))
		)
			return
		view.value = 'list'
		errorMessage.value = ''
	}
	function serializableForm(): CampaignSavePayload {
		return serializeCampaignForm(
			form.value,
			selectedGroups.value,
			selectedUsers.value,
			selectedExcludedGroups.value,
		)
	}

	async function save() {
		if (saving.value) return
		const data = serializableForm()
		errorMessage.value = ''
		const validationError = validateCampaignPayload(data, hasDestination.value)
		if (validationError) {
			errorMessage.value = validationError
			return
		}
		saving.value = true
		try {
			const isNew = editorId.value === null
			const saved = await campaignStore.save(data, editorId.value)
			if (
				isNew
				&& ['nextcloud.flow.matched', 'external.webhook'].includes(
					data.trigger_key,
				)
			) {
				editorId.value = saved.id
				initialFormSnapshot.value = JSON.stringify(serializableForm())
				flash(
					data.trigger_key === 'nextcloud.flow.matched'
						? // TRANSLATORS Shown after saving a Flow-triggered publication; refers to its name in Flow.
							t(
								'communications',
								"Publication saved. Configure the rule in Flow using this publication's name.",
							)
						: // TRANSLATORS Descriptive message shown in the publications management screen.
							t(
								'communications',
								'Publication saved. Use the identifier shown in the webhook example before testing.',
							),
					'success',
				)
			} else {
				view.value = 'list'
				flash(
					isNew
						? // TRANSLATORS Short label for an option or field in the publications management screen.
							t('communications', 'Publication created.')
						: // TRANSLATORS Short label for an option or field in the publications management screen.
							t('communications', 'Publication updated.'),
					'success',
				)
			}
		} catch (e) {
			errorMessage.value = errorMessageFor(e)
		} finally {
			saving.value = false
		}
	}

	onMounted(async () => {
		try {
			await campaignStore.load()
			editorGroupValues.value = catalog.value.editorGroups || []
			if (new URL(location.href).searchParams.get('view') === 'new')
				openCreate()
		} catch (e) {
			flash(errorMessageFor(e), 'error')
		}
	})

	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminIdentificationLabel = t('communications', 'Identification')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminContentLabel = t('communications', 'Content')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminTranslationLanguageLabel = t('communications', 'Translation language')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminRecipientsLabel = t('communications', 'Recipients')
	// TRANSLATORS Action or instruction in the publications management screen.
	const adminSendToLabel = t('communications', 'Send to')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminGroupMatchingLabel = t('communications', 'Group matching')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminHowToMatchExcludedGroupsLabel = t(
		'communications',
		'How to match excluded groups',
	)
	// TRANSLATORS Descriptive message shown in the publications management screen.
	const adminMessagingAndSMSTwoFactorLabel = t(
		'communications',
		'Messaging and SMS (Two-Factor Gateway)',
	)
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminMessageTypeLabel = t('communications', 'Message type')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminBannerColorLabel = t('communications', 'Banner color')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminBannerIconLabel = t('communications', 'Banner icon')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminAllowDismissingTheBannerLabel = t(
		'communications',
		'Allow dismissing the banner',
	)
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminTextAlignmentLabel = t('communications', 'Text alignment')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminTextColorOptionalLabel = t('communications', 'Text color (optional)')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminVisualDestinationLabel = t('communications', 'Visual destination')
	// TRANSLATORS Name of a delivery option without a banner or dialog.
	const adminNoContextualDisplayLabel = t(
		'communications',
		'No contextual display',
	)
	// TRANSLATORS Name of a contextual visual delivery format: banner.
	const adminContextualBannerLabel = t('communications', 'Contextual banner')
	// TRANSLATORS Name of a contextual visual delivery format: modal dialog.
	const adminModalDialogLabel = t('communications', 'Modal dialog')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminPriority0100Label = t('communications', 'Priority (0 to 100)')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminWhereToDisplayLabel = t('communications', 'Where to display')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminFrequencyLimitLabel = t('communications', 'Frequency limit')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminWhereToDisplayThePublicationLabel = t(
		'communications',
		'Where to display the publication',
	)
	// TRANSLATORS Descriptive message shown in the publications management screen.
	const adminIncludedOrExcludedApplicationsLabel = t(
		'communications',
		'Included or excluded applications *',
	)
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminCategoryLabel = t('communications', 'Category')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminVisualThemeLabel = t('communications', 'Visual theme')
	// TRANSLATORS Short label for an option or field in the publications management screen.
	const adminPublicationStatusLabel = t('communications', 'Publication status')
	// TRANSLATORS Action or instruction in the publications management screen.
	const adminChooseAValidConditionToLabel = t(
		'communications',
		'Choose a valid condition to continue.',
	)
	// TRANSLATORS Title for the editor when creating a publication.
	const adminNewPublicationLabel = t('communications', 'New publication')
	// TRANSLATORS Title for the editor when updating an existing publication.
	const adminEditPublicationLabel = t('communications', 'Edit publication')
	// TRANSLATORS Placeholder example of a publication internal name.
	const adminExampleWelcomeNoticeLabel = t(
		'communications',
		'Example: Welcome notice',
	)
	// TRANSLATORS Required field label for the publication internal name.
	const adminInternalNameLabel = t('communications', 'Internal name *')
	// TRANSLATORS Placeholder for the publication message editor.
	const adminWriteYourMessageLabel = t('communications', 'Write your message…')
	// TRANSLATORS Required field label for publication message body.
	const adminDisplayedMessageLabel = t('communications', 'Displayed message *')
	// TRANSLATORS Placeholder for choosing recipient groups.
	const adminSelectOneOrMoreGroupsLabel = t(
		'communications',
		'Select one or more groups',
	)
	// TRANSLATORS Selector label for the groups that receive a publication.
	const adminRecipientGroupsLabel = t('communications', 'Recipient groups')
	// TRANSLATORS Placeholder for searching individual Nextcloud recipients.
	const adminSearchByNameOrIdentifierLabel = t(
		'communications',
		'Search by name or identifier',
	)
	// TRANSLATORS Selector label for individual publication recipients.
	const adminRecipientUsersLabel = t('communications', 'Recipient users')
	// TRANSLATORS Placeholder for selecting excluded groups.
	const adminTheseGroupsWillNotReceiveLabel = t(
		'communications',
		'These groups will not receive the publication',
	)
	// TRANSLATORS Selector label for the excluded recipient groups.
	const adminExcludedGroupsLabel = t('communications', 'Excluded groups')
	// TRANSLATORS Status description when a publication is not active.
	const adminWhileDraftOrPausedThisHint = t(
		'communications',
		'While draft or paused, this publication will not be displayed or sent.',
	)
	// TRANSLATORS Status description for a publication being activated.
	const adminThePublicationWillBecomeActiveHint = t(
		'communications',
		'The publication will become active according to the configured schedule and rules.',
	)
	// TRANSLATORS Button for persisting a publication.
	const adminSavePublicationLabel = t('communications', 'Save publication')
	// TRANSLATORS Progress label while saving a publication.
	const adminSavingLabel = t('communications', 'Saving…')

	return {
		campaigns,
		catalog,
		loading,
		audienceOptions,
		publishOptions,
		placementOptions,
		targetModeOptions,
		targetAppOptions,
		frequencyOptions,
		excludedMatchOptions,
		groupMatchOptions,
		categoryOptions,
		themeOptions,
		bannerTypes,
		selectable,
		saving,
		view,
		editorId,
		previewOpen,
		wizardStep,
		wizardSteps,
		nextStepLabel,
		goToWizardStep,
		continueWizard,
		onIntentChange,
		feedback,
		feedbackType,
		errorMessage,
		confirmArchiveOpen,
		confirmDeleteOpen,
		deleteTarget,
		deleteName,
		deleting,
		archiveConfirmationMessage,
		orderSaving,
		previewCampaign,
		editorGroupValues,
		editorConfigLoading,
		selectedGroups,
		selectedExcludedGroups,
		selectedUsers,
		metricsOpen,
		metricsLoading,
		metricsData,
		metricsCampaign,
		form,
		hasAdditionalNotices,
		eventDrivenDelivery,
		hasDestination,
		destinationCount,
		inboxCopy,
		gatewayOptions,
		gatewayProviderCountLabel,
		deliveryChannelOptions,
		onDeliveryChannelChange,
		noticeEmail,
		visualDeliveryOptions,
		inboxDeliveryOption,
		deliveryReview,
		selectedBannerType,
		bannerIconOptions,
		colorPickerPalette,
		languageOptions,
		translationLocale,
		translatedHeadline,
		translatedMessage,
		translatedCta,
		selectedTextColor,
		alignments,
		automationReady,
		communicationReady,
		canContinue,
		contentFormattingHelp,
		editMode,
		triggerOptions,
		groupOptions,
		groupChoices,
		userChoices,
		searchUsers,
		openPreview,
		closePreview,
		summaryWhen,
		audienceReview,
		locationReview,
		frequencyReview,
		scheduleReview,
		saveEditorGroups,
		updateStatus,
		openMetrics,
		moveCampaign,
		duplicate,
		askDelete,
		deletePermanently,
		askArchive,
		archive,
		openEdit,
		cancelEdit,
		save,
		adminIdentificationLabel,
		adminContentLabel,
		adminTranslationLanguageLabel,
		adminRecipientsLabel,
		adminSendToLabel,
		adminGroupMatchingLabel,
		adminHowToMatchExcludedGroupsLabel,
		adminMessagingAndSMSTwoFactorLabel,
		adminMessageTypeLabel,
		adminBannerColorLabel,
		adminBannerIconLabel,
		adminAllowDismissingTheBannerLabel,
		adminTextAlignmentLabel,
		adminTextColorOptionalLabel,
		adminVisualDestinationLabel,
		adminNoContextualDisplayLabel,
		adminContextualBannerLabel,
		adminModalDialogLabel,
		adminPriority0100Label,
		adminWhereToDisplayLabel,
		adminFrequencyLimitLabel,
		adminWhereToDisplayThePublicationLabel,
		adminIncludedOrExcludedApplicationsLabel,
		adminCategoryLabel,
		adminVisualThemeLabel,
		adminPublicationStatusLabel,
		adminChooseAValidConditionToLabel,
		adminNewPublicationLabel,
		adminEditPublicationLabel,
		adminExampleWelcomeNoticeLabel,
		adminInternalNameLabel,
		adminWriteYourMessageLabel,
		adminDisplayedMessageLabel,
		adminSelectOneOrMoreGroupsLabel,
		adminRecipientGroupsLabel,
		adminSearchByNameOrIdentifierLabel,
		adminRecipientUsersLabel,
		adminTheseGroupsWillNotReceiveLabel,
		adminExcludedGroupsLabel,
		adminWhileDraftOrPausedThisHint,
		adminThePublicationWillBecomeActiveHint,
		adminSavePublicationLabel,
		adminSavingLabel,
	}
}
