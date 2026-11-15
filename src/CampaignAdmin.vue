<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t, getLanguage } from '@nextcloud/l10n'
import {
	campaignRecordToForm,
	serializeCampaignForm,
	validateCampaignPayload,
} from './services/campaignForm'
import type { CampaignSavePayload } from './services/campaignForm'
import { useCampaignStore } from './stores/campaigns'
import { storeToRefs } from 'pinia'
import { fetchCampaignMetrics, persistEditorGroups } from './services/campaigns'
import { useCampaignUserLookup } from './composables/useCampaignUserLookup'
import { intentChangePatch, validateWizardStep } from './services/campaignWizard'
import {
	channelChangePatch,
	gatewayChannelOption,
	noticeChannelOption,
} from './services/deliveryChannelSelection'
import type { WizardIssue } from './services/campaignWizard'

import { computed, onMounted, ref, watch } from 'vue'
import type {
	CampaignRecord,
	CampaignForm,
	CampaignCatalog,
	CampaignMetrics,
	GroupSelection,
	UserSelection,
	ChannelNoticeField,
	TranslatedPublicationField,
	TriggerIntent,
} from './types/communications'
const props = withDefaults(defineProps<{ createRequest?: number }>(), {
	createRequest: 0,
})
const emit = defineEmits<{ 'editor-view': [view: string] }>()
watch(
	() => props.createRequest,
	(value, previous) => {
		if (value !== previous) openCreate()
	},
)
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import CampaignOverview from './components/CampaignOverview.vue'
import CampaignAdminDialogs from './components/CampaignAdminDialogs.vue'
import MarkdownEditor from './components/MarkdownEditor.vue'
import CampaignPresentation from './components/CampaignPresentation.vue'
import CampaignWizardSidebar from './components/CampaignWizardSidebar.vue'
import AutomationBuilder from './components/AutomationBuilder.vue'
import DeliveryChannelOption from './components/DeliveryChannelOption.vue'
import {
	BANNER_COLOR_PALETTE,
	BANNER_ICON_OPTIONS,
	DEFAULT_BANNER_COLOR,
} from './bannerAppearance.ts'
import {
	mdiApps,
	mdiMessageTextOutline,
	mdiCheckCircleOutline,
	mdiWindowMaximize,
	mdiDockTop,
	mdiInformation,
	mdiCheckboxMarkedCircle,
	mdiAlert,
	mdiAlertDecagram,
	mdiPaletteOutline,
	mdiMinusCircleOutline,
} from '@mdi/js'

const audienceOptions = [
	// TRANSLATORS Audience scope covering all authenticated Nextcloud accounts.
	{ label: t('communications', 'All signed-in users'), value: 'all' },
	// TRANSLATORS Audience scope restricted to Nextcloud administrators.
	{ label: t('communications', 'Administrators only'), value: 'admins' },
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ label: t('communications', 'One or more Nextcloud groups'), value: 'group' },
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ label: t('communications', 'Specific users'), value: 'users' },
]
const publishOptions = [
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ label: t('communications', 'Draft (do not publish or send)'), value: 'draft' },
	{
		// TRANSLATORS Descriptive message shown in the publications management screen.
		label: t('communications', 'Active (publish and send when eligible)'),
		value: 'active',
	},
	{
		// TRANSLATORS Action or instruction in the publications management screen.
		label: t('communications', 'Paused (do not display or send)'),
		value: 'paused',
	},
]
const placementOptions = computed(() =>
	(
		catalog.value.automation?.placements || [
			// TRANSLATORS Short label for an option or field in the publications management screen.
			{ label: t('communications', 'Any supported page'), value: 'any' },
			{ label: 'Files', value: 'files' },
			{ label: 'Dashboard', value: 'dashboard' },
			// TRANSLATORS Location inside the Nextcloud user settings pages.
			{ label: t('communications', 'Personal settings'), value: 'settings' },
			{
				// TRANSLATORS Location inside the Nextcloud administrator settings.
				label: t('communications', 'Administration settings'),
				value: 'admin_settings',
			},
		]
	).map((p) => ({ label: p.label, value: p.value })),
)
const targetModeOptions = [
	{
		value: 'area',
		// TRANSLATORS Only display the publication within one chosen Nextcloud application area.
		label: t('communications', 'Selected application area'),
	},
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ value: 'all', label: t('communications', 'All supported applications') },
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ value: 'only', label: t('communications', 'Only selected applications') },
	{
		value: 'exclude',
		// TRANSLATORS Short label for an option or field in the publications management screen.
		label: t('communications', 'All except selected applications'),
	},
]
const targetAppOptions = computed(() =>
	placementOptions.value.filter((p) => p.value !== 'any'),
)
const frequencyOptions = [
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ label: t('communications', 'On each occurrence'), value: 'event' },
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ label: t('communications', 'Once per publication and user'), value: 'once' },
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ label: t('communications', 'At most once every 24 hours'), value: 'daily' },
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ label: t('communications', 'At most once every 7 days'), value: 'weekly' },
	// TRANSLATORS Frequency option with a user-defined minimum delay.
	{ label: t('communications', 'Custom interval'), value: 'custom' },
]
const excludedMatchOptions = [
	{
		// TRANSLATORS Short label for an option or field in the publications management screen.
		label: t('communications', 'Exclude members of any group (OR)'),
		value: 'any',
	},
	{
		// TRANSLATORS Descriptive message shown in the publications management screen.
		label: t('communications', 'Exclude only members of every group (AND)'),
		value: 'all',
	},
]
const groupMatchOptions = [
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ label: t('communications', 'Any selected group (OR)'), value: 'any' },
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ label: t('communications', 'All selected groups (AND)'), value: 'all' },
]
const categoryOptions = [
	{
		// TRANSLATORS Type of announcement sent by an organization, not a paid promotion.
		label: t('communications', 'Institutional announcement'),
		value: 'institutional',
	},
	// TRANSLATORS Label for promotional announcements that may require recipient consent.
	{ label: t('communications', 'Sponsored content'), value: 'sponsored' },
]
const themeOptions = [
	// TRANSLATORS Informational visual theme or notification style, not a menu command.
	{ label: t('communications', 'Information'), value: 'info' },
	// TRANSLATORS Visual theme used for a successful or positive announcement.
	{ label: t('communications', 'Success'), value: 'success' },
	// TRANSLATORS Visual theme used for an important cautionary announcement.
	{ label: t('communications', 'Warning'), value: 'warning' },
	// TRANSLATORS Visual theme for a problem or failure notice.
	{ label: t('communications', 'Error'), value: 'error' },
	// TRANSLATORS Visual theme without a particular status or emphasis.
	{ label: t('communications', 'Neutral'), value: 'neutral' },
	// TRANSLATORS Visual theme configurable by the editor.
	{ label: t('communications', 'Custom'), value: 'custom' },
]
// Match the native NcNoteCard variants and icons used by Nextcloud.
const bannerTypes = [
	{
		value: 'info',
		// TRANSLATORS Informational visual theme or notification style, not a menu command.
		label: t('communications', 'Information'),
		icon: mdiInformation,
	},
	{
		value: 'success',
		// TRANSLATORS Visual theme used for a successful or positive announcement.
		label: t('communications', 'Success'),
		icon: mdiCheckboxMarkedCircle,
	},
	// TRANSLATORS Visual theme used for an important cautionary announcement.
	{ value: 'warning', label: t('communications', 'Warning'), icon: mdiAlert },
	// TRANSLATORS Visual theme for a problem or failure notice.
	{ value: 'error', label: t('communications', 'Error'), icon: mdiAlertDecagram },
	{
		value: 'neutral',
		// TRANSLATORS Visual theme without a particular status or emphasis.
		label: t('communications', 'Neutral'),
		icon: mdiMinusCircleOutline,
	},
	{
		value: 'custom',
		// TRANSLATORS Visual theme configurable by the editor.
		label: t('communications', 'Custom'),
		icon: mdiPaletteOutline,
	},
]
const selectable = (option: { value: string }): string => option.value
const campaignStore = useCampaignStore()
const { campaigns, catalog, loading } = storeToRefs(campaignStore)
const saving = ref(false)
const view = ref('list')
watch(view, (value) => emit('editor-view', value), { immediate: true })
const editorId = ref<number | null>(null)
const previewOpen = ref(false)
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

const feedback = ref('')
const feedbackType = ref<'info' | 'success' | 'warning' | 'error'>('info')
const errorMessage = ref('')
const confirmArchiveOpen = ref(false)
const confirmDeleteOpen = ref(false)
const deleteTarget = ref<CampaignRecord | null>(null)
const deleteName = ref('')
const deleting = ref(false)
const archiveTarget = ref<CampaignRecord | null>(null)

const archiveConfirmationMessage = computed(() => {
	// TRANSLATORS {name} is the publication being archived; this sentence may be reordered for different languages.
	return t(
		'communications',
		'Archive “{name}”? It will no longer be displayed and can be restored later.',
		{ name: archiveTarget.value?.name || '' },
	)
})
const orderSaving = ref(false)
const previewCampaign = ref<CampaignRecord | null>(null)
const editorGroupValues = ref<string[]>([])
const editorConfigLoading = ref(false)
const selectedGroups = ref<GroupSelection[]>([])
const selectedExcludedGroups = ref<GroupSelection[]>([])
const selectedUsers = ref<UserSelection[]>([])
const metricsOpen = ref(false)
const metricsLoading = ref(false)
const metricsData = ref<CampaignMetrics | null>(null)
const metricsCampaign = ref<CampaignRecord | null>(null)

const userLookup = useCampaignUserLookup()
const userSearchOptions = userLookup.options
const form = ref<CampaignForm>(makeDefault())

// Outbound channels, Inbox history and presentation are independent.
const hasAdditionalNotices = computed(
	() =>
		!!(
			form.value.notify_users
			|| form.value.notify_activity
			|| form.value.notify_email
			|| (form.value.gateway_channels || []).length
		),
)
const eventDrivenDelivery = computed(
	() =>
		!!form.value.trigger_key
		&& !['nextcloud.board.published', 'nextcloud.time.window'].includes(
			form.value.trigger_key,
		),
)
const hasDestination = computed(
	() =>
		['banner', 'modal'].includes(form.value.presentation)
		|| inboxCopy.value
		|| hasAdditionalNotices.value,
)
const destinationCount = computed(
	() =>
		(['banner', 'modal'].includes(form.value.presentation) ? 1 : 0)
		+ (inboxCopy.value ? 1 : 0)
		+ [
			form.value.notify_users,
			form.value.notify_activity,
			form.value.notify_email,
		].filter(Boolean).length
		+ (form.value.gateway_channels || []).length,
)
const inboxCopy = computed({
	get: () => !!form.value.board_visible,
	set: (enabled) => {
		if (form.value.guest_visible) return
		form.value.board_visible = !!enabled
	},
})
const deliveryChannelDefinitions: Array<{
	id: string
	field: ChannelNoticeField
	label: string
	description: string
}> = [
	{
		id: 'notifications',
		field: 'notify_users',
		// TRANSLATORS Delivery channel using the Nextcloud notification bell.
		label: t('communications', 'Nextcloud Notifications'),
		// TRANSLATORS Descriptive message shown in the publications management screen.
		description: t(
			'communications',
			'Notification in the bell menu. Requires the Notifications app.',
		),
	},
	{
		id: 'activity',
		field: 'notify_activity',
		// TRANSLATORS Delivery channel recording an entry in the Nextcloud Activity app.
		label: t('communications', 'Activity feed'),
		// TRANSLATORS Descriptive message shown in the publications management screen.
		description: t(
			'communications',
			'Records the publication in the Activity feed without showing a banner or dialog.',
		),
	},
	{
		id: 'email',
		field: 'notify_email',
		label: 'E-mail',
		// TRANSLATORS Action or instruction in the publications management screen.
		description: t(
			'communications',
			'Sends the message to recipients with valid addresses.',
		),
	},
]
const gatewayDefinitions = [
	{
		id: 'gateway:sms',
		label: 'SMS',
		// TRANSLATORS Descriptive message shown in the publications management screen.
		description: t(
			'communications',
			'International phone number, e.g. +5521999999999.',
		),
	},
	{
		id: 'gateway:signal',
		label: 'Signal',
		// TRANSLATORS Descriptive message shown in the publications management screen.
		description: t('communications', 'Signal number, e.g. +5521999999999.'),
	},
	{
		id: 'gateway:telegram',
		label: 'Telegram',
		// TRANSLATORS Short label for an option or field in the publications management screen.
		description: t('communications', 'Private bot chat identifier.'),
	},
	{
		id: 'gateway:gowhatsapp',
		label: 'WhatsApp',
		// TRANSLATORS Descriptive message shown in the publications management screen.
		description: t('communications', 'WhatsApp number in international format.'),
	},
	{
		id: 'gateway:whatsappbusiness',
		label: 'WhatsApp Business',
		// TRANSLATORS Help text for WhatsApp Business; sending depends on provider templates and policies.
		description: t(
			'communications',
			"Subject to the provider's policies and approved templates.",
		),
	},
	{
		id: 'gateway:xmpp',
		label: 'XMPP',
		// TRANSLATORS Short label for an option or field in the publications management screen.
		description: t('communications', 'Recipient JID address.'),
	},
]
const channelLabels = {
	// TRANSLATORS Status shown when a channel has no eligible recipients.
	noRecipients: t('communications', 'No recipients'),
	// TRANSLATORS Status for a selected delivery channel whose provider is unavailable.
	selectedUnavailable: t('communications', 'Selected · unavailable'),
	// TRANSLATORS Provider availability status while integration checks run.
	checking: t('communications', 'Checking…'),
	// TRANSLATORS Explanation that public visitor banners do not use personal gateway addresses.
	guestGatewayHelp: t(
		'communications',
		'Anonymous public banners cannot send individual messages.',
	),
	// TRANSLATORS Status while an external gateway provider is checked.
	checkingGateway: t('communications', 'Checking provider availability.'),
	// TRANSLATORS Delivery channel status when there is no individual recipient.
	noIndividualDelivery: t('communications', 'No individual delivery'),
	// TRANSLATORS Explanation that anonymous banners cannot also send notifications.
	guestNoticeHelp: t(
		'communications',
		'Anonymous public banners do not send individual messages. Disable guest mode to configure recipients and channels.',
	),
	// TRANSLATORS Help while a Nextcloud delivery app is being checked.
	checkingNotice: t(
		'communications',
		'Availability of this channel is still being checked.',
	),
}
const gatewayOptions = computed(() =>
	gatewayDefinitions.map((definition) =>
		gatewayChannelOption(
			definition,
			form.value,
			catalog.value.deliveryChannels || [],
			channelLabels,
		),
	),
)
const gatewayGroup = computed(() =>
	(catalog.value.deliveryChannels || []).find(
		(item) => item.id === 'twofactor_gateway',
	),
)
const gatewayReadyCount = computed(
	() => gatewayOptions.value.filter((x) => x.ready).length,
)

const gatewayProviderCountLabel = computed(() => {
	if (!gatewayGroup.value?.installed) return adminAppNotInstalledLabel
	// TRANSLATORS {ready} is the number of configured messaging providers; {total} is the total number of available providers.
	return t('communications', '{ready} of {total} providers configured', {
		ready: gatewayReadyCount.value,
		total: gatewayOptions.value.length,
	})
})

const deliveryChannelOptions = computed(() =>
	deliveryChannelDefinitions.map((definition) =>
		noticeChannelOption(
			definition,
			form.value,
			catalog.value.deliveryChannels || [],
			channelLabels,
		),
	),
)
function setNoticeChannel(field: ChannelNoticeField, enabled: boolean): void {
	const channel = deliveryChannelDefinitions.find((item) => item.field === field)
	if (channel) onDeliveryChannelChange({ id: channel.id, enabled })
}
function onDeliveryChannelChange(change: { id: string; enabled: boolean }): void {
	Object.assign(
		form.value,
		channelChangePatch(
			form.value,
			change,
			catalog.value.deliveryChannels,
			gatewayDefinitions.map((gateway) => gateway.id),
			deliveryChannelDefinitions,
		),
	)
}
const noticeUsers = computed({
	get: () => !!form.value.notify_users,
	set: (enabled) => setNoticeChannel('notify_users', enabled),
})
const noticeActivity = computed({
	get: () => !!form.value.notify_activity,
	set: (enabled) => setNoticeChannel('notify_activity', enabled),
})
const noticeEmail = computed({
	get: () => !!form.value.notify_email,
	set: (enabled) => setNoticeChannel('notify_email', enabled),
})
// All destinations use the same channel-style switch. Only these two
// visual destinations are mutually exclusive, enforced by presentation.
const visualDeliveryOptions = computed(() => [
	{
		id: 'banner',
		// TRANSLATORS Display mode showing a message inline on a Nextcloud page.
		label: t('communications', 'Contextual banner'),
		// TRANSLATORS Descriptive message shown in the publications management screen.
		description: t(
			'communications',
			'Non-intrusive notice on the page during the next eligible visit or event.',
		),
		selected: form.value.presentation === 'banner',
		selectable: true,
		availability: 'ready',
		status:
			form.value.presentation === 'banner'
				? // TRANSLATORS Status of a delivery method or visual destination selected by the editor.
					t('communications', 'Selected')
				: // TRANSLATORS Status of a delivery method or visual destination that can be used.
					t('communications', 'Available'),
		// TRANSLATORS Explanatory help text shown in the publications management screen.
		help: t(
			'communications',
			'The banner appears during navigation. Selecting it disables the modal but keeps other channels.',
		),
	},
	{
		id: 'modal',
		// TRANSLATORS Display mode showing a message in an overlay.
		label: t('communications', 'Modal dialog'),
		// TRANSLATORS Descriptive message shown in the publications management screen.
		description: t(
			'communications',
			'Dialog that requires interaction on the next eligible visit or event.',
		),
		selected: form.value.presentation === 'modal',
		selectable: !form.value.guest_visible,
		availability: form.value.guest_visible ? 'guest' : 'ready',
		status: form.value.guest_visible
			? // TRANSLATORS Empty state or unavailable-condition message in the publications management screen.
				t('communications', 'Not available to guests')
			: form.value.presentation === 'modal'
				? // TRANSLATORS Status of a delivery method or visual destination selected by the editor.
					t('communications', 'Selected')
				: // TRANSLATORS Status of a delivery method or visual destination that can be used.
					t('communications', 'Available'),
		// TRANSLATORS Explanatory help text shown in the publications management screen.
		help: t(
			'communications',
			'Modal and banner are alternatives. Selecting one disables the other; email, history, and notifications remain.',
		),
	},
])
const inboxDeliveryOption = computed(() => ({
	id: 'inbox',
	// TRANSLATORS The Communications app message Inbox, distinct from the Nextcloud Mail app.
	label: t('communications', 'Inbox'),
	// TRANSLATORS Descriptive message shown in the publications management screen.
	description: t(
		'communications',
		'Keeps the publication available in Nextcloud, with optional comments.',
	),
	selected: inboxCopy.value,
	selectable: !form.value.guest_visible,
	availability: form.value.guest_visible ? 'guest' : 'ready',
	status: form.value.guest_visible
		? // TRANSLATORS Empty state or unavailable-condition message in the publications management screen.
			t('communications', 'Not available to guests')
		: inboxCopy.value
			? // TRANSLATORS Status of a delivery method or visual destination selected by the editor.
				t('communications', 'Selected')
			: // TRANSLATORS Status of a delivery method or visual destination that can be used.
				t('communications', 'Available'),
	// TRANSLATORS Explanatory help text shown in the publications management screen.
	help: t(
		'communications',
		'Inbox is an independent destination. If disabled, messages sent through other channels open only their authorized details.',
	),
}))

const deliveryReview = computed(() => {
	const surfaces = []
	if (form.value.presentation === 'banner')
		// TRANSLATORS Display mode showing a message inline on a Nextcloud page.
		surfaces.push(t('communications', 'Contextual banner'))
	if (form.value.presentation === 'modal')
		// TRANSLATORS Display mode showing a message in an overlay.
		surfaces.push(t('communications', 'Modal dialog'))
	// TRANSLATORS The Communications app message Inbox, distinct from the Nextcloud Mail app.
	if (inboxCopy.value) surfaces.push(t('communications', 'Inbox'))

	if (form.value.notify_users)
		// TRANSLATORS Delivery channel using the Nextcloud notification bell.
		surfaces.push(t('communications', 'Nextcloud Notifications'))
	if (form.value.notify_activity)
		// TRANSLATORS Delivery channel recording an entry in the Nextcloud Activity app.
		surfaces.push(t('communications', 'Activity feed'))
	if (form.value.notify_email) surfaces.push('E-mail')
	for (const id of form.value.gateway_channels || []) {
		const gateway = gatewayDefinitions.find(
			(item) => item.id === 'gateway:' + id,
		)
		if (gateway) surfaces.push(gateway.label + ' (Two-Factor Gateway)')
	}
	// TRANSLATORS Empty state or unavailable-condition message in the publications management screen.
	return surfaces.join(' · ') || t('communications', 'No destination selected')
})
const selectedBannerType = computed(() =>
	bannerTypes.some((item) => item.value === form.value.theme)
		? form.value.theme
		: 'info',
)
const bannerIconOptions = BANNER_ICON_OPTIONS.map(({ value, label }) => ({
	value,
	label,
}))
const colorPickerPalette = BANNER_COLOR_PALETTE.map(({ color }) => color)
const languageOptions = [
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ value: 'pt_BR', label: t('communications', 'Portuguese (Brazil)') },
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ value: 'en_US', label: t('communications', 'English (United States)') },
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ value: 'es_ES', label: t('communications', 'Spanish') },
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ value: 'fr_FR', label: t('communications', 'French') },
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ value: 'de_DE', label: t('communications', 'German') },
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ value: 'it_IT', label: t('communications', 'Italian') },
	// TRANSLATORS Short label for an option or field in the publications management screen.
	{ value: 'ar', label: t('communications', 'Arabic (RTL)') },
]
const translationLocale = ref('en_US')
function translatedField(field: TranslatedPublicationField) {
	return computed({
		get: () =>
			form.value.translated_content?.[translationLocale.value]?.[field] || '',
		set: (value) => {
			form.value.translated_content = {
				...(form.value.translated_content || {}),
				[translationLocale.value]: {
					...(form.value.translated_content?.[translationLocale.value]
						|| {}),
					[field]: value || '',
				},
			}
		},
	})
}
const translatedHeadline = translatedField('headline')
const translatedMessage = translatedField('message')
const translatedCta = translatedField('cta_label')
const selectedTextColor = computed({
	get: () => form.value.text_color || undefined,
	set: (value) => {
		form.value.text_color = value || ''
	},
})
const alignments = [
	// TRANSLATORS Text alignment option for the announcement banner.
	{ value: 'left', label: t('communications', 'Left') },
	// TRANSLATORS Text alignment option for the announcement banner.
	{ value: 'center', label: t('communications', 'Center') },
	// TRANSLATORS Text alignment option for the announcement banner.
	{ value: 'right', label: t('communications', 'Right') },
]
const automationReady = computed(() => {
	const draft = form.value
	if (!draft.trigger_key) return false
	if (draft.trigger_key === 'nextcloud.board.published') return true
	if (draft.trigger_key === 'nextcloud.time.window') {
		return (
			draft.starts_at instanceof Date
			&& !Number.isNaN(draft.starts_at.getTime())
			&& (!(draft.ends_at instanceof Date) || draft.ends_at > draft.starts_at)
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

function makeDefault(): CampaignForm {
	return {
		name: '',
		description: '',
		headline: '',
		message: '',
		cta_label: '',
		cta_url: '',
		trigger_key: '',
		page_route: '',
		page_filters: {},
		audience_type: 'all',
		audience_groups: [],
		audience_users: [],
		presentation: 'banner',
		status: 'draft',
		priority: 10,
		placement: 'any',
		app_target_mode: 'area',
		app_target_apps: [],
		frequency: 'event',
		min_interval_minutes: 0,
		max_total: 0,
		max_per_day: 0,
		category: 'institutional',
		theme: 'info',
		banner_color: DEFAULT_BANNER_COLOR,
		banner_icon: 'auto',
		board_visible: false,
		guest_visible: false,
		comments_enabled: true,
		dismissible: true,
		notify_users: false,
		notify_activity: false,
		notify_email: false,
		gateway_channels: [],
		text_align: 'left',
		text_color: '',
		manual_order: 0,
		translated_content: {},
		group_match: 'any',
		excluded_match: 'any',
		excluded_groups: [],
		starts_at: null,
		ends_at: null,
	}
}

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
	return [...selectedUsers.value, ...userSearchOptions.value].filter((option) => {
		if (ids.has(option.id)) return false
		ids.add(option.id)
		return true
	})
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

const summaryAction = computed(
	() =>
		triggerOptions.value.find((t) => t.value === form.value.trigger_key)?.label
		// TRANSLATORS Instruction to choose a triggering event for the publication.
		|| t('communications', 'Select an action'),
)
const sourceForReview = computed(() =>
	catalog.value.automation?.sources?.find((source) =>
		source.actions?.some((action) => action.key === form.value.trigger_key),
	),
)
const summaryWhen = computed(() => {
	// TRANSLATORS The publication trigger or delivery channel has not been configured.
	if (!form.value.trigger_key) return t('communications', 'Not configured')
	if (form.value.trigger_key === 'nextcloud.page.opened') {
		const place =
			placementOptions.value.find((p) => p.value === form.value.placement)
				?.label || form.value.placement
		const filters = Object.entries(form.value.page_filters || {})
			.map(
				([name, rule]) =>
					'{'
					+ name
					+ '} '
					+ ({
						equals: '=',
						// TRANSLATORS Operator for matching a URL route variable by its prefix.
						prefix: t('communications', 'starts with'),
						// TRANSLATORS Operator restricting a route variable to digits.
						digits: t('communications', 'numeric'),
					}[rule.operator] || rule.operator)
					+ ' '
					+ (rule.value || ''),
			)
			.join('; ')
		return (
			// TRANSLATORS Prefix before a destination page label in the publication review.
			t('communications', 'Open ')
			+ place
			+ (form.value.page_route
				? ' · ' + form.value.page_route
				: // TRANSLATORS Suffix meaning any page of the selected application will match.
					t('communications', ' · any page'))
			+ (filters ? ' · ' + filters : '')
		)
	}
	return sourceForReview.value
		? sourceForReview.value.label + ' → ' + summaryAction.value
		: summaryAction.value
})
const audienceReview = computed(() => {
	if (form.value.audience_type === 'users')
		return (
			selectedUsers.value.map((u) => u.displayName || u.id).join(', ')
			// TRANSLATORS Audience review when no individual account is selected.
			|| t('communications', 'No user selected')
		)
	if (form.value.audience_type === 'group') {
		const ids = selectedGroups.value.map((g) => g.displayName || g.id)
		return ids.length
			? ids.join(
					form.value.group_match === 'all'
						? // TRANSLATORS Logical operator joining required audience groups.
							t('communications', ' AND ')
						: // TRANSLATORS Logical operator joining alternative audience groups.
							t('communications', ' OR '),
				)
			: // TRANSLATORS Audience review when no group is selected.
				t('communications', 'No group selected')
	}
	// TRANSLATORS Audience scope covering all authenticated Nextcloud accounts.
	return t('communications', 'All signed-in users')
})
const locationReview = computed(() =>
	form.value.presentation === 'none'
		? inboxCopy.value
			? // TRANSLATORS Short label for an option or field in the publications management screen.
				t('communications', 'Inbox only; no banner or dialog')
			: // TRANSLATORS Publication that does not show a banner or modal.
				t('communications', 'No on-screen display')
		: placementOptions.value.find((x) => x.value === form.value.placement)?.label
			|| form.value.placement,
)
function dailyFrequencyLabel(count: number): string {
	// TRANSLATORS {count} is the maximum number of deliveries allowed in 24 hours.
	return t('communications', '{count} in 24 hours', { count })
}
const frequencyReview = computed(() => {
	if (eventDrivenDelivery.value) {
		// TRANSLATORS Descriptive message shown in the publications management screen.
		return t(
			'communications',
			'On each occurrence, respecting frequency and deduplication limits',
		)
	}
	if (!['banner', 'modal'].includes(form.value.presentation)) {
		// TRANSLATORS Descriptive message shown in the publications management screen.
		return t('communications', 'Outbound channels: one batch per activation')
	}
	const freq =
		frequencyOptions.find((x) => x.value === form.value.frequency)?.label
		|| form.value.frequency
	const caps = [
		form.value.max_total > 0
			? // TRANSLATORS Prefix of a maximum total delivery limit.
				t('communications', 'maximum ')
				+ form.value.max_total
				// TRANSLATORS Suffix of a maximum total delivery limit for each recipient.
				+ t('communications', ' per user')
			: '',
		form.value.max_per_day > 0
			? dailyFrequencyLabel(form.value.max_per_day)
			: '',
	].filter(Boolean)
	return caps.length ? freq + '; ' + caps.join('; ') : freq
})
const scheduleReview = computed(() => {
	const from =
		form.value.starts_at instanceof Date
			? form.value.starts_at.toLocaleDateString(
					getLanguage().replace('_', '-'),
				)
			: ''
	const until =
		form.value.ends_at instanceof Date
			? form.value.ends_at.toLocaleDateString(getLanguage().replace('_', '-'))
			: ''
	return from || until
		? // TRANSLATORS Prefix before the start date of the publication window.
			t('communications', 'From ')
				// TRANSLATORS Beginning of the publication window at the current time.
				+ (from || t('communications', 'now'))
				// TRANSLATORS Separator before the publication end date.
				+ t('communications', ' until ')
				// TRANSLATORS Publication window without an expiration date.
				+ (until || t('communications', 'no end date'))
		: // TRANSLATORS Publication with an unlimited availability window.
			t('communications', 'No date limit')
})

async function saveEditorGroups() {
	if (editorConfigLoading.value || !catalog.value.canConfigureEditors) return
	editorConfigLoading.value = true
	try {
		editorGroupValues.value = await persistEditorGroups(editorGroupValues.value)
		// TRANSLATORS Action or instruction in the publications management screen.
		flash(t('communications', 'Editorial groups updated.'), 'success')
	} catch (e) {
		flash(errorMessageFor(e), 'error')
	} finally {
		editorConfigLoading.value = false
	}
}
async function updateStatus(c: CampaignRecord, status: string) {
	try {
		await campaignStore.updateStatus(c.id, status)
		flash(
			status === 'active'
				? // TRANSLATORS Short label for an option or field in the publications management screen.
					t('communications', 'Publication activated.')
				: // TRANSLATORS Short label for an option or field in the publications management screen.
					t('communications', 'Status updated.'),
			'success',
		)
	} catch (e) {
		flash(errorMessageFor(e), 'error')
	}
}
async function openMetrics(c: CampaignRecord) {
	metricsCampaign.value = c
	metricsData.value = null
	metricsOpen.value = true
	metricsLoading.value = true
	try {
		metricsData.value = await fetchCampaignMetrics(c.id)
	} catch (e) {
		flash(errorMessageFor(e), 'error')
	} finally {
		metricsLoading.value = false
	}
}
async function moveCampaign(
	c: CampaignRecord,
	direction: string,
	targetId: number | null = null,
) {
	if (orderSaving.value || c.status === 'archived') return
	orderSaving.value = true
	try {
		await campaignStore.reorder(c.id, direction, targetId)
		// TRANSLATORS Short label for an option or field in the publications management screen.
		flash(t('communications', 'Display order updated.'), 'success')
	} catch (e) {
		flash(errorMessageFor(e), 'error')
	} finally {
		orderSaving.value = false
	}
}
async function duplicate(c: CampaignRecord) {
	try {
		const duplicated = await campaignStore.duplicate(c.id)
		openEdit(duplicated)
		// TRANSLATORS Short label for an option or field in the publications management screen.
		flash(t('communications', 'Copy created as a draft.'), 'success')
	} catch (e) {
		flash(errorMessageFor(e), 'error')
	}
}
function askDelete(c: CampaignRecord) {
	if (c.status !== 'archived') return
	deleteTarget.value = c
	deleteName.value = ''
	confirmDeleteOpen.value = true
}
async function deletePermanently() {
	if (
		deleting.value
		|| !deleteTarget.value
		|| deleteName.value !== deleteTarget.value.name
	)
		return
	deleting.value = true
	const id = deleteTarget.value.id
	try {
		await campaignStore.purge(id, deleteName.value)
		confirmDeleteOpen.value = false
		deleteTarget.value = null
		deleteName.value = ''
		flash(
			// TRANSLATORS Descriptive message shown in the publications management screen.
			t(
				'communications',
				'Publication and delivery history permanently deleted.',
			),
			'success',
		)
	} catch (e) {
		flash(errorMessageFor(e), 'error')
	} finally {
		deleting.value = false
	}
}
function askArchive(c: CampaignRecord) {
	archiveTarget.value = c
	confirmArchiveOpen.value = true
}
async function archive() {
	if (!archiveTarget.value) return
	try {
		await campaignStore.archive(archiveTarget.value.id)
		// TRANSLATORS Short label for an option or field in the publications management screen.
		flash(t('communications', 'Publication archived.'), 'success')
	} catch (e) {
		flash(errorMessageFor(e), 'error')
	}
	confirmArchiveOpen.value = false
	archiveTarget.value = null
}

function openCreate() {
	selectedGroups.value = []
	selectedExcludedGroups.value = []
	selectedUsers.value = []
	userLookup.clear()
	form.value = makeDefault()
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
			groupChoices.value.find((option) => option.id === id)?.displayName || id
		return { id, displayName: label, isNoUser: true }
	})
	selectedUsers.value = (c.audience_users || []).map((id) => ({
		id,
		displayName: id,
		user: id,
	}))
	selectedExcludedGroups.value = (c.excluded_groups || []).map((id) => {
		const label =
			groupChoices.value.find((option) => option.id === id)?.displayName || id
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
		if (new URL(location.href).searchParams.get('view') === 'new') openCreate()
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
// TRANSLATORS Availability indicator when a messaging provider app is absent.
const adminAppNotInstalledLabel = t('communications', 'App not installed')
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
const adminNoContextualDisplayLabel = t('communications', 'No contextual display')
// TRANSLATORS Name of a contextual visual delivery format: banner.
const adminContextualBannerLabel = t('communications', 'Contextual banner')
// TRANSLATORS Name of a contextual visual delivery format: modal dialog.
const adminModalDialogLabel = t('communications', 'Modal dialog')
// TRANSLATORS Short label for an option or field in the publications management screen.
const adminPriority0100Label = t('communications', 'Priority (0–100)')
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
const adminExampleWelcomeNoticeLabel = t('communications', 'Example: Welcome notice')
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
</script>

<template>
	<div class="cc-native-app">
		<div class="cc-native-page">
			<NcNoteCard
				v-if="feedback"
				:type="feedbackType"
				class="cc-native-feedback">
				{{ feedback }}
			</NcNoteCard>

			<CampaignOverview
				v-if="view === 'list'"
				:campaigns="campaigns"
				:catalog="catalog"
				:loading="loading"
				:order-saving="orderSaving"
				:editor-config-loading="editorConfigLoading"
				:group-options="groupOptions"
				:trigger-options="triggerOptions"
				v-model:editor-group-values="editorGroupValues"
				@edit="openEdit"
				@preview="openPreview"
				@metrics="openMetrics"
				@move="moveCampaign"
				@status-change="updateStatus"
				@duplicate="duplicate"
				@delete="askDelete"
				@archive="askArchive"
				@save-editor-groups="saveEditorGroups" />

			<template v-else>
				<div class="cc-native-heading cc-native-heading-editor">
					<div class="cc-wizard-heading">
						<!-- TRANSLATORS Action or instruction in the publications management screen. -->
						<NcButton
							variant="tertiary"
							:text="t('communications', 'Back to publications')"
							@click="cancelEdit" />
						<h1>
							{{
								editMode
									? adminEditPublicationLabel
									: adminNewPublicationLabel
							}}
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
						:variant="
							wizardStep === step.number ? 'primary' : 'tertiary'
						"
						:aria-current="
							wizardStep === step.number ? 'step' : undefined
						"
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

				<NcNoteCard
					v-if="errorMessage"
					type="error"
					class="cc-wizard-error"
					>{{ errorMessage }}</NcNoteCard
				>

				<div
					class="cc-native-editor-layout"
					:class="{ 'cc-wizard-unconfigured': wizardStep === 1 }">
					<div class="cc-native-editor-fields cc-wizard-main">
						<div v-if="wizardStep === 1" class="cc-wizard-step-content">
							<p class="cc-native-hint">
								<!-- TRANSLATORS Action or instruction in the publications management screen. -->
								{{
									t(
										'communications',
										'Choose when the publication becomes available. Display and notification channels are selected together in the next step.',
									)
								}}
							</p>
							<AutomationBuilder
								v-model="form.trigger_key"
								v-model:placement="form.placement"
								v-model:page-route="form.page_route"
								v-model:page-filters="form.page_filters"
								:catalog="catalog.automation"
								:campaign-id="editorId"
								@intent-change="onIntentChange" />
							<section
								v-if="form.trigger_key === 'nextcloud.time.window'"
								class="cc-native-section cc-schedule-inline">
								<h3>
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									{{ t('communications', 'Availability period') }}
								</h3>
								<div class="cc-native-pair">
									<!-- TRANSLATORS Required starting date of publication availability. -->
									<NcDateTimePickerNative
										v-model="form.starts_at"
										:label="t('communications', 'Start *')"
										type="datetime-local"
										clearable />
									<!-- TRANSLATORS Optional end date of publication availability. -->
									<NcDateTimePickerNative
										v-model="form.ends_at"
										:label="
											t('communications', 'End (optional)')
										"
										type="datetime-local"
										clearable />
								</div>
							</section>
						</div>

						<div
							v-else-if="wizardStep === 2"
							class="cc-wizard-step-content">
							<div class="cc-wizard-step-title">
								<h2>
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									{{
										t(
											'communications',
											'Message, audience and delivery',
										)
									}}
								</h2>
								<p>
									<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
									{{
										t(
											'communications',
											'Write a message, select recipients, and configure every channel in one workflow.',
										)
									}}
								</p>
							</div>
							<section class="cc-native-section">
								<h2>
									{{ adminIdentificationLabel }}
								</h2>
								<NcTextField
									v-model="form.name"
									:label="adminInternalNameLabel"
									:maxlength="160"
									:placeholder="adminExampleWelcomeNoticeLabel" />
								<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
								<NcTextArea
									v-model="form.description"
									:label="
										t(
											'communications',
											'Description for your team',
										)
									"
									:maxlength="700" />
							</section>
							<section class="cc-native-section">
								<h2>
									{{ adminContentLabel }}
								</h2>
								<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
								<NcTextField
									v-model="form.headline"
									:label="t('communications', 'Displayed title *')"
									:maxlength="180" />
								<div class="cc-native-rich">
									<MarkdownEditor
										v-model="form.message"
										:label="adminDisplayedMessageLabel"
										:description="contentFormattingHelp"
										:placeholder="adminWriteYourMessageLabel"
										min-height="130px"
										max-height="410px" />
								</div>
								<details
									class="cc-progressive-details"
									:open="
										Boolean(form.cta_label || form.cta_url)
										|| undefined
									">
									<summary>
										<!-- TRANSLATORS Action or instruction in the publications management screen. -->
										{{
											t(
												'communications',
												'Add an action button (optional)',
											)
										}}
									</summary>
									<div class="cc-native-pair">
										<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
										<NcTextField
											v-model="form.cta_label"
											:label="
												t('communications', 'Button text')
											"
											:maxlength="80" />
										<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
										<NcTextField
											v-model="form.cta_url"
											:label="
												t(
													'communications',
													'HTTP(S), mailto:, tel: or internal path',
												)
											"
											:maxlength="1900" />
									</div>
								</details>
								<details
									class="cc-progressive-details cc-translation-options">
									<summary>
										<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
										{{
											t(
												'communications',
												'Message translations (optional)',
											)
										}}
									</summary>
									<!-- TRANSLATORS Explains the per-language overrides in a publication translation editor. -->
									<p class="cc-native-hint">
										{{
											t(
												'communications',
												"Provide a version in the recipient's language. Empty fields use the original text.",
											)
										}}
									</p>
									<div class="cc-native-control">
										<label for="cc-translation-locale">{{
											adminTranslationLanguageLabel
										}}</label>
										<NcSelect
											v-model="translationLocale"
											:options="languageOptions"
											:reduce="selectable"
											:clearable="false"
											input-id="cc-translation-locale" />
									</div>
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									<NcTextField
										v-model="translatedHeadline"
										:label="
											t('communications', 'Translated title')
										"
										:maxlength="180" />
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									<NcTextArea
										v-model="translatedMessage"
										:label="
											t('communications', 'Translated message')
										"
										:maxlength="4000" />
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									<NcTextField
										v-model="translatedCta"
										:label="
											t('communications', 'Translated button')
										"
										:maxlength="80" />
								</details>
							</section>
							<section class="cc-native-section">
								<h2>
									{{ adminRecipientsLabel }}
								</h2>
								<div class="cc-native-control">
									<label for="cc-audience">{{
										adminSendToLabel
									}}</label>
									<NcSelect
										v-model="form.audience_type"
										:options="audienceOptions"
										:reduce="selectable"
										:clearable="false"
										input-id="cc-audience" />
								</div>
								<div
									v-if="form.audience_type === 'group'"
									class="cc-native-control">
									<NcSelectUsers
										v-model="selectedGroups"
										:options="groupChoices"
										input-id="cc-group"
										:input-label="adminRecipientGroupsLabel"
										:placeholder="
											adminSelectOneOrMoreGroupsLabel
										"
										multiple
										keep-open />
									<div class="cc-native-control">
										<label for="cc-group-match">{{
											adminGroupMatchingLabel
										}}</label>
										<NcSelect
											v-model="form.group_match"
											:options="groupMatchOptions"
											:reduce="selectable"
											:clearable="false"
											input-id="cc-group-match" />
									</div>
								</div>
								<div
									v-if="form.audience_type === 'users'"
									class="cc-native-control">
									<NcSelectUsers
										v-model="selectedUsers"
										:options="userChoices"
										input-id="cc-users"
										:input-label="adminRecipientUsersLabel"
										:placeholder="
											adminSearchByNameOrIdentifierLabel
										"
										multiple
										keep-open
										@search="searchUsers" />
									<p class="cc-native-hint">
										<!-- TRANSLATORS Action or instruction in the publications management screen. -->
										{{
											t(
												'communications',
												'Enter at least two characters. Only selected users will receive the publication.',
											)
										}}
									</p>
								</div>
								<details
									class="cc-progressive-details"
									:open="
										selectedExcludedGroups.length > 0
										|| undefined
									">
									<summary>
										<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
										{{
											t(
												'communications',
												'Exclude recipient groups (optional)',
											)
										}}
									</summary>
									<div class="cc-native-control">
										<NcSelectUsers
											v-model="selectedExcludedGroups"
											:options="groupChoices"
											input-id="cc-excluded"
											:input-label="adminExcludedGroupsLabel"
											:placeholder="
												adminTheseGroupsWillNotReceiveLabel
											"
											multiple
											keep-open />
										<label for="cc-excluded-match">{{
											adminHowToMatchExcludedGroupsLabel
										}}</label>
										<NcSelect
											v-model="form.excluded_match"
											:options="excludedMatchOptions"
											:reduce="selectable"
											:clearable="false"
											input-id="cc-excluded-match" />
										<p class="cc-native-hint">
											<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
											{{
												t(
													'communications',
													'Any group (OR) excludes members of at least one group. All groups (AND) excludes only members of every group. Exclusion takes precedence over inclusion.',
												)
											}}
										</p>
									</div>
								</details>
							</section>

							<section class="cc-native-section cc-delivery-section">
								<h2>
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									{{ t('communications', 'Delivery methods') }}
								</h2>
								<p class="cc-native-hint">
									<!-- TRANSLATORS Action or instruction in the publications management screen. -->
									{{
										t(
											'communications',
											'Select one or more destinations. Contextual banners and modal dialogs are alternatives: selecting one disables the other. Inbox, notifications, activity, email, and messaging can be freely combined.',
										)
									}}
								</p>
								<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
								<div
									class="cc-delivery-selector"
									role="group"
									:aria-label="
										t(
											'communications',
											'Publication delivery methods',
										)
									">
									<DeliveryChannelOption
										v-for="channel in visualDeliveryOptions"
										:key="channel.id"
										:channel="channel"
										@change="onDeliveryChannelChange" />
									<DeliveryChannelOption
										:channel="inboxDeliveryOption"
										@change="onDeliveryChannelChange" />
									<DeliveryChannelOption
										v-for="channel in deliveryChannelOptions"
										:key="channel.id"
										:channel="channel"
										@change="onDeliveryChannelChange" />
									<details class="cc-gateway-provider-details">
										<summary>
											<strong>{{
												adminMessagingAndSMSTwoFactorLabel
											}}</strong>
											<span>{{
												gatewayProviderCountLabel
											}}</span>
										</summary>
										<p class="cc-native-hint">
											<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
											{{
												t(
													'communications',
													'WhatsApp, SMS, Signal, Telegram and XMPP are independent destinations. Each recipient must opt in and provide their address in notification preferences. Sponsored content requires additional consent.',
												)
											}}
										</p>
										<DeliveryChannelOption
											v-for="channel in gatewayOptions"
											:key="channel.id"
											:channel="channel"
											@change="onDeliveryChannelChange" />
									</details>
								</div>
								<div
									class="cc-delivery-selection-feedback"
									role="status"
									aria-live="polite">
									<strong
										>{{ destinationCount }}
										{{
											destinationCount === 1
												? 'forma selecionada'
												: 'formas selecionadas'
										}}</strong
									>
									<span>{{ deliveryReview }}</span>
								</div>
								<NcCheckboxRadioSwitch
									v-if="inboxCopy"
									v-model="form.comments_enabled"
									type="switch"
									class="cc-delivery-comments">
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									{{ t('communications', 'Allow Inbox comments') }}
								</NcCheckboxRadioSwitch>
								<NcNoteCard
									v-if="!hasDestination"
									type="warning"
									class="cc-delivery-empty-warning">
									<!-- TRANSLATORS Action or instruction in the publications management screen. -->
									{{
										t(
											'communications',
											'Select at least one delivery method to continue.',
										)
									}}
								</NcNoteCard>
								<p v-if="eventDrivenDelivery" class="cc-native-hint">
									<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
									{{
										t(
											'communications',
											'Each event occurrence targets only the authorized recipient. Outbound channels are queued; a banner or modal appears on the next eligible visit. Frequency limits apply to all deliveries, with no mass dispatch on activation.',
										)
									}}
								</p>
								<p
									v-else-if="
										['banner', 'modal'].includes(
											form.presentation,
										)
									"
									class="cc-native-hint">
									<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
									{{
										t(
											'communications',
											'A banner or modal appears on the next eligible visit. Other selected channels are queued once per activation, respecting the start date.',
										)
									}}
								</p>
								<p v-if="noticeEmail" class="cc-native-hint">
									<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
									{{
										t(
											'communications',
											'Email requires a configured server and valid address. Delivery cannot be undone.',
										)
									}}
								</p>
								<p
									v-if="
										editorId !== null
										&& form.status === 'active'
										&& hasAdditionalNotices
									"
									class="cc-native-hint">
									<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
									{{
										t(
											'communications',
											'Changing channels does not resend earlier messages. New events follow the active configuration, consent, availability dates, and frequency limits.',
										)
									}}
								</p>
								<div
									v-if="form.presentation === 'banner'"
									class="cc-banner-type-settings">
									<div class="cc-banner-type-label">
										<span
											id="cc-banner-type-label"
											class="cc-native-label"
											>{{ adminMessageTypeLabel }}</span
										>
										<p class="cc-native-hint">
											<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
											{{
												t(
													'communications',
													'Use a built-in type or choose a neutral or custom appearance.',
												)
											}}
										</p>
									</div>
									<div
										class="cc-banner-type-options"
										role="group"
										aria-labelledby="cc-banner-type-label">
										<NcButton
											v-for="option in bannerTypes"
											:key="option.value"
											class="cc-banner-type-card"
											:data-type="option.value"
											:pressed="
												selectedBannerType === option.value
											"
											:style="
												option.value === 'custom'
													? {
															'--cc-banner-type-background':
																form.banner_color,
															'--cc-banner-type-accent':
																'color-mix(in srgb, '
																+ form.banner_color
																+ ' 45%, var(--color-main-text) 55%)',
														}
													: undefined
											"
											variant="secondary"
											@click="form.theme = option.value">
											<span class="cc-banner-type-heading">
												<NcIconSvgWrapper
													:path="option.icon"
													:size="20" />
												<span>{{ option.label }}</span>
											</span>
										</NcButton>
									</div>
									<div class="cc-banner-custom-fields">
										<div
											v-if="form.theme === 'custom'"
											class="cc-banner-color-picker">
											<span class="cc-native-label">{{
												adminBannerColorLabel
											}}</span>
											<NcColorPicker
												v-model="form.banner_color"
												:palette="colorPickerPalette"
												advanced-fields
												container="body">
												<!-- TRANSLATORS Action or instruction in the publications management screen. -->
												<NcButton
													class="cc-banner-color-trigger"
													variant="secondary"
													:aria-label="
														t(
															'communications',
															'Choose a custom banner color',
														)
													">
													<span
														class="cc-banner-color-swatch"
														:style="{
															backgroundColor:
																form.banner_color,
														}"
														aria-hidden="true"></span>
													<span
														>{{ form.banner_color }}
														<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
														{{
															t(
																'communications',
																'— Choose color',
															)
														}}</span
													>
												</NcButton>
											</NcColorPicker>
											<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
											<NcTextField
												v-model="form.banner_color"
												:label="
													t(
														'communications',
														'Hexadecimal code (#RRGGBB or #RRGGBBAA)',
													)
												"
												:maxlength="9" />
											<p class="cc-native-hint">
												<!-- TRANSLATORS Validation or failure message shown in the publications management screen. -->
												{{
													t(
														'communications',
														'The color is softened for readability in light and dark themes. Color does not change meaning: use Warning or Error for genuine alerts.',
													)
												}}
											</p>
										</div>
										<div
											class="cc-native-control cc-banner-icon-picker">
											<label for="cc-banner-icon">{{
												adminBannerIconLabel
											}}</label>
											<NcSelect
												v-model="form.banner_icon"
												:options="bannerIconOptions"
												:reduce="selectable"
												:clearable="false"
												label="label"
												input-id="cc-banner-icon">
												<template #option="option">
													<span
														class="cc-banner-icon-item">
														<NcIconSvgWrapper
															v-if="option.path"
															:path="option.path"
															:size="20"
															aria-hidden="true" />
														<span>{{
															option.label
														}}</span>
													</span>
												</template>
												<template #selected-option="option">
													<span
														class="cc-banner-icon-item">
														<NcIconSvgWrapper
															v-if="option.path"
															:path="option.path"
															:size="18"
															aria-hidden="true" />
														<span>{{
															option.label
														}}</span>
													</span>
												</template>
											</NcSelect>
											<p class="cc-native-hint">
												<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
												{{
													t(
														'communications',
														'Keep the default icon, choose another, or hide it.',
													)
												}}
											</p>
										</div>
									</div>
								</div>
								<div class="cc-native-appearance-options">
									<NcCheckboxRadioSwitch
										v-if="form.presentation === 'banner'"
										v-model="form.dismissible"
										type="switch"
										>{{
											adminAllowDismissingTheBannerLabel
										}}</NcCheckboxRadioSwitch
									>
								</div>
								<div
									v-if="
										form.presentation === 'banner'
										|| form.presentation === 'none'
									"
									class="cc-native-control">
									<label for="cc-text-align">{{
										adminTextAlignmentLabel
									}}</label>
									<NcSelect
										v-model="form.text_align"
										:options="alignments"
										:reduce="selectable"
										:clearable="false"
										input-id="cc-text-align" />
								</div>
								<div
									v-if="
										form.presentation === 'banner'
										|| form.presentation === 'none'
									"
									class="cc-banner-color-picker">
									<label>{{ adminTextColorOptionalLabel }}</label>
									<NcColorPicker
										v-model="selectedTextColor"
										clearable
										advanced-fields
										container="body">
										<!-- TRANSLATORS Action or instruction in the publications management screen. -->
										<NcButton
											variant="secondary"
											:text="
												t(
													'communications',
													'Choose text color',
												)
											">
											<template #icon
												><NcIconSvgWrapper
													:path="mdiPaletteOutline"
													:size="18"
											/></template>
										</NcButton>
									</NcColorPicker>
									<p class="cc-native-hint">
										<!-- TRANSLATORS Validation or failure message shown in the publications management screen. -->
										{{
											t(
												'communications',
												'The chosen color is blended with the theme text color to maintain contrast.',
											)
										}}
									</p>
								</div>
							</section>
						</div>

						<div v-else class="cc-wizard-step-content">
							<div class="cc-wizard-step-title">
								<h2>
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									{{ t('communications', 'Review and publish') }}
								</h2>
								<p>
									<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
									{{
										t(
											'communications',
											'Review delivery options before activating the publication.',
										)
									}}
								</p>
							</div>
							<section class="cc-native-section cc-review-delivery">
								<h2>
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									{{ t('communications', 'Configured delivery') }}
								</h2>
								<p class="cc-review-delivery-line">
									{{ deliveryReview }}
								</p>
								<p
									v-if="hasAdditionalNotices"
									class="cc-native-hint">
									<!-- TRANSLATORS Action or instruction in the publications management screen. -->
									{{
										t(
											'communications',
											'Selected channels share the same message and audience. Email and messaging are queued; banners and modals appear during navigation. Links respect permissions even without an Inbox item.',
										)
									}}
								</p>
								<NcNoteCard
									v-if="
										(noticeEmail || form.gateway_channels.length)
										&& form.status === 'active'
									"
									type="warning">
									<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
									{{
										t(
											'communications',
											'Activation may send real external messages. Only recipients who opted into each gateway will be included; verify the audience and date range.',
										)
									}}
								</NcNoteCard>
							</section>
							<section class="cc-native-section">
								<h2>
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									{{
										t(
											'communications',
											'Publication and frequency',
										)
									}}
								</h2>
								<div class="cc-native-pair">
									<div class="cc-native-control cc-format-summary">
										<span class="cc-native-label">{{
											adminVisualDestinationLabel
										}}</span>
										<strong>{{
											form.presentation === 'modal'
												? adminModalDialogLabel
												: form.presentation === 'banner'
													? adminContextualBannerLabel
													: adminNoContextualDisplayLabel
										}}</strong>
									</div>
									<div
										class="cc-native-control cc-native-priority">
										<label for="cc-priority">{{
											adminPriority0100Label
										}}</label>
										<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
										<NcTextField
											id="cc-priority"
											v-model="form.priority"
											:label="
												t(
													'communications',
													'Priority (0–100)',
												)
											"
											type="number"
											label-outside />
									</div>
								</div>
								<div class="cc-native-pair">
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									<NcTextField
										v-model="form.manual_order"
										:label="
											t(
												'communications',
												'Manual order (lower numbers first)',
											)
										"
										type="number" />
								</div>
								<div class="cc-native-pair">
									<div
										v-if="
											form.trigger_key
												!== 'nextcloud.page.opened'
											&& ['banner', 'modal'].includes(
												form.presentation,
											)
											&& form.app_target_mode === 'area'
										"
										class="cc-native-control">
										<label for="cc-placement">{{
											adminWhereToDisplayLabel
										}}</label>
										<NcSelect
											v-model="form.placement"
											:options="placementOptions"
											:reduce="selectable"
											:clearable="false"
											input-id="cc-placement" />
									</div>
									<div
										v-if="
											['banner', 'modal'].includes(
												form.presentation,
											)
										"
										class="cc-native-control">
										<label for="cc-frequency">{{
											adminFrequencyLimitLabel
										}}</label>
										<NcSelect
											v-model="form.frequency"
											:options="frequencyOptions"
											:reduce="selectable"
											:clearable="false"
											input-id="cc-frequency" />
									</div>
								</div>
								<div
									v-if="
										['banner', 'modal'].includes(
											form.presentation,
										)
										&& form.trigger_key
											!== 'nextcloud.page.opened'
									"
									class="cc-native-control cc-app-targeting">
									<label for="cc-app-target-mode">{{
										adminWhereToDisplayThePublicationLabel
									}}</label>
									<NcSelect
										v-model="form.app_target_mode"
										:options="targetModeOptions"
										:reduce="selectable"
										:clearable="false"
										input-id="cc-app-target-mode" />
									<div
										v-if="
											form.app_target_mode === 'only'
											|| form.app_target_mode === 'exclude'
										"
										class="cc-native-control">
										<label for="cc-app-target-apps">{{
											adminIncludedOrExcludedApplicationsLabel
										}}</label>
										<!-- TRANSLATORS Action or instruction in the publications management screen. -->
										<NcSelect
											v-model="form.app_target_apps"
											:options="targetAppOptions"
											:reduce="selectable"
											multiple
											:clearable="true"
											input-id="cc-app-target-apps"
											:placeholder="
												t(
													'communications',
													'Select at least one application',
												)
											" />
										<p class="cc-native-hint">
											<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
											{{
												t(
													'communications',
													'Rules are enforced server-side. Unknown pages do not receive publications filtered by application.',
												)
											}}
										</p>
									</div>
								</div>
								<div
									v-if="
										form.presentation === 'banner'
										&& form.trigger_key
											=== 'nextcloud.time.window'
									"
									class="cc-native-control cc-guest-target">
									<NcCheckboxRadioSwitch
										v-model="form.guest_visible"
										type="switch"
										:disabled="
											inboxCopy
											|| hasAdditionalNotices
											|| form.audience_type !== 'all'
											|| form.category !== 'institutional'
											|| form.placement !== 'any'
											|| !['area', 'all'].includes(
												form.app_target_mode,
											)
										">
										<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
										{{
											t(
												'communications',
												'Also show to signed-out visitors (public pages)',
											)
										}}
									</NcCheckboxRadioSwitch>
									<p class="cc-native-hint">
										<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
										{{
											t(
												'communications',
												'Content will be public, including on the login page and public links. This is only for institution-wide notices scheduled by date without application restrictions. It cannot be combined with Inbox or personal notifications. Disabled by default to protect existing publications.',
											)
										}}
									</p>
								</div>
								<div
									v-if="
										['banner', 'modal'].includes(
											form.presentation,
										)
									"
									class="cc-native-pair">
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									<NcTextField
										v-if="form.frequency === 'custom'"
										v-model="form.min_interval_minutes"
										:label="
											t(
												'communications',
												'Minimum interval in minutes',
											)
										"
										type="number" />
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									<NcTextField
										v-model="form.max_total"
										:label="
											t(
												'communications',
												'Maximum per user (0 = unlimited)',
											)
										"
										type="number" />
									<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
									<NcTextField
										v-model="form.max_per_day"
										:label="
											t(
												'communications',
												'Maximum in 24 hours (0 = unlimited)',
											)
										"
										type="number" />
								</div>
								<div class="cc-native-pair">
									<div class="cc-native-control">
										<label for="cc-category">{{
											adminCategoryLabel
										}}</label>
										<NcSelect
											v-model="form.category"
											:options="categoryOptions"
											:reduce="selectable"
											:clearable="false"
											input-id="cc-category" />
									</div>
									<div
										v-if="form.presentation === 'modal'"
										class="cc-native-control">
										<label for="cc-theme">{{
											adminVisualThemeLabel
										}}</label>
										<NcSelect
											v-model="form.theme"
											:options="themeOptions"
											:reduce="selectable"
											:clearable="false"
											input-id="cc-theme" />
									</div>
								</div>
								<p class="cc-native-hint">
									<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
									{{
										t(
											'communications',
											'Sponsored publications are clearly marked and can be hidden by users. No external tracking is used.',
										)
									}}
								</p>
								<div
									v-if="
										form.trigger_key !== 'nextcloud.time.window'
									"
									class="cc-native-pair">
									<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
									<NcDateTimePickerNative
										v-model="form.starts_at"
										:label="
											t('communications', 'Start (optional)')
										"
										type="datetime-local"
										clearable />
									<!-- TRANSLATORS Optional end date of publication availability. -->
									<NcDateTimePickerNative
										v-model="form.ends_at"
										:label="
											t('communications', 'End (optional)')
										"
										type="datetime-local"
										clearable />
								</div>
								<div class="cc-native-control">
									<label for="cc-publish">{{
										adminPublicationStatusLabel
									}}</label>
									<NcSelect
										v-model="form.status"
										:options="publishOptions"
										:reduce="selectable"
										:clearable="false"
										input-id="cc-publish" />
								</div>
							</section>
							<NcNoteCard type="info" class="cc-wizard-review-note">
								{{
									form.status === 'active'
										? adminThePublicationWillBecomeActiveHint
										: adminWhileDraftOrPausedThisHint
								}}
							</NcNoteCard>
						</div>

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
									:text="
										saving
											? adminSavingLabel
											: adminSavePublicationLabel
									"
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
		</div>
	</div>

	<CampaignPresentation
		v-if="
			previewOpen
			&& ['banner', 'modal'].includes((previewCampaign || form).presentation)
		"
		:campaign="previewCampaign || form"
		:open="previewOpen"
		preview
		@close="closePreview" />

	<CampaignAdminDialogs
		:form="form"
		:preview-campaign="previewCampaign"
		:metrics-campaign="metricsCampaign"
		:metrics-loading="metricsLoading"
		:metrics-data="metricsData"
		:delete-target="deleteTarget"
		:deleting="deleting"
		:archive-confirmation-message="archiveConfirmationMessage"
		v-model:preview-open="previewOpen"
		v-model:metrics-open="metricsOpen"
		v-model:confirm-delete-open="confirmDeleteOpen"
		v-model:confirm-archive-open="confirmArchiveOpen"
		v-model:delete-name="deleteName"
		@close-preview="closePreview"
		@delete-permanently="deletePermanently"
		@archive="archive" />
</template>

<style lang="scss" scoped>
@use './styles/campaign-admin.scss';
</style>
