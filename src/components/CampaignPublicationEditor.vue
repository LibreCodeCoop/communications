<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { mdiPaletteOutline } from '@mdi/js'
import { useCampaignEditorContext } from '../composables/campaignEditorContext'
import CampaignEditorTimingStep from './CampaignEditorTimingStep.vue'
import CampaignEditorMessageStep from './CampaignEditorMessageStep.vue'
import CampaignEditorReviewStep from './CampaignEditorReviewStep.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import CampaignWizardSidebar from './CampaignWizardSidebar.vue'
const {
	catalog,
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
	editorId,
	wizardStep,
	wizardSteps,
	nextStepLabel,
	goToWizardStep,
	continueWizard,
	onIntentChange,
	feedback,
	errorMessage,
	selectedGroups,
	selectedExcludedGroups,
	selectedUsers,
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
	groupChoices,
	userChoices,
	searchUsers,
	openPreview,
	summaryWhen,
	audienceReview,
	locationReview,
	frequencyReview,
	scheduleReview,
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
} = useCampaignEditorContext()
</script>

<template>
	<div class="cc-native-heading cc-native-heading-editor">
		<div class="cc-wizard-heading">
			<!-- TRANSLATORS Action or instruction in the publications management screen. -->
			<NcButton
				variant="tertiary"
				:text="t('communications', 'Back to publications')"
				@click="cancelEdit" />
			<h1>
				{{ editMode ? adminEditPublicationLabel : adminNewPublicationLabel }}
			</h1>
			<p>
				<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
				{{
					t(
						'communications',
						'One message, multiple delivery methods. Configure the timing, content, and recipients.',
					)
				}}
			</p>
		</div>
	</div>

	<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
	<nav
		class="cc-wizard-steps"
		:aria-label="t('communications', 'Publication steps')">
		<NcButton
			v-for="step in wizardSteps"
			:key="step.number"
			:pressed="wizardStep === step.number"
			:aria-label="step.number + ': ' + step.label"
			:variant="wizardStep === step.number ? 'primary' : 'tertiary'"
			:aria-current="wizardStep === step.number ? 'step' : undefined"
			:disabled="
				(step.number > 1 && !automationReady)
				|| (step.number > 2 && !communicationReady)
			"
			@click="goToWizardStep(step.number)">
			<template #icon
				><NcIconSvgWrapper :path="step.icon" :size="18"
			/></template>
			<span class="cc-wizard-step-long"
				>{{ step.number }} {{ step.label }}</span
			>
			<span class="cc-wizard-step-short"
				>{{ step.number }} {{ step.shortLabel }}</span
			>
		</NcButton>
	</nav>

	<NcNoteCard v-if="errorMessage" type="error" class="cc-wizard-error">{{
		errorMessage
	}}</NcNoteCard>

	<div
		class="cc-native-editor-layout"
		:class="{ 'cc-wizard-unconfigured': wizardStep === 1 }">
		<div class="cc-native-editor-fields cc-wizard-main">
			<CampaignEditorTimingStep v-if="wizardStep === 1" />

			<CampaignEditorMessageStep v-else-if="wizardStep === 2" />

			<CampaignEditorReviewStep v-else />

			<div class="cc-native-editor-footer cc-wizard-footer">
				<!-- TRANSLATORS Dismiss the current editor or confirmation dialog without saving. -->
				<NcButton
					:text="t('communications', 'Cancel')"
					variant="secondary"
					@click="cancelEdit" />
				<div class="cc-wizard-footer-actions">
					<!-- TRANSLATORS Return to the previous step in the publication editor. -->
					<NcButton
						v-if="wizardStep > 1"
						:text="t('communications', 'Back')"
						@click="goToWizardStep(wizardStep - 1)" />
					<span
						v-if="wizardStep === 1 && !canContinue"
						class="cc-wizard-required-hint"
						>{{ adminChooseAValidConditionToLabel }}</span
					>
					<NcButton
						v-if="wizardStep < 3"
						variant="primary"
						:text="nextStepLabel"
						:disabled="!canContinue"
						@click="continueWizard" />
					<NcButton
						v-else
						variant="primary"
						:disabled="saving"
						:text="saving ? adminSavingLabel : adminSavePublicationLabel"
						@click="save" />
				</div>
			</div>
		</div>

		<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
		<CampaignWizardSidebar
			v-if="wizardStep > 1"
			:form="form"
			:summary-when="summaryWhen"
			:audience-review="audienceReview"
			:location-review="locationReview"
			:delivery-review="deliveryReview"
			:frequency-review="frequencyReview"
			:schedule-review="scheduleReview"
			@preview="openPreview()" />
	</div>
</template>
