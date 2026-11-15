<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { requestApi } from '../services/api'
import { generateUrl } from '@nextcloud/router'
import type {
	AutomationCatalog,
	NativeIntent,
	NativeSource,
	NativeAction,
	NativeApplication,
	TriggerIntent,
	TriggerMode,
	PageRouteFilters,
	PageRouteOption,
} from '../types/communications'

import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiApps,
	mdiCalendarMonthOutline,
	mdiSourceBranch,
	mdiFolderOutline,
	mdiShareVariantOutline,
	mdiAccountOutline,
	mdiCommentTextOutline,
	mdiPuzzleOutline,
	mdiCheckCircleOutline,
	mdiOpenInNew,
	mdiWeb,
	mdiHelpCircleOutline,
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
const routes = ref<PageRouteOption[]>([])
const routeLoading = ref(false)
const routeError = ref('')
let routeGeneration = 0

const intentIcons: Record<TriggerIntent, string> = {
	app: mdiApps,
	schedule: mdiCalendarMonthOutline,
	advanced: mdiSourceBranch,
	board: mdiBullhornOutline,
}
const sourceIcons: Record<string, string> = {
	files: mdiFolderOutline,
	files_sharing: mdiShareVariantOutline,
	core: mdiAccountOutline,
	comments: mdiCommentTextOutline,
	dashboard: mdiWeb,
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
const filteredApplications = computed(() => {
	const term = appSearch.value.trim().toLocaleLowerCase()
	if (!term) return applications.value
	return applications.value.filter((app) =>
		[
			app.label,
			app.description,
			...(app.keywords || []),
			...(app.actions || []).flatMap((action) => [
				action.label,
				action.description,
			]),
		]
			.join(' ')
			.toLocaleLowerCase()
			.includes(term),
	)
})
const appOptions = computed(() => {
	const chosen = selectedApp.value
	const first = filteredApplications.value.slice(0, MAX_OPTIONS)
	return chosen && !first.some((app) => app.id === chosen.id)
		? [chosen, ...first.slice(0, MAX_OPTIONS - 1)]
		: first
})
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
// Do not use an empty value for NcSelect: it is interpreted as "no selection"
// and the placeholder overlaps the selected label on some Nextcloud themes.
const ANY_PAGE = '__any_page__'
const pageRouteValue = computed({
	get: () => props.pageRoute || ANY_PAGE,
	set: (route: string) => {
		emit('update:pageRoute', !route || route === ANY_PAGE ? '' : route)
		emit('update:pageFilters', {})
	},
})
const routeOptions = computed(() => [
	{
		value: ANY_PAGE,
		label: t('communications', 'Any page in this app'),
		description: t('communications', 'All recognized interface pages'),
	},
	...routes.value,
])
const routeVariables = computed(() => [
	...new Set(
		[...props.pageRoute.matchAll(/\{([A-Za-z][A-Za-z0-9_]*)\}/g)].map(
			(match) => match[1],
		),
	),
])
function changeRouteFilter(
	name: string,
	field: 'operator' | 'value',
	value: string,
): void {
	const next = { ...props.pageFilters }
	if (field === 'operator' && value === 'any') {
		delete next[name]
	} else {
		const rule = {
			...(next[name] || { operator: 'equals', value: '' }),
			[field]: value,
		}
		if (field === 'operator' && value === 'digits') rule.value = ''
		next[name] = rule
	}
	emit('update:pageFilters', next)
}
watch([() => props.modelValue, () => props.placement], (next, previous) => {
	if (previous[0] !== next[0] || previous[1] !== next[1])
		emit('update:pageFilters', {})
})
const selectedRouteLabel = computed(() =>
	props.pageRoute === ''
		? t('communications', 'Any application page')
		: routes.value.find((route) => route.value === props.pageRoute)?.label
			|| props.pageRoute,
)
const webhookExample = computed(() =>
	JSON.stringify(
		{
			campaignId:
				props.campaignId
				|| t('communications', '<campaign ID after saving>'),
			uid: 'nextcloud_user',
			eventId: 'unique-event-001',
		},
		null,
		2,
	),
)
const summary = computed(() => {
	if (selectedIntent.value === 'board')
		return t('communications', 'Available on activation')
	if (selectedIntent.value === 'schedule')
		return t(
			'communications',
			'During the chosen period, on the next eligible visit',
		)
	if (selectedIntent.value === 'advanced' && selectedAdvanced.value)
		return (
			selectedAdvanced.value.label
			+ ' → '
			+ selectedAdvanced.value.actions[0].label
		)
	if (!selectedApp.value) return t('communications', 'Choose an app')
	if (selectedMode.value === 'visit')
		return selectedApp.value.label + ' → ' + selectedRouteLabel.value
	return selectedAction.value
		? selectedApp.value.label + ' → ' + selectedAction.value.label
		: t('communications', 'Select an application event')
})
const explanation = computed(() => {
	if (selectedIntent.value === 'board')
		return t(
			'communications',
			'On activation, outbound channels are queued; content appears in Inbox if selected, and banners or dialogs appear on the next eligible visit.',
		)
	if (selectedIntent.value === 'schedule')
		return t(
			'communications',
			'The publication becomes eligible only within its availability period; it appears when the user returns to Nextcloud.',
		)
	if (
		selectedIntent.value === 'advanced'
		&& selectedAdvancedId.value === 'webhook'
	)
		return t(
			'communications',
			'The HMAC event specifies campaignId and uid. Only this campaign and the specified user can receive the presentation, subject to audience and frequency limits.',
		)
	if (selectedIntent.value === 'advanced' && selectedAdvancedId.value === 'flow')
		return t(
			'communications',
			'Flow checks the configured conditions before recording the occurrence for that user.',
		)
	if (selectedMode.value === 'visit' && selectedApp.value)
		return props.pageRoute
			? t(
					'communications',
					'Only when the authenticated user opens this interface route. Variable URL parameters are not stored.',
				)
			: t('communications', 'When opening any interface page in ')
					+ selectedApp.value.label
					+ t(
						'communications',
						". API calls, downloads, and other apps' pages do not trigger this campaign.",
					)
	return (
		selectedAction.value?.description
		|| t('communications', 'Choose a trusted event emitted by the application.')
	)
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
async function loadRoutes(app: string | null): Promise<void> {
	const generation = ++routeGeneration
	routes.value = []
	routeError.value = ''
	if (!app) {
		return
	}
	routeLoading.value = true
	try {
		const json = await requestApi<{ routes?: PageRouteOption[] }>(
			'/api/routes?app=' + encodeURIComponent(app),
		)
		if (generation === routeGeneration) routes.value = json.routes || []
	} catch (error) {
		if (generation === routeGeneration)
			routeError.value = t(
				'communications',
				'Could not retrieve pages for this app.',
			)
	} finally {
		if (generation === routeGeneration) routeLoading.value = false
	}
}
watch(
	[selectedAppId, selectedMode],
	([app, mode]) => {
		if (mode === 'visit' && app) void loadRoutes(app)
		else {
			routeGeneration++
			routes.value = []
		}
	},
	{ immediate: true },
)
function sourceIcon(id: string): string {
	return sourceIcons[id] || mdiPuzzleOutline
}
const routeOperatorOptions = [
	{ value: 'any', label: t('communications', 'Any value') },
	{ value: 'equals', label: t('communications', 'Equals') },
	{ value: 'prefix', label: t('communications', 'Starts with') },
	{ value: 'digits', label: t('communications', 'Numbers only') },
]
const selectRouteOperatorValue = (option: { value: string }): string => option.value
const setRouteOperator = (variable: string, value: string | null): void =>
	changeRouteFilter(variable, 'operator', value || 'any')
const setRouteValue = (variable: string, value: string | number): void =>
	changeRouteFilter(variable, 'value', String(value))
const selectApplicationValue = (app: NativeApplication): string => app.id
const selectActionValue = (action: { value: string }): string => action.value
const selectRouteValue = (route: PageRouteOption): string => route.value
const selectSourceValue = (source: NativeSource): string => source.id
function flowLink(): string {
	return generateUrl('/settings/admin/workflow')
}
</script>

<template>
	<div class="cc-automation" data-testid="automation-builder">
		<h2 class="cc-automation-title">
			{{
				t('communications', 'When should the publication become available?')
			}}
		</h2>

		<div
			class="cc-automation-intents"
			role="group"
			:aria-label="t('communications', 'Trigger source')">
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
				<div class="cc-automation-field">
					<div class="cc-field-label">
						<label for="cc-app-source">{{
							t('communications', 'Choose an application *')
						}}</label>
						<NcPopover
							popup-role="dialog"
							:triggers="['click', 'focus', 'hover']"
							placement="bottom-start"
							class="cc-app-help-popover">
							<template #trigger>
								<NcButton
									variant="tertiary"
									:aria-label="
										t(
											'communications',
											'Help: selecting applications',
										)
									"
									:title="
										t(
											'communications',
											'Help about applications',
										)
									">
									<template #icon
										><NcIconSvgWrapper
											:path="mdiHelpCircleOutline"
											:size="20"
									/></template>
								</NcButton>
							</template>
							<p class="cc-help-content">
								{{
									t(
										'communications',
										'Only enabled apps with integrated events or browsable pages are shown. Pages are discovered automatically. Business events require a trusted adapter.',
									)
								}}
							</p>
						</NcPopover>
					</div>
					<NcSelect
						v-model="appValue"
						:options="appOptions"
						:reduce="selectApplicationValue"
						:filterable="false"
						:clearable="true"
						input-id="cc-app-source"
						label="label"
						:placeholder="
							t('communications', 'Search installed apps...')
						"
						:aria-label-combobox="
							t('communications', 'Select application')
						"
						@search="appSearch = $event">
						<template #option="option">
							<div class="cc-automation-option">
								<img
									v-if="option.icon"
									:src="option.icon"
									alt=""
									class="cc-app-icon" />
								<NcIconSvgWrapper
									v-else
									:path="sourceIcon(option.id)"
									:size="20" />
								<span>
									<strong>{{ option.label }}</strong>
									<small>
										{{
											option.canEvents && option.canPages
												? t(
														'communications',
														'Events and pages',
													)
												: option.canEvents
													? t(
															'communications',
															'Events available',
														)
													: t(
															'communications',
															'Pages available',
														)
										}}
									</small>
								</span>
							</div>
						</template>
						<template #selected-option="option">
							<span class="cc-automation-selected">
								<img
									v-if="option.icon"
									:src="option.icon"
									alt=""
									class="cc-app-icon" />
								<NcIconSvgWrapper
									v-else
									:path="sourceIcon(option.id)"
									:size="18" />
								{{ option.label }}
							</span>
						</template>
					</NcSelect>
					<p v-if="applications.length > 12" class="cc-native-hint">
						{{ applications.length }}
						{{
							t(
								'communications',
								'options: search by name to find more.',
							)
						}}
					</p>
				</div>

				<template v-if="selectedApp">
					<div
						v-if="canSelectEvent && canSelectPage"
						class="cc-automation-mode">
						<NcRadioGroup
							v-model="modeValue"
							:label="t('communications', 'What should happen?')">
							<div class="cc-automation-mode-options">
								<NcRadioGroupButton
									value="event"
									:label="
										t('communications', 'An application event')
									" />
								<NcRadioGroupButton
									value="visit"
									:label="t('communications', 'Page visit')" />
							</div>
						</NcRadioGroup>
					</div>
					<div v-else class="cc-native-hint cc-automation-only-mode">
						{{
							canSelectPage
								? t(
										'communications',
										'This app provides interface pages.',
									)
								: t(
										'communications',
										'This app provides integrated events but has no navigation page.',
									)
						}}
					</div>

					<div v-if="selectedMode === 'event'" class="cc-automation-field">
						<label for="cc-app-event">{{
							t('communications', 'Application event *')
						}}</label>
						<NcSelect
							v-model="actionValue"
							:options="actionOptions"
							:reduce="selectActionValue"
							:clearable="false"
							input-id="cc-app-event"
							label="label"
							:placeholder="t('communications', 'Select an event...')"
							:aria-label-combobox="
								t('communications', 'Select application event')
							">
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

					<div v-else class="cc-automation-field">
						<label for="cc-app-route">{{
							t('communications', 'Web page or route *')
						}}</label>
						<NcSelect
							v-model="pageRouteValue"
							:options="routeOptions"
							:reduce="selectRouteValue"
							:clearable="false"
							label="label"
							input-id="cc-app-route"
							:disabled="routeLoading"
							:placeholder="
								routeLoading
									? t('communications', 'Loading pages…')
									: ''
							"
							:aria-label-combobox="
								t('communications', 'Select web page')
							">
							<template #option="option">
								<div class="cc-automation-option">
									<NcIconSvgWrapper :path="mdiWeb" :size="18" />
									<span
										><strong>{{ option.label }}</strong
										><small>{{
											option.description
										}}</small></span
									>
								</div>
							</template>
						</NcSelect>
						<div
							v-if="pageRoute && routeVariables.length"
							class="cc-route-filters"
							role="group"
							:aria-label="
								t('communications', 'Route variable restrictions')
							">
							<h4>
								{{
									t(
										'communications',
										'Filters for this route (optional)',
									)
								}}
							</h4>
							<p class="cc-native-hint">
								{{
									t(
										'communications',
										'Restricts only path variable values; no concrete URL is stored. Use safe comparisons instead of arbitrary regular expressions.',
									)
								}}
							</p>
							<div
								v-for="variable in routeVariables"
								:key="variable"
								class="cc-route-filter">
								<label :for="'cc-route-operator-' + variable"
									>{{ t('communications', 'Variable') }}
									{{ '{' + variable + '}' }}</label
								>
								<NcSelect
									:input-id="'cc-route-operator-' + variable"
									class="cc-route-filter-select"
									:model-value="
										pageFilters[variable]?.operator || 'any'
									"
									:options="routeOperatorOptions"
									:reduce="selectRouteOperatorValue"
									:clearable="false"
									@update:model-value="
										setRouteOperator(variable, $event)
									" />
								<template
									v-if="
										['equals', 'prefix'].includes(
											pageFilters[variable]?.operator,
										)
									">
									<label :for="'cc-route-value-' + variable"
										>{{ t('communications', 'Value for') }}
										{{ variable }}</label
									>
									<NcTextField
										:id="'cc-route-value-' + variable"
										:maxlength="96"
										class="cc-route-filter-value"
										:model-value="
											pageFilters[variable]?.value || ''
										"
										:label="
											t(
												'communications',
												'Value for {variable}',
												{ variable },
											)
										"
										:placeholder="
											t('communications', 'Example: {value}', {
												value:
													variable === 'view'
														? 'recent'
														: '123',
											})
										"
										@update:model-value="
											setRouteValue(variable, $event)
										" />
								</template>
							</div>
						</div>
						<p v-if="routeLoading" class="cc-native-hint">
							{{
								t(
									'communications',
									'Checking the native Nextcloud router...',
								)
							}}
						</p>
						<NcNoteCard v-if="routeError" type="error">{{
							routeError
						}}</NcNoteCard>
						<p class="cc-native-hint">
							{{
								t(
									'communications',
									'Only GET web routes recognized by Nextcloud. API endpoints, OCS routes, files, and private parameters are excluded.',
								)
							}}
						</p>
					</div>
				</template>
			</div>

			<div
				v-else-if="selectedIntent === 'board'"
				class="cc-automation-configuration">
				<NcNoteCard type="info">{{
					t(
						'communications',
						'Without a technical trigger, the publication becomes available in Inbox when activated. Recipients and notifications are configured in the next step.',
					)
				}}</NcNoteCard>
			</div>

			<div
				v-else-if="selectedIntent === 'schedule'"
				class="cc-automation-configuration">
				<p class="cc-native-hint">
					{{
						t(
							'communications',
							"The message will be available during the selected period and appear on the user's next visit.",
						)
					}}
				</p>
			</div>

			<div v-else class="cc-automation-configuration">
				<h3>{{ t('communications', 'Integration or Flow') }}</h3>
				<div class="cc-automation-field">
					<label for="cc-advanced">{{
						t('communications', 'Event source *')
					}}</label>
					<NcSelect
						v-model="advancedValue"
						:options="advancedSources"
						:reduce="selectSourceValue"
						:clearable="true"
						input-id="cc-advanced"
						label="label"
						:placeholder="
							t('communications', 'Choose an integration...')
						" />
				</div>
				<NcNoteCard v-if="selectedAdvanced" type="info">
					<p>{{ selectedAdvanced.setup }}</p>
					<div
						v-if="selectedAdvanced.id === 'webhook'"
						class="cc-automation-webhook">
						<p v-if="!campaignId">
							{{
								t(
									'communications',
									'Save the campaign first to get its identifier and configure the integration.',
								)
							}}
						</p>
						<p v-else>
							{{ t('communications', 'Use the identifier') }}
							{{ campaignId }}
							{{ t('communications', 'in the field') }}
							<code>campaignId</code
							>{{
								t(
									'communications',
									'. The integration does not send the campaign to an entire group.',
								)
							}}
						</p>
						<pre>{{ webhookExample }}</pre>
					</div>
					<div
						v-if="selectedAdvanced.id === 'flow'"
						class="cc-automation-flow">
						<p v-if="campaignId">
							{{
								t(
									'communications',
									"Select this campaign by name in Flow's “Trigger contextual campaign” operation.",
								)
							}}
						</p>
						<p v-else>
							{{
								t(
									'communications',
									'Save the campaign before linking it to the Flow rule.',
								)
							}}
						</p>
						<NcButton
							:text="t('communications', 'Open Nextcloud Flow')"
							:href="flowLink()"
							target="_blank">
							<template #icon
								><NcIconSvgWrapper :path="mdiOpenInNew" :size="18"
							/></template>
						</NcButton>
					</div>
				</NcNoteCard>
				<NcNoteCard v-else type="info"
					>{{
						t(
							'communications',
							'Flow evaluates internal conditions. An HMAC webhook records an event only for the user specified in',
						)
					}}
					<code>uid</code
					>{{
						t('communications', '; it never broadcasts to all users.')
					}}</NcNoteCard
				>
			</div>

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
.cc-native-hint {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	margin: 3px 0 0;
}

.cc-automation {
	display: flex;
	flex-direction: column;
	gap: 14px;
	min-width: 0;
}

.cc-automation-intents {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 10px;
}

.cc-automation-flow {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: center;
	padding-top: 10px;
}

.cc-automation-flow p {
	width: 100%;
}

@media (max-width: 640px) {
	.cc-automation-intents,
	.cc-automation-sources {
		grid-template-columns: 1fr;
	}
}

.cc-automation {
	gap: 0;
}

.cc-automation-title {
	margin: 0 0 3px !important;
	font-size: 19px !important;
}

.cc-automation-intents {
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 9px;
}

.cc-automation-setup {
	margin-top: 18px;
	padding-top: 15px;
	border-top: 1px solid var(--color-border);
	display: flex;
	flex-direction: column;
	gap: 15px;
}

.cc-automation-option {
	display: flex;
	align-items: center;
	gap: 9px;
	min-width: 0;
}

.cc-automation-option > span:last-child {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.cc-automation-option strong {
	font-size: 13px;
	font-weight: 650;
}

.cc-automation-option small {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	white-space: normal;
}

.cc-automation-selected {
	display: flex;
	gap: 6px;
	align-items: center;
	font-size: 13px;
}

.cc-automation-action-field .cc-native-hint {
	margin-top: 6px;
}

.cc-automation-result {
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
}

.cc-automation-result-label strong {
	font-size: 13px;
	overflow-wrap: anywhere;
}

.cc-automation-result p {
	margin: 3px 0 0 26px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.cc-automation-result p.cc-automation-error {
	color: var(--color-error);
}

.cc-automation-flow {
	padding-top: 8px;
}

.cc-automation-flow p {
	margin: 0;
}

@media (max-width: 800px) {
	.cc-automation-intents {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (max-width: 490px) {
	.cc-automation-intents {
		grid-template-columns: 1fr 1fr;
	}
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
}

.cc-automation-intent-card[aria-pressed='true'] {
	border-color: var(--color-primary-element) !important;
	background: var(--color-background-hover) !important;
	color: var(--color-main-text) !important;
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
}

.cc-automation-intent-title strong {
	font-weight: 650;
}

.cc-automation-intent-description {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.4;
	white-space: normal;
}

.cc-automation-setup {
	margin-top: 16px;
	gap: 14px;
}

.cc-automation-configuration {
	display: flex;
	flex-direction: column;
	gap: 14px;
	max-width: 760px;
}

.cc-automation-configuration h3 {
	font-size: 15px;
	font-weight: 650;
	margin: 0;
}

.cc-automation-field {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}

.cc-automation-field label {
	font-size: 13px;
	font-weight: 650;
}

.cc-automation-field :is(:deep(.v-select), .vue-select) {
	width: 100%;
	max-width: none;
}

.cc-automation-field .cc-native-hint {
	margin: 2px 0;
}

.cc-route-filters {
	display: grid;
	gap: 10px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: 10px;
	background: var(--color-main-background);
}

.cc-route-filters h4 {
	font-size: 13px;
	margin: 0;
}

.cc-route-filter {
	display: grid;
	grid-template-columns: minmax(100px, 1fr) minmax(170px, 1fr);
	gap: 7px 12px;
	align-items: center;
}

.cc-route-filter label {
	font-size: 12px;
}

.cc-route-filter-select,
.cc-route-filter-value {
	min-width: 0;
	width: 100%;
	min-height: 38px;
	padding: 7px 10px;
	color: var(--color-main-text);
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	font: inherit;
	font-size: 13px;
}

.cc-route-filter :is(select, input):focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

@media (max-width: 600px) {
	.cc-route-filter {
		grid-template-columns: 1fr;
	}
}

.cc-automation-mode {
	min-width: 0;
}

.cc-automation-mode-options {
	display: flex;
	gap: 9px;
	flex-wrap: wrap;
}

.cc-automation-mode-options > * {
	flex: 0 1 auto;
	min-width: 175px;
}

.cc-automation-only-mode {
	color: var(--color-text-maxcontrast);
}

.cc-automation-result {
	margin-top: 3px;
}

@media (max-width: 800px) {
	.cc-automation-intents {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 800px) {
	.cc-automation-intent-card {
		min-height: 68px !important;
	}
}

@media (max-width: 800px) {
	.cc-automation-mode-options {
		flex-direction: column;
	}
}

@media (max-width: 800px) {
	.cc-automation-mode-options > * {
		min-width: 0;
	}
}

.cc-automation-webhook {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-top: 12px;
	min-width: 0;
}

.cc-automation-webhook p {
	margin: 0;
}

.cc-automation-webhook pre {
	white-space: pre-wrap;
	overflow-wrap: anywhere;
	font-size: 12px;
	line-height: 1.4;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	padding: 12px;
	border-radius: 8px;
	max-width: 100%;
}

.cc-automation-setup {
	margin-top: 16px;
	padding-top: 14px;
}

.cc-field-label {
	display: flex;
	align-items: center;
	gap: 10px;
}

.cc-app-help-popover {
	display: inline-flex;
	align-items: center;
}

.cc-app-help-popover button {
	min-width: 32px;
	min-height: 32px;
}

.cc-help-content {
	max-width: 340px;
	padding: 14px 16px;
	margin: 0;
	font-size: 13px;
	line-height: 1.5;
}

.cc-automation-option img.cc-app-icon,
.cc-automation-selected img.cc-app-icon {
	width: 20px;
	height: 20px;
	flex: none;
	object-fit: contain;
}

.cc-automation-selected img.cc-app-icon {
	width: 18px;
	height: 18px;
}

.cc-banner-type-label .cc-native-hint {
	margin: 4px 0 0;
}

.cc-unified-channel-group .cc-native-hint {
	font-size: 12px;
	margin: 0;
	line-height: 1.45;
}

.cc-gateway-provider-details[open] > p.cc-native-hint {
	margin: 12px 0 4px;
}

.cc-delivery-section > .cc-native-hint {
	max-width: 790px;
	line-height: 1.5;
}
</style>
