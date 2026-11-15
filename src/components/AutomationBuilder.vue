<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { useAppRoutes } from '../composables/useAppRoutes'
import {
	limitAppOptions,
	searchAutomationApps,
} from '../services/automationSelection'
import type {
	AutomationCatalog,
	NativeIntent,
	NativeApplication,
	TriggerIntent,
	TriggerMode,
	PageRouteFilters,
} from '../types/communications'

import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import AutomationApplicationSelect from './AutomationApplicationSelect.vue'
import AutomationRouteConfiguration from './AutomationRouteConfiguration.vue'
import AutomationAdvancedSetup from './AutomationAdvancedSetup.vue'
import {
	mdiApps,
	mdiCalendarMonthOutline,
	mdiSourceBranch,
	mdiPuzzleOutline,
	mdiCheckCircleOutline,
	mdiBullhornOutline,
} from '@mdi/js'

const props = withDefaults(
	defineProps<{
		modelValue?: string
		placement?: string
		pageRoute?: string
		pageFilters?: PageRouteFilters
		catalog?: AutomationCatalog
		campaignId?: number | null
	}>(),
	{
		modelValue: '',
		placement: 'any',
		pageRoute: '',
		pageFilters: () => ({}),
		catalog: () => ({
			sources: [],
			applications: [],
			intents: [],
			placements: [],
		}),
		campaignId: null,
	},
)
const emit = defineEmits<{
	'update:modelValue': [value: string]
	'update:placement': [value: string]
	'update:pageRoute': [value: string]
	'update:pageFilters': [value: PageRouteFilters]
	'intent-change': [value: { from: TriggerIntent | null; to: TriggerIntent }]
}>()
const selectedIntent = ref<TriggerIntent | null>(null)
const selectedAppId = ref<string | null>(null)
const selectedMode = ref<TriggerMode>('event')
const selectedAdvancedId = ref<string | null>(null)
const appSearch = ref('')
const MAX_OPTIONS = 12
const {
	routes,
	loading: routeLoading,
	failed: routeFailed,
	select: loadRoutes,
} = useAppRoutes()
// TRANSLATORS Error when an application's Nextcloud page routes cannot be retrieved.
const routeFailureLabel = t(
	'communications',
	'Could not retrieve pages for this app.',
)
const routeError = computed(() => (routeFailed.value ? routeFailureLabel : ''))

const intentIcons: Record<TriggerIntent, string> = {
	app: mdiApps,
	schedule: mdiCalendarMonthOutline,
	advanced: mdiSourceBranch,
	board: mdiBullhornOutline,
}
const sources = computed(() => props.catalog.sources || [])
const applications = computed(() => props.catalog.applications || [])
const intents = computed(() => props.catalog.intents || [])
const selectedApp = computed(
	() => applications.value.find((app) => app.id === selectedAppId.value) || null,
)
const advancedSources = computed(() =>
	sources.value.filter((source) => source.intent === 'advanced'),
)
const selectedAdvanced = computed(
	() =>
		advancedSources.value.find(
			(source) => source.id === selectedAdvancedId.value,
		) || null,
)
const selectedAction = computed(
	() =>
		selectedApp.value?.actions?.find((action) => action.key === props.modelValue)
		|| null,
)
const actionOptions = computed(() =>
	(selectedApp.value?.actions || []).map((action) => ({
		label: action.label,
		value: action.key,
		description: action.description,
	})),
)
const filteredApplications = computed(() =>
	searchAutomationApps(applications.value, appSearch.value),
)
const appOptions = computed(() =>
	limitAppOptions(filteredApplications.value, selectedApp.value, MAX_OPTIONS),
)
const appValue = computed({
	get: () => selectedAppId.value,
	set: (id: string | null) => selectApp(id),
})
const actionValue = computed({
	get: () => props.modelValue,
	set: (key: string) => emit('update:modelValue', key || ''),
})
const advancedValue = computed({
	get: () => selectedAdvancedId.value,
	set: (id: string | null) => {
		selectedAdvancedId.value = id
		const source = advancedSources.value.find((item) => item.id === id)
		emit('update:modelValue', source?.actions[0]?.key || '')
		emit('update:pageRoute', '')
	},
})
const modeValue = computed({
	get: () => selectedMode.value,
	set: (mode: TriggerMode) => selectMode(mode),
})
watch([() => props.modelValue, () => props.placement], (next, previous) => {
	if (previous[0] !== next[0] || previous[1] !== next[1])
		emit('update:pageFilters', {})
})
// TRANSLATORS Label for all eligible pages of the selected Nextcloud application.
const anyApplicationPageLabel = t('communications', 'Any application page')
const selectedRouteLabel = computed(() =>
	props.pageRoute === ''
		? anyApplicationPageLabel
		: routes.value.find((route) => route.value === props.pageRoute)?.label
			|| props.pageRoute,
)
// TRANSLATORS Placeholder for the publication ID before a webhook-enabled publication is saved.
const unsavedCampaignIdLabel = t('communications', '<campaign ID after saving>')
const webhookExample = computed(() =>
	JSON.stringify(
		{
			campaignId: props.campaignId || unsavedCampaignIdLabel,
			uid: 'nextcloud_user',
			eventId: 'unique-event-001',
		},
		null,
		2,
	),
)
// TRANSLATORS Summary of a publication available immediately upon activation.
const onActivationLabel = t('communications', 'Available on activation')
// TRANSLATORS Summary of a scheduled publication available on the next eligible visit.
const availabilityPeriodLabel = t(
	'communications',
	'During the chosen period, on the next eligible visit',
)
// TRANSLATORS Prompt to choose an application as the event source.
const chooseAppLabel = t('communications', 'Choose an app')
// TRANSLATORS Prompt to choose an event produced by an application.
const selectEventLabel = t('communications', 'Select an application event')
// TRANSLATORS Description of how direct publication delivery reaches recipients.
const activationExplanation = t(
	'communications',
	'On activation, outbound channels are queued; content appears in Inbox if selected, and banners or dialogs appear on the next eligible visit.',
)
// TRANSLATORS Description of the availability period of scheduled publications.
const scheduleExplanation = t(
	'communications',
	'The publication becomes eligible only within its availability period; it appears when the user returns to Nextcloud.',
)
// TRANSLATORS Description of an HMAC webhook that delivers a publication to one user.
const webhookExplanation = t(
	'communications',
	'The HMAC event specifies campaignId and uid. Only this campaign and the specified user can receive the presentation, subject to audience and frequency limits.',
)
// TRANSLATORS Description of the Nextcloud Flow evaluation for a publication event.
const flowExplanation = t(
	'communications',
	'Flow checks the configured conditions before recording the occurrence for that user.',
)
// TRANSLATORS Description of a trigger for a specific Nextcloud interface route.
const specificRouteExplanation = t(
	'communications',
	'Only when the authenticated user opens this interface route. Variable URL parameters are not stored.',
)
// TRANSLATORS Prefix before the selected app name for a trigger on any of its pages.
const anyRoutePrefix = t('communications', 'When opening any interface page in ')
// TRANSLATORS Suffix after the selected app name; calls and other apps' pages do not trigger it.
const anyRouteSuffix = t(
	'communications',
	". API calls, downloads, and other apps' pages do not trigger this campaign.",
)
// TRANSLATORS Help when the user has not selected an application event.
const chooseTrustedEventLabel = t(
	'communications',
	'Choose a trusted event emitted by the application.',
)
// TRANSLATORS Accessibility label for grouping the publication trigger source choices.
const triggerSourceLabel = t('communications', 'Trigger source')
// TRANSLATORS Heading asking when a publication becomes available.
const automationHeading = t(
	'communications',
	'When should the publication become available?',
)
// TRANSLATORS Label for choosing between event triggers and page visits.
const modeQuestionLabel = t('communications', 'What should happen?')
// TRANSLATORS Choice for a trigger based on an application event.
const eventModeLabel = t('communications', 'An application event')
// TRANSLATORS Choice for a trigger based on an application page visit.
const pageModeLabel = t('communications', 'Page visit')
// TRANSLATORS Explanation of an app that only provides page-visit triggers.
const pagesOnlyLabel = t('communications', 'This app provides interface pages.')
// TRANSLATORS Explanation of an app that only provides typed event triggers.
const eventsOnlyLabel = t(
	'communications',
	'This app provides integrated events but has no navigation page.',
)
// TRANSLATORS Label for choosing a specific typed application event.
const applicationEventLabel = t('communications', 'Application event *')
// TRANSLATORS Placeholder while choosing an application event.
const selectEventPlaceholder = t('communications', 'Select an event...')
// TRANSLATORS Accessibility label for the event selection combobox.
const eventSelectorAriaLabel = t('communications', 'Select application event')
// TRANSLATORS Help when no event is needed and publication occurs on activation.
const boardModeHelp = t(
	'communications',
	'Without a technical trigger, the publication becomes available in Inbox when activated. Recipients and notifications are configured in the next step.',
)
// TRANSLATORS Help for publication visibility during a scheduled period.
const scheduledModeHelp = t(
	'communications',
	"The message will be available during the selected period and appear on the user's next visit.",
)

const summary = computed(() => {
	if (selectedIntent.value === 'board') return onActivationLabel
	if (selectedIntent.value === 'schedule') return availabilityPeriodLabel
	if (selectedIntent.value === 'advanced' && selectedAdvanced.value) {
		return (
			selectedAdvanced.value.label
			+ ' → '
			+ (selectedAdvanced.value.actions[0]?.label || '')
		)
	}
	if (!selectedApp.value) return chooseAppLabel
	if (selectedMode.value === 'visit')
		return selectedApp.value.label + ' → ' + selectedRouteLabel.value
	return selectedAction.value
		? selectedApp.value.label + ' → ' + selectedAction.value.label
		: selectEventLabel
})
const explanation = computed(() => {
	if (selectedIntent.value === 'board') return activationExplanation
	if (selectedIntent.value === 'schedule') return scheduleExplanation
	if (
		selectedIntent.value === 'advanced'
		&& selectedAdvancedId.value === 'webhook'
	)
		return webhookExplanation
	if (selectedIntent.value === 'advanced' && selectedAdvancedId.value === 'flow')
		return flowExplanation
	if (selectedMode.value === 'visit' && selectedApp.value) {
		return props.pageRoute
			? specificRouteExplanation
			: anyRoutePrefix + selectedApp.value.label + anyRouteSuffix
	}
	return selectedAction.value?.description || chooseTrustedEventLabel
})
const canSelectEvent = computed(() => !!selectedApp.value?.canEvents)
const canSelectPage = computed(() => !!selectedApp.value?.canPages)

function syncSelection(): void {
	if (!props.modelValue) return
	if (props.modelValue === 'nextcloud.board.published') {
		selectedIntent.value = 'board'
		return
	}
	if (props.modelValue === 'nextcloud.time.window') {
		selectedIntent.value = 'schedule'
		return
	}
	const advanced = advancedSources.value.find((source) =>
		source.actions?.some((action) => action.key === props.modelValue),
	)
	if (advanced) {
		selectedIntent.value = 'advanced'
		selectedAdvancedId.value = advanced.id
		return
	}
	selectedIntent.value = 'app'
	if (props.modelValue === 'nextcloud.page.opened') {
		selectedMode.value = 'visit'
		if (props.placement !== 'any') selectedAppId.value = props.placement
		return
	}
	const app = applications.value.find((app) =>
		app.actions?.some((action) => action.key === props.modelValue),
	)
	if (app) {
		selectedMode.value = 'event'
		selectedAppId.value = app.id
	}
}
watch(
	[() => props.modelValue, () => props.placement, applications, advancedSources],
	syncSelection,
	{ immediate: true },
)

function chooseIntent(id: TriggerIntent): void {
	if (selectedIntent.value === id) return
	emit('intent-change', { from: selectedIntent.value, to: id })
	selectedIntent.value = id
	appSearch.value = ''
	selectedAppId.value = null
	selectedAdvancedId.value = null
	if (id === 'board') {
		emit('update:modelValue', 'nextcloud.board.published')
		emit('update:placement', 'any')
		emit('update:pageRoute', '')
	} else if (id === 'schedule') {
		emit('update:modelValue', 'nextcloud.time.window')
		emit('update:placement', 'any')
		emit('update:pageRoute', '')
	} else {
		emit('update:modelValue', '')
		emit('update:placement', 'any')
		emit('update:pageRoute', '')
	}
}
function selectApp(id: string | null): void {
	selectedAppId.value = id || null
	appSearch.value = ''
	const app = applications.value.find((x) => x.id === id)
	if (!app) {
		emit('update:modelValue', '')
		emit('update:placement', 'any')
		emit('update:pageRoute', '')
		return
	}
	const mode = app.canEvents ? 'event' : 'visit'
	selectedMode.value = mode
	selectMode(mode)
}
function selectMode(mode: TriggerMode): void {
	if (!selectedApp.value) return
	if (mode === 'event' && !canSelectEvent.value) return
	if (mode === 'visit' && !canSelectPage.value) return
	selectedMode.value = mode
	emit('update:pageRoute', '')
	if (mode === 'visit') {
		emit('update:placement', selectedApp.value.id)
		emit('update:modelValue', 'nextcloud.page.opened')
	} else {
		emit('update:placement', 'any')
		const current = selectedApp.value.actions.find(
			(action) => action.key === props.modelValue,
		)
		emit(
			'update:modelValue',
			current?.key
				|| (selectedApp.value.actions.length === 1
					? selectedApp.value.actions[0].key
					: ''),
		)
	}
}
watch(
	[selectedAppId, selectedMode],
	([app, mode]) => {
		void loadRoutes(mode === 'visit' ? app : null)
	},
	{ immediate: true },
)
const selectActionValue = (action: { value: string }): string => action.value
</script>

<template>
	<div class="cc-automation" data-testid="automation-builder">
		<h2 class="cc-automation-title">
			{{ automationHeading }}
		</h2>

		<div
			class="cc-automation-intents"
			role="group"
			:aria-label="triggerSourceLabel">
			<NcButton
				v-for="intent in intents"
				:key="intent.id"
				class="cc-automation-intent-card"
				:pressed="selectedIntent === intent.id"
				:variant="selectedIntent === intent.id ? 'primary' : 'secondary'"
				:aria-label="intent.label"
				@click="chooseIntent(intent.id)">
				<span class="cc-automation-intent-title">
					<NcIconSvgWrapper
						:path="intentIcons[intent.id] || mdiApps"
						:size="20" />
					<strong>{{ intent.label }}</strong>
				</span>
				<span class="cc-automation-intent-description">{{
					intent.description
				}}</span>
			</NcButton>
		</div>

		<div v-if="selectedIntent" class="cc-automation-setup">
			<div v-if="selectedIntent === 'app'" class="cc-automation-configuration">
				<AutomationApplicationSelect
					v-model="appValue"
					:options="appOptions"
					:total="applications.length"
					@search="appSearch = $event" />
				<template v-if="selectedApp">
					<div
						v-if="canSelectEvent && canSelectPage"
						class="cc-automation-mode">
						<NcRadioGroup v-model="modeValue" :label="modeQuestionLabel">
							<div class="cc-automation-mode-options">
								<NcRadioGroupButton
									value="event"
									:label="eventModeLabel" />

								<NcRadioGroupButton
									value="visit"
									:label="pageModeLabel" />
							</div>
						</NcRadioGroup>
					</div>
					<div v-else class="cc-native-hint cc-automation-only-mode">
						{{ canSelectPage ? pagesOnlyLabel : eventsOnlyLabel }}
					</div>

					<div v-if="selectedMode === 'event'" class="cc-automation-field">
						<label for="cc-app-event">{{ applicationEventLabel }}</label>

						<NcSelect
							v-model="actionValue"
							:options="actionOptions"
							:reduce="selectActionValue"
							:clearable="false"
							input-id="cc-app-event"
							label="label"
							:placeholder="selectEventPlaceholder"
							:aria-label-combobox="eventSelectorAriaLabel">
							<template #option="option">
								<div class="cc-automation-option">
									<NcIconSvgWrapper
										:path="mdiCheckCircleOutline"
										:size="18" />
									<span
										><strong>{{ option.label }}</strong
										><small>{{
											option.description
										}}</small></span
									>
								</div>
							</template>
						</NcSelect>
						<p v-if="selectedAction" class="cc-native-hint">
							{{ selectedAction.description }}
						</p>
					</div>

					<AutomationRouteConfiguration
						v-else
						:model-value="pageRoute"
						:page-filters="pageFilters"
						:routes="routes"
						:loading="routeLoading"
						:error="routeError"
						@update:model-value="emit('update:pageRoute', $event)"
						@update:page-filters="emit('update:pageFilters', $event)" />
				</template>
			</div>

			<div
				v-else-if="selectedIntent === 'board'"
				class="cc-automation-configuration">
				<NcNoteCard type="info">{{ boardModeHelp }}</NcNoteCard>
			</div>

			<div
				v-else-if="selectedIntent === 'schedule'"
				class="cc-automation-configuration">
				<p class="cc-native-hint">
					{{ scheduledModeHelp }}
				</p>
			</div>

			<AutomationAdvancedSetup
				v-else
				v-model="advancedValue"
				:sources="advancedSources"
				:campaign-id="campaignId"
				:webhook-example="webhookExample" />
			<div
				v-if="modelValue && selectedIntent && selectedIntent !== 'schedule'"
				class="cc-automation-result"
				role="status">
				<div class="cc-automation-result-label">
					<NcIconSvgWrapper
						:path="modelValue ? mdiCheckCircleOutline : mdiPuzzleOutline"
						:size="18" />
					<strong>{{ summary }}</strong>
				</div>
				<p>{{ explanation }}</p>
			</div>
		</div>
	</div>
</template>

<style lang="scss" scoped>
.cc-automation {
	display: flex;
	flex-direction: column;
	gap: 0;
	min-width: 0;
}

.cc-automation-title {
	margin: 0 0 3px;
	font-size: 19px;
}

.cc-automation-intents {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 10px;
}

.cc-automation-intent-card {
	display: flex !important;
	flex-direction: column;
	width: 100% !important;
	max-width: none !important;
	min-width: 0;
	min-height: 89px !important;
	height: auto !important;
	padding: 12px 13px !important;
	border: 1px solid var(--color-border) !important;
	border-radius: var(--border-radius-large, 12px) !important;
	text-align: start;
	align-items: stretch !important;
	justify-content: flex-start !important;
	gap: 8px;
	white-space: normal;

	&[aria-pressed='true'] {
		border-color: var(--color-primary-element) !important;
		background: var(--color-background-hover) !important;
		color: var(--color-main-text) !important;
	}
}

.cc-automation-intent-card
	:is(:deep(.button-vue__wrapper), :deep(.button-vue__text)) {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 7px;
	min-width: 0;
	white-space: normal;
	width: 100%;
	text-align: start;
}

.cc-automation-intent-title {
	display: flex;
	align-items: center;
	gap: 8px;
	color: var(--color-main-text);
	font-weight: 650;
	line-height: 1.2;
	font-size: 14px;

	strong {
		font-weight: 650;
	}
}

.cc-automation-intent-description {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.4;
	white-space: normal;
}

.cc-automation-setup {
	display: flex;
	flex-direction: column;
	gap: 14px;
	margin-top: 16px;
	padding-top: 14px;
	border-top: 1px solid var(--color-border);
}

.cc-automation-configuration,
.cc-automation-field {
	display: flex;
	flex-direction: column;
	gap: 14px;
	max-width: 760px;
	min-width: 0;
}

.cc-automation-field {
	gap: 6px;

	label {
		font-size: 13px;
		font-weight: 650;
	}

	:deep(.v-select) {
		width: 100%;
		max-width: none;
	}
}

.cc-automation-option {
	display: flex;
	align-items: center;
	gap: 9px;
	min-width: 0;

	> span {
		display: flex;
		flex-direction: column;
		gap: 2px;
		min-width: 0;
	}

	strong {
		font-size: 13px;
		font-weight: 650;
	}
}

.cc-automation-option small,
.cc-native-hint,
.cc-automation-only-mode {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	white-space: normal;
}

.cc-native-hint {
	margin: 3px 0 0;
}

.cc-automation-mode {
	min-width: 0;
}

.cc-automation-mode-options {
	display: flex;
	gap: 9px;
	flex-wrap: wrap;

	> * {
		flex: 0 1 auto;
		min-width: 175px;
	}
}

.cc-automation-result {
	margin-top: 3px;
	padding: 10px 12px;
	border-radius: 8px;
	border: 1px solid var(--color-border);
	background: var(--color-background-hover);
}

.cc-automation-result-label {
	display: flex;
	gap: 8px;
	align-items: center;
	min-width: 0;

	strong {
		font-size: 13px;
		overflow-wrap: anywhere;
	}
}

.cc-automation-result p {
	margin: 3px 0 0 26px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

@media (max-width: 800px) {
	.cc-automation-intents {
		grid-template-columns: 1fr;
	}

	.cc-automation-intent-card {
		min-height: 68px !important;
	}

	.cc-automation-mode-options {
		flex-direction: column;

		> * {
			min-width: 0;
		}
	}
}
</style>
