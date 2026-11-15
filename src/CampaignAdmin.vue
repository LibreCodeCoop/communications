<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t, getLanguage } from '@nextcloud/l10n'
import { requestApi } from './services/api'
import {
	campaignRecordToForm,
	serializeCampaignForm,
	validateCampaignPayload,
} from './services/campaignForm'
import type { CampaignSavePayload } from './services/campaignForm'

import { computed, onMounted, ref, watch } from 'vue'
import type {
	CampaignRecord,
	CampaignForm,
	CampaignCatalog,
	CampaignApiPayload,
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
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import MarkdownEditor from './components/MarkdownEditor.vue'
import CampaignPresentation from './components/CampaignPresentation.vue'
import AutomationBuilder from './components/AutomationBuilder.vue'
import DeliveryChannelOption from './components/DeliveryChannelOption.vue'
import {
	bannerAppearance,
	BANNER_COLOR_PALETTE,
	BANNER_ICON_OPTIONS,
	DEFAULT_BANNER_COLOR,
} from './bannerAppearance.ts'
import {
	mdiEyeOutline,
	mdiContentCopy,
	mdiArchiveOutline,
	mdiPlayCircleOutline,
	mdiPauseCircleOutline,
	mdiBackupRestore,
	mdiChartBar,
	mdiBullhornOutline,
	mdiAccountGroupOutline,
	mdiMapMarkerOutline,
	mdiRepeat,
	mdiCalendarRange,
	mdiLightningBoltOutline,
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
	mdiArrowUp,
	mdiArrowDown,
} from '@mdi/js'

const statuses = [
	{ label: t('communications', 'All statuses'), value: 'all' },
	{ label: t('communications', 'Active'), value: 'active' },
	{ label: t('communications', 'Scheduled'), value: 'scheduled' },
	{ label: t('communications', 'Ended'), value: 'ended' },
	{ label: t('communications', 'Drafts'), value: 'draft' },
	{ label: t('communications', 'Paused'), value: 'paused' },
	{ label: t('communications', 'Archived'), value: 'archived' },
]
const audienceOptions = [
	{ label: t('communications', 'All signed-in users'), value: 'all' },
	{ label: t('communications', 'Administrators only'), value: 'admins' },
	{ label: t('communications', 'One or more Nextcloud groups'), value: 'group' },
	{ label: t('communications', 'Specific users'), value: 'users' },
]
const publishOptions = [
	{ label: t('communications', 'Draft (do not publish or send)'), value: 'draft' },
	{
		label: t('communications', 'Active (publish and send when eligible)'),
		value: 'active',
	},
	{
		label: t('communications', 'Paused (do not display or send)'),
		value: 'paused',
	},
]
const placementOptions = computed(() =>
	(
		catalog.value.automation?.placements || [
			{ label: t('communications', 'Any supported page'), value: 'any' },
			{ label: 'Files', value: 'files' },
			{ label: 'Dashboard', value: 'dashboard' },
			{ label: t('communications', 'Personal settings'), value: 'settings' },
			{
				label: t('communications', 'Administration settings'),
				value: 'admin_settings',
			},
		]
	).map((p) => ({ label: p.label, value: p.value })),
)
const targetModeOptions = [
	{
		value: 'legacy',
		label: t('communications', 'A defined area (existing mode)'),
	},
	{ value: 'all', label: t('communications', 'All supported applications') },
	{ value: 'only', label: t('communications', 'Only selected applications') },
	{
		value: 'exclude',
		label: t('communications', 'All except selected applications'),
	},
]
const targetAppOptions = computed(() =>
	placementOptions.value.filter((p) => p.value !== 'any'),
)
const frequencyOptions = [
	{ label: t('communications', 'On each occurrence'), value: 'event' },
	{ label: t('communications', 'Once per publication and user'), value: 'once' },
	{ label: t('communications', 'At most once every 24 hours'), value: 'daily' },
	{ label: t('communications', 'At most once every 7 days'), value: 'weekly' },
	{ label: t('communications', 'Custom interval'), value: 'custom' },
]
const excludedMatchOptions = [
	{
		label: t('communications', 'Exclude members of any group (OR)'),
		value: 'any',
	},
	{
		label: t('communications', 'Exclude only members of every group (AND)'),
		value: 'all',
	},
]
const groupMatchOptions = [
	{ label: t('communications', 'Any selected group (OR)'), value: 'any' },
	{ label: t('communications', 'All selected groups (AND)'), value: 'all' },
]
const categoryOptions = [
	{
		label: t('communications', 'Institutional announcement'),
		value: 'institutional',
	},
	{ label: t('communications', 'Sponsored content'), value: 'sponsored' },
]
const themeOptions = [
	{ label: t('communications', 'Nextcloud default'), value: 'default' },
	{ label: t('communications', 'Institutional highlight'), value: 'primary' },
	{ label: t('communications', 'Information'), value: 'info' },
	{ label: t('communications', 'Success'), value: 'success' },
	{ label: t('communications', 'Warning'), value: 'warning' },
	{ label: t('communications', 'Error'), value: 'error' },
	{ label: t('communications', 'Neutral'), value: 'neutral' },
	{ label: t('communications', 'Custom'), value: 'custom' },
]
// Match the native NcNoteCard variants and icons used by Nextcloud.
const bannerTypes = [
	{
		value: 'info',
		label: t('communications', 'Information'),
		icon: mdiInformation,
	},
	{
		value: 'success',
		label: t('communications', 'Success'),
		icon: mdiCheckboxMarkedCircle,
	},
	{ value: 'warning', label: t('communications', 'Warning'), icon: mdiAlert },
	{ value: 'error', label: t('communications', 'Error'), icon: mdiAlertDecagram },
	{
		value: 'neutral',
		label: t('communications', 'Neutral'),
		icon: mdiMinusCircleOutline,
	},
	{
		value: 'custom',
		label: t('communications', 'Custom'),
		icon: mdiPaletteOutline,
	},
]
const selectable = (option: { value: string }): string => option.value
const campaigns = ref<CampaignRecord[]>([])
const catalog = ref<CampaignCatalog>({
	triggers: [],
	groups: [],
	automation: { intents: [], sources: [], placements: [], applications: [] },
	deliveryChannels: [],
})
const loading = ref(true)
const saving = ref(false)
const view = ref('list')
watch(view, (value) => emit('editor-view', value), { immediate: true })
const search = ref('')
const filterStatus = ref('all')
const editorId = ref<number | null>(null)
const previewOpen = ref(false)
const wizardStep = ref(1)
const wizardSteps = [
	{
		number: 1,
		label: t('communications', 'When to publish'),
		shortLabel: t('communications', 'Timing'),
		icon: mdiApps,
	},
	{
		number: 2,
		label: t('communications', 'Message and audience'),
		shortLabel: t('communications', 'Message'),
		icon: mdiMessageTextOutline,
	},
	{
		number: 3,
		label: t('communications', 'Review and publish'),
		shortLabel: t('communications', 'Publish'),
		icon: mdiCheckCircleOutline,
	},
]
const nextStepLabel = computed(() =>
	wizardStep.value === 1
		? t('communications', 'Continue: Message and audience')
		: t('communications', 'Continue: Review and publish'),
)

function checkWizardStep(step: number): boolean {
	errorMessage.value = ''
	if (step === 1 && !automationReady.value) {
		errorMessage.value = t(
			'communications',
			'Choose when to deliver the publication.',
		)
		return false
	}
	if (
		step === 1
		&& form.value.trigger_key === 'nextcloud.time.window'
		&& (!(form.value.starts_at instanceof Date)
			|| Number.isNaN(form.value.starts_at.getTime()))
	) {
		errorMessage.value = t('communications', 'Enter the scheduled start date.')
		return false
	}
	if (step === 2) {
		if (
			!form.value.name.trim()
			|| !form.value.headline.trim()
			|| !form.value.message.trim()
		) {
			errorMessage.value = t(
				'communications',
				'Enter the internal name, title, and message before continuing.',
			)
			return false
		}
		if (
			form.value.audience_type === 'group'
			&& selectedGroups.value.length === 0
		) {
			errorMessage.value = t(
				'communications',
				'Select at least one recipient group.',
			)
			return false
		}
		if (
			form.value.audience_type === 'users'
			&& selectedUsers.value.length === 0
		) {
			errorMessage.value = t(
				'communications',
				'Select at least one recipient user.',
			)
			return false
		}
		if (
			Boolean(form.value.cta_label.trim())
			!== Boolean(form.value.cta_url.trim())
		) {
			errorMessage.value = t(
				'communications',
				'To display a button, enter its text and URL.',
			)
			return false
		}
		if (!hasDestination.value) {
			errorMessage.value = t(
				'communications',
				'Select at least one delivery method.',
			)
			return false
		}
	}
	return true
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
	// A scheduled draft must not silently keep a future date when changed into
	// an event-driven campaign, which would otherwise never appear in tests.
	if (to === 'board' && from === null) {
		// No channel is preselected for a new immediate publication.
		form.value.presentation = 'none'
	}

	if (to !== 'schedule') form.value.guest_visible = false
	if (from === 'schedule' && to !== 'schedule') {
		form.value.starts_at = null
		form.value.ends_at = null
	}
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
const rowMenuOpen = ref<Record<number, boolean>>({})
const draggingCampaignId = ref<number | null>(null)
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
const userSearchOptions = ref<UserSelection[]>([])
const page = ref(1)
const PAGE_SIZE = 10
let userSearchGeneration = 0
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
		label: t('communications', 'Nextcloud Notifications'),
		description: t(
			'communications',
			'Notification in the bell menu. Requires the Notifications app.',
		),
	},
	{
		id: 'activity',
		field: 'notify_activity',
		label: t('communications', 'Activity feed'),
		description: t(
			'communications',
			'Records the publication in the Activity feed without showing a banner or dialog.',
		),
	},
	{
		id: 'email',
		field: 'notify_email',
		label: 'E-mail',
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
		description: t(
			'communications',
			'International phone number, e.g. +5521999999999.',
		),
	},
	{
		id: 'gateway:signal',
		label: 'Signal',
		description: t('communications', 'Signal number, e.g. +5521999999999.'),
	},
	{
		id: 'gateway:telegram',
		label: 'Telegram',
		description: t('communications', 'Private bot chat identifier.'),
	},
	{
		id: 'gateway:gowhatsapp',
		label: 'WhatsApp',
		description: t('communications', 'WhatsApp number in international format.'),
	},
	{
		id: 'gateway:whatsappbusiness',
		label: 'WhatsApp Business',
		description: t(
			'communications',
			"Subject to the provider's policies and approved templates.",
		),
	},
	{
		id: 'gateway:xmpp',
		label: 'XMPP',
		description: t('communications', 'Recipient JID address.'),
	},
]
const gatewayOptions = computed(() =>
	gatewayDefinitions.map((definition) => {
		const meta = (catalog.value.deliveryChannels || []).find(
			(item) => item.id === definition.id,
		)
		const gatewayId = definition.id.slice(8)
		const selected = (form.value.gateway_channels || []).includes(gatewayId)
		const ready = meta?.ready === true
		const selectable =
			(!form.value.guest_visible && ready)
			|| (!form.value.guest_visible && selected)
		const availability = form.value.guest_visible
			? 'guest'
			: meta?.availability || 'loading'
		const status = form.value.guest_visible
			? t('communications', 'No recipients')
			: selected && !ready
				? t('communications', 'Selected · unavailable')
				: meta?.status || t('communications', 'Checking…')
		const help = form.value.guest_visible
			? t(
					'communications',
					'Anonymous public banners cannot send individual messages.',
				)
			: meta?.help || t('communications', 'Checking provider availability.')
		return {
			...definition,
			gatewayId,
			selected,
			ready,
			selectable,
			availability,
			status,
			help,
		}
	}),
)
const gatewayGroup = computed(() =>
	(catalog.value.deliveryChannels || []).find(
		(item) => item.id === 'twofactor_gateway',
	),
)
const gatewayReadyCount = computed(
	() => gatewayOptions.value.filter((x) => x.ready).length,
)

const deliveryChannelOptions = computed(() =>
	deliveryChannelDefinitions.map((definition) => {
		const meta = (catalog.value.deliveryChannels || []).find(
			(item) => item.id === definition.id,
		)
		const selected = definition.field !== null && !!form.value[definition.field]
		const ready = meta?.ready === true && definition.field !== null
		const usable = ready && !form.value.guest_visible
		const selectable = usable || (selected && !form.value.guest_visible)
		const availability = form.value.guest_visible
			? 'guest'
			: meta?.availability || 'loading'
		let status = form.value.guest_visible
			? t('communications', 'No individual delivery')
			: meta?.status || t('communications', 'Checking…')
		if (selected && !ready && !form.value.guest_visible)
			status = t('communications', 'Selected · unavailable')
		const help = form.value.guest_visible
			? t(
					'communications',
					'Anonymous public banners do not send individual messages. Disable guest mode to configure recipients and channels.',
				)
			: meta?.help
				|| t(
					'communications',
					'Availability of this channel is still being checked.',
				)
		return {
			...definition,
			selected,
			ready,
			selectable,
			availability,
			status,
			help,
		}
	}),
)
function setNoticeChannel(field: ChannelNoticeField, enabled: boolean) {
	if (form.value.guest_visible) return
	const channel = deliveryChannelDefinitions.find((item) => item.field === field)
	if (!channel) return
	const ready = (catalog.value.deliveryChannels || []).some(
		(item) => item.id === channel.id && item.ready === true,
	)
	// A previously selected channel must remain removable when its app disappears.
	if (enabled && !ready) return
	form.value[field] = !!enabled
}
function onDeliveryChannelChange({ id, enabled }: { id: string; enabled: boolean }) {
	if (id === 'banner' || id === 'modal') {
		if (enabled) {
			// First visual destination on an immediate campaign defaults to a
			// single impression per user, not daily surprise modals/banners.
			if (
				form.value.trigger_key === 'nextcloud.board.published'
				&& form.value.presentation === 'none'
				&& form.value.frequency === 'event'
			) {
				form.value.frequency = 'once'
			}
			form.value.presentation = id
		} else if (form.value.presentation === id) {
			form.value.presentation = 'none'
		}
		if (id !== 'banner' || !enabled) form.value.guest_visible = false
		return
	}
	if (id === 'inbox') {
		inboxCopy.value = enabled
		return
	}
	if (id.startsWith('gateway:')) {
		const gateway = id.slice(8)
		if (!gatewayDefinitions.some((item) => item.id === id)) return
		const selected = [...(form.value.gateway_channels || [])]
		const ready = (catalog.value.deliveryChannels || []).some(
			(item) => item.id === id && item.ready === true,
		)
		if (enabled && (form.value.guest_visible || !ready)) return
		form.value.gateway_channels = enabled
			? [...new Set([...selected, gateway])]
			: selected.filter((item) => item !== gateway)
		return
	}
	const channel = deliveryChannelDefinitions.find((item) => item.id === id)
	if (channel?.field) setNoticeChannel(channel.field, enabled)
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
		label: t('communications', 'Contextual banner'),
		description: t(
			'communications',
			'Non-intrusive notice on the page during the next eligible visit or event.',
		),
		selected: form.value.presentation === 'banner',
		selectable: true,
		availability: 'ready',
		status:
			form.value.presentation === 'banner'
				? t('communications', 'Selected')
				: t('communications', 'Available'),
		help: t(
			'communications',
			'The banner appears during navigation. Selecting it disables the modal but keeps other channels.',
		),
	},
	{
		id: 'modal',
		label: t('communications', 'Modal dialog'),
		description: t(
			'communications',
			'Dialog that requires interaction on the next eligible visit or event.',
		),
		selected: form.value.presentation === 'modal',
		selectable: !form.value.guest_visible,
		availability: form.value.guest_visible ? 'guest' : 'ready',
		status: form.value.guest_visible
			? t('communications', 'Not available to guests')
			: form.value.presentation === 'modal'
				? t('communications', 'Selected')
				: t('communications', 'Available'),
		help: t(
			'communications',
			'Modal and banner are alternatives. Selecting one disables the other; email, history, and notifications remain.',
		),
	},
])
const inboxDeliveryOption = computed(() => ({
	id: 'inbox',
	label: t('communications', 'Inbox'),
	description: t(
		'communications',
		'Keeps the publication available in Nextcloud, with optional comments.',
	),
	selected: inboxCopy.value,
	selectable: !form.value.guest_visible,
	availability: form.value.guest_visible ? 'guest' : 'ready',
	status: form.value.guest_visible
		? t('communications', 'Not available to guests')
		: inboxCopy.value
			? t('communications', 'Selected')
			: t('communications', 'Available'),
	help: t(
		'communications',
		'Inbox is an independent destination. If disabled, messages sent through other channels open only their authorized details.',
	),
}))

const deliveryReview = computed(() => {
	const surfaces = []
	if (form.value.presentation === 'banner')
		surfaces.push(t('communications', 'Contextual banner'))
	if (form.value.presentation === 'modal')
		surfaces.push(t('communications', 'Modal dialog'))
	if (inboxCopy.value) surfaces.push(t('communications', 'Inbox'))

	if (form.value.notify_users)
		surfaces.push(t('communications', 'Nextcloud Notifications'))
	if (form.value.notify_activity)
		surfaces.push(t('communications', 'Activity feed'))
	if (form.value.notify_email) surfaces.push('E-mail')
	for (const id of form.value.gateway_channels || []) {
		const gateway = gatewayDefinitions.find(
			(item) => item.id === 'gateway:' + id,
		)
		if (gateway) surfaces.push(gateway.label + ' (Two-Factor Gateway)')
	}
	return surfaces.join(' · ') || t('communications', 'No destination selected')
})
// Legacy default/primary become informational banners without migrating data.
const selectedBannerType = computed(() =>
	bannerTypes.some((item) => item.value === form.value.theme)
		? form.value.theme
		: 'info',
)
const bannerPreviewAppearance = computed(() => bannerAppearance(form.value))
const bannerIconOptions = BANNER_ICON_OPTIONS.map(({ value, label }) => ({
	value,
	label,
}))
const colorPickerPalette = BANNER_COLOR_PALETTE.map(({ color }) => color)
const languageOptions = [
	{ value: 'pt_BR', label: t('communications', 'Portuguese (Brazil)') },
	{ value: 'en_US', label: t('communications', 'English (United States)') },
	{ value: 'es_ES', label: t('communications', 'Spanish') },
	{ value: 'fr_FR', label: t('communications', 'French') },
	{ value: 'de_DE', label: t('communications', 'German') },
	{ value: 'it_IT', label: t('communications', 'Italian') },
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
	{ value: 'left', label: t('communications', 'Left') },
	{ value: 'center', label: t('communications', 'Center') },
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
		audience_group: '',
		audience_groups: [],
		audience_users: [],
		presentation: 'banner',
		status: 'draft',
		priority: 10,
		placement: 'any',
		app_target_mode: 'legacy',
		app_target_apps: [],
		frequency: 'event',
		min_interval_minutes: 0,
		max_total: 0,
		max_per_day: 0,
		category: 'institutional',
		theme: 'default',
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

async function api<T extends object = CampaignApiPayload>(
	path: string,
	{
		method = 'GET',
		body,
	}: { method?: 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH'; body?: unknown } = {},
): Promise<T> {
	return requestApi<T>(path, method, body)
}

/**
 * Instant cross-tab refresh without broadcasting campaign content or targeting.
 * The lightweight page adapter re-checks eligibility with the server.
 */
function announceChanges() {
	try {
		if (typeof BroadcastChannel !== 'undefined') {
			const channel = new BroadcastChannel('communications')
			channel.postMessage({ event: 'changed' })
			channel.close()
		}
		localStorage.setItem('communications-campaign-update', String(Date.now()))
	} catch (_) {
		// Storage denied by the browser; normal 30-second fallback remains.
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
function effectiveStatus(c: CampaignRecord): string {
	if (c.status !== 'active') return c.status
	const now = Math.floor(Date.now() / 1000)
	if (c.starts_at !== null && c.starts_at > now) return 'scheduled'
	if (c.ends_at !== null && c.ends_at <= now) return 'ended'
	return 'active'
}
const labels: Record<string, string> = {
	active: t('communications', 'Active'),
	draft: t('communications', 'Draft'),
	paused: t('communications', 'Paused'),
	archived: t('communications', 'Archived'),
	scheduled: t('communications', 'Scheduled'),
	ended: t('communications', 'Ended'),
}
const currentCampaigns = computed(() =>
	campaigns.value.filter((c) => c.status !== 'archived'),
)
const stats = computed(() => [
	{
		label: t('communications', 'Registered publications'),
		count: currentCampaigns.value.length,
	},
	{
		label: t('communications', 'Active now'),
		count: currentCampaigns.value.filter((c) => effectiveStatus(c) === 'active')
			.length,
	},
	{
		label: t('communications', 'Scheduled'),
		count: currentCampaigns.value.filter(
			(c) => effectiveStatus(c) === 'scheduled',
		).length,
	},
	{
		label: t('communications', 'Drafts'),
		count: currentCampaigns.value.filter((c) => effectiveStatus(c) === 'draft')
			.length,
	},
	{
		label: t('communications', 'Paused'),
		count: currentCampaigns.value.filter((c) => effectiveStatus(c) === 'paused')
			.length,
	},
])
const visible = computed(() =>
	campaigns.value
		.filter((c) => {
			const q = search.value.trim().toLocaleLowerCase()
			if (filterStatus.value === 'all' && c.status === 'archived') return false
			if (
				filterStatus.value !== 'all'
				&& effectiveStatus(c) !== filterStatus.value
			)
				return false
			return (
				!q
				|| [c.name, c.headline, c.description, c.trigger_key]
					.join(' ')
					.toLocaleLowerCase()
					.includes(q)
			)
		})
		.sort(
			(a, b) =>
				(a.manual_order || 0) - (b.manual_order || 0)
				|| b.priority - a.priority
				|| b.id - a.id,
		),
)
const pageCount = computed(() =>
	Math.max(1, Math.ceil(visible.value.length / PAGE_SIZE)),
)
const currentPage = computed(() => Math.min(page.value, pageCount.value))
const pageVisible = computed(() =>
	visible.value.slice(
		(currentPage.value - 1) * PAGE_SIZE,
		currentPage.value * PAGE_SIZE,
	),
)
watch([search, filterStatus], () => {
	page.value = 1
})
const triggerOptions = computed(() =>
	(catalog.value.triggers || []).map((t) => ({ label: t.label, value: t.key })),
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
async function searchUsers(search: string) {
	const generation = ++userSearchGeneration
	if (search.trim().length < 2) {
		userSearchOptions.value = []
		return
	}
	try {
		const payload = await api<{ users?: Array<{ id: string; name: string }> }>(
			'/api/users?search=' + encodeURIComponent(search),
		)
		if (generation === userSearchGeneration) {
			userSearchOptions.value = (payload.users || []).map((user) => ({
				id: user.id,
				displayName: user.name,
				user: user.id,
			}))
		}
	} catch (e) {
		flash(errorMessageFor(e), 'error')
	}
}
function openPreview(campaign: CampaignRecord | null = null): void {
	rowMenuOpen.value = {}
	previewCampaign.value = campaign
	previewOpen.value = true
}
function closePreview() {
	previewOpen.value = false
	previewCampaign.value = null
}

function dateDisplay(ts: number | null): string {
	return ts === null
		? ''
		: new Date(ts * 1000).toLocaleDateString(getLanguage().replace('_', '-'))
}
function period(c: CampaignRecord): string {
	if (!c.starts_at && !c.ends_at) return t('communications', 'No deadline')
	if (c.starts_at && c.ends_at)
		return dateDisplay(c.starts_at) + ' – ' + dateDisplay(c.ends_at)
	return c.starts_at
		? t('communications', 'Since ') + dateDisplay(c.starts_at)
		: t('communications', 'Until ') + dateDisplay(c.ends_at)
}
function groupLabel(c: CampaignRecord): string {
	if (c.audience_type === 'group') {
		const ids = c.audience_groups?.length
			? c.audience_groups
			: c.audience_group
				? [c.audience_group]
				: []
		return (
			ids
				.map(
					(id) =>
						groupOptions.value.find((g) => g.value === id)?.label || id,
				)
				.join(', ') || t('communications', 'No group')
		)
	}
	if (c.audience_type === 'users') {
		const users = c.audience_users || []
		return users.length === 1
			? t('communications', 'User: ') + users[0]
			: users.length + t('communications', ' specific users')
	}
	return c.audience_type === 'admins'
		? t('communications', 'Administrators only')
		: t('communications', 'All users')
}
function triggerLabel(c: CampaignRecord): string {
	return (
		triggerOptions.value.find((t) => t.value === c.trigger_key)?.label
		|| c.trigger_key
	)
}
const summaryAction = computed(
	() =>
		triggerOptions.value.find((t) => t.value === form.value.trigger_key)?.label
		|| t('communications', 'Select an action'),
)
const sourceForReview = computed(() =>
	catalog.value.automation?.sources?.find((source) =>
		source.actions?.some((action) => action.key === form.value.trigger_key),
	),
)
const summaryWhen = computed(() => {
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
						prefix: t('communications', 'starts with'),
						digits: t('communications', 'numeric'),
					}[rule.operator] || rule.operator)
					+ ' '
					+ (rule.value || ''),
			)
			.join('; ')
		return (
			t('communications', 'Open ')
			+ place
			+ (form.value.page_route
				? ' · ' + form.value.page_route
				: t('communications', ' · any page'))
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
			|| t('communications', 'No user selected')
		)
	if (form.value.audience_type === 'group') {
		const ids = selectedGroups.value.map((g) => g.displayName || g.id)
		return ids.length
			? ids.join(
					form.value.group_match === 'all'
						? t('communications', ' AND ')
						: t('communications', ' OR '),
				)
			: t('communications', 'No group selected')
	}
	return t('communications', 'All signed-in users')
})
const locationReview = computed(() =>
	form.value.presentation === 'none'
		? inboxCopy.value
			? t('communications', 'Inbox only; no banner or dialog')
			: t('communications', 'No on-screen display')
		: form.value.presentation === 'board'
			? t('communications', 'Inbox')
			: placementOptions.value.find((x) => x.value === form.value.placement)
					?.label || form.value.placement,
)
const frequencyReview = computed(() => {
	if (eventDrivenDelivery.value) {
		return t(
			'communications',
			'On each occurrence, respecting frequency and deduplication limits',
		)
	}
	if (!['banner', 'modal'].includes(form.value.presentation)) {
		return t('communications', 'Outbound channels: one batch per activation')
	}
	const freq =
		frequencyOptions.find((x) => x.value === form.value.frequency)?.label
		|| form.value.frequency
	const caps = [
		form.value.max_total > 0
			? t('communications', 'maximum ')
				+ form.value.max_total
				+ t('communications', ' per user')
			: '',
		form.value.max_per_day > 0
			? t('communications', '{count} in 24 hours', {
					count: form.value.max_per_day,
				})
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
		? t('communications', 'From ')
				+ (from || t('communications', 'now'))
				+ t('communications', ' until ')
				+ (until || t('communications', 'no end date'))
		: t('communications', 'No date limit')
})

async function saveEditorGroups() {
	if (editorConfigLoading.value || !catalog.value.canConfigureEditors) return
	editorConfigLoading.value = true
	try {
		const data = await api<{ groups: string[] }>('/api/editor-groups', {
			method: 'POST',
			body: { groups: editorGroupValues.value },
		})
		editorGroupValues.value = data.groups
		flash(t('communications', 'Editorial groups updated.'), 'success')
	} catch (e) {
		flash(errorMessageFor(e), 'error')
	} finally {
		editorConfigLoading.value = false
	}
}
async function refresh() {
	const data = await api<{ campaigns: CampaignRecord[] }>('/api/campaigns')
	campaigns.value = data.campaigns || []
}
async function updateStatus(c: CampaignRecord, status: string) {
	try {
		await api('/api/campaigns/' + c.id + '/status', {
			method: 'POST',
			body: { status },
		})
		await refresh()
		announceChanges()
		flash(
			status === 'active'
				? t('communications', 'Publication activated.')
				: t('communications', 'Status updated.'),
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
		const result = await api<{ metrics: CampaignMetrics }>(
			'/api/campaigns/' + c.id + '/metrics',
		)
		metricsData.value = result.metrics
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
		await api('/api/campaigns/' + c.id + '/move', {
			method: 'POST',
			body: { direction, target_id: targetId },
		})
		await refresh()
		announceChanges()
		flash(t('communications', 'Display order updated.'), 'success')
	} catch (e) {
		flash(errorMessageFor(e), 'error')
	} finally {
		orderSaving.value = false
	}
}
function startReorderDrag(event: DragEvent, c: CampaignRecord) {
	if (c.status === 'archived' || orderSaving.value) return event.preventDefault()
	draggingCampaignId.value = c.id
	if (event.dataTransfer) {
		event.dataTransfer.effectAllowed = 'move'
		event.dataTransfer.setData('text/plain', String(c.id))
	}
}
function endReorderDrag() {
	draggingCampaignId.value = null
}
async function dropReorder(event: DragEvent, target: CampaignRecord) {
	const from = draggingCampaignId.value
	draggingCampaignId.value = null
	if (from == null || from === target.id || target.status === 'archived') return
	const moving = campaigns.value.find((c) => c.id === from)
	if (!moving) return
	await moveCampaign(moving, 'before', target.id)
}
async function duplicate(c: CampaignRecord) {
	try {
		const result = await api<{ campaign: CampaignRecord }>(
			'/api/campaigns/' + c.id + '/duplicate',
			{ method: 'POST', body: {} },
		)
		await refresh()
		announceChanges()
		openEdit(result.campaign)
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
		await api('/api/campaigns/' + id + '/purge', {
			method: 'POST',
			body: { confirmName: deleteName.value },
		})
		confirmDeleteOpen.value = false
		deleteTarget.value = null
		deleteName.value = ''
		await refresh()
		announceChanges()
		flash(
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
		await api('/api/campaigns/' + archiveTarget.value.id, {
			method: 'DELETE',
			body: {},
		})
		await refresh()
		announceChanges()
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
	userSearchOptions.value = []
	form.value = makeDefault()
	initialFormSnapshot.value = JSON.stringify(serializableForm())
	editorId.value = null
	errorMessage.value = ''
	feedback.value = ''
	wizardStep.value = 1
	view.value = 'editor'
}
function openEdit(c: CampaignRecord) {
	const groupIds = c.audience_groups?.length
		? c.audience_groups
		: c.audience_group
			? [c.audience_group]
			: []
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
	userSearchOptions.value = []
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
	if (data.audience_type !== 'group') data.audience_group = ''
	saving.value = true
	try {
		const isNew = editorId.value === null
		const saved = await api<{ campaign: CampaignRecord }>(
			isNew ? '/api/campaigns' : '/api/campaigns/' + editorId.value,
			{
				method: isNew ? 'POST' : 'PUT',
				body: data,
			},
		)
		await refresh()
		announceChanges()
		if (
			isNew
			&& ['nextcloud.flow.matched', 'external.webhook'].includes(
				data.trigger_key,
			)
		) {
			editorId.value = saved.campaign.id
			initialFormSnapshot.value = JSON.stringify(serializableForm())
			flash(
				data.trigger_key === 'nextcloud.flow.matched'
					? t(
							'communications',
							"Publication saved. Configure the rule in Flow using this publication's name.",
						)
					: t(
							'communications',
							'Publication saved. Use the identifier shown in the webhook example before testing.',
						),
				'success',
			)
		} else {
			view.value = 'list'
			flash(
				isNew
					? t('communications', 'Publication created.')
					: t('communications', 'Publication updated.'),
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
		const [metadata, data] = await Promise.all([
			api<CampaignCatalog>('/api/catalog'),
			api<{ campaigns: CampaignRecord[] }>('/api/campaigns'),
		])
		catalog.value = metadata
		editorGroupValues.value = metadata.editorGroups || []
		campaigns.value = data.campaigns || []
		if (new URL(location.href).searchParams.get('view') === 'new') openCreate()
	} catch (e) {
		flash(errorMessageFor(e), 'error')
	} finally {
		loading.value = false
	}
})
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

			<template v-if="view === 'list'">
				<div class="cc-native-heading cc-native-heading-overview">
					<div>
						<h2>{{ t('communications', 'Overview') }}</h2>
						<p>
							{{
								t(
									'communications',
									'Track active, scheduled, and draft publications.',
								)
							}}
						</p>
					</div>
				</div>

				<div class="cc-native-stats">
					<section
						v-for="stat in stats"
						:key="stat.label"
						class="cc-native-stat">
						<span>{{ stat.label }}</span>
						<strong>{{ stat.count }}</strong>
					</section>
				</div>

				<section class="cc-native-panel">
					<div class="cc-native-list-head">
						<div>
							<h2>{{ t('communications', 'All publications') }}</h2>
							<p>
								{{
									t(
										'communications',
										'Manage persistent and contextual notices in one place. Reorder by dragging or using keyboard controls.',
									)
								}}
							</p>
						</div>
						<div class="cc-native-filters">
							<NcTextField
								v-model="search"
								:label="t('communications', 'Search publications')"
								type="search" />
							<NcSelect
								v-model="filterStatus"
								:options="statuses"
								:reduce="selectable"
								:clearable="false"
								input-id="cc-status-filter"
								:aria-label="
									t('communications', 'Filter by status')
								" />
						</div>
					</div>

					<NcEmptyContent
						v-if="!loading && !visible.length"
						:name="t('communications', 'No publications found')"
						:description="
							t(
								'communications',
								'Create a publication or change the filters.',
							)
						" />
					<div v-else class="cc-native-table-scroll">
						<table class="cc-native-table">
							<thead>
								<tr>
									<th>{{ t('communications', 'Publication') }}</th>
									<th>{{ t('communications', 'Trigger') }}</th>
									<th>{{ t('communications', 'Audience') }}</th>
									<th>{{ t('communications', 'Period') }}</th>
									<th>{{ t('communications', 'Status') }}</th>
									<th>{{ t('communications', 'Actions') }}</th>
								</tr>
							</thead>
							<tbody>
								<tr
									v-for="campaign in pageVisible"
									:key="campaign.id"
									:draggable="
										campaign.status !== 'archived'
										&& !orderSaving
									"
									@dragstart="startReorderDrag($event, campaign)"
									@dragend="endReorderDrag"
									@dragover.prevent
									@drop.prevent="dropReorder($event, campaign)">
									<td>
										<strong>{{ campaign.name }}</strong>
										<small>{{ campaign.headline }}</small>
									</td>
									<td>{{ triggerLabel(campaign) }}</td>
									<td>{{ groupLabel(campaign) }}</td>
									<td>{{ period(campaign) }}</td>
									<td>
										<span
											class="cc-native-status"
											:data-status="effectiveStatus(campaign)">
											{{ labels[effectiveStatus(campaign)] }}
										</span>
									</td>
									<td class="cc-native-table-actions">
										<div class="cc-native-row-actions">
											<NcButton
												size="small"
												:text="t('communications', 'Edit')"
												@click="openEdit(campaign)" />
											<NcActions
												:aria-label="
													t(
														'communications',
														'Actions for {name}',
														{ name: campaign.name },
													)
												"
												:open="
													rowMenuOpen[campaign.id] || false
												"
												@update:open="
													rowMenuOpen[campaign.id] = $event
												">
												<NcActionButton
													v-if="
														campaign.status
														!== 'archived'
													"
													:disabled="orderSaving"
													@click="
														moveCampaign(campaign, 'up')
													">
													<template #icon
														><NcIconSvgWrapper
															:path="mdiArrowUp"
															:size="20"
													/></template>
													{{
														t(
															'communications',
															'Move up',
														)
													}}
												</NcActionButton>
												<NcActionButton
													v-if="
														campaign.status
														!== 'archived'
													"
													:disabled="orderSaving"
													@click="
														moveCampaign(
															campaign,
															'down',
														)
													">
													<template #icon
														><NcIconSvgWrapper
															:path="mdiArrowDown"
															:size="20"
													/></template>
													{{
														t(
															'communications',
															'Move down',
														)
													}}
												</NcActionButton>
												<NcActionButton
													@click="openPreview(campaign)">
													<template #icon
														><NcIconSvgWrapper
															:path="mdiEyeOutline"
															:size="20"
													/></template>
													{{
														t(
															'communications',
															'Preview presentation',
														)
													}}
												</NcActionButton>
												<NcActionButton
													v-if="
														campaign.status
														!== 'archived'
													"
													@click="
														updateStatus(
															campaign,
															campaign.status
																=== 'active'
																? 'paused'
																: 'active',
														)
													">
													<template #icon
														><NcIconSvgWrapper
															:path="
																campaign.status
																=== 'active'
																	? mdiPauseCircleOutline
																	: mdiPlayCircleOutline
															"
															:size="20"
													/></template>
													{{
														campaign.status === 'active'
															? t(
																	'communications',
																	'Pause',
																)
															: t(
																	'communications',
																	'Activate',
																)
													}}
												</NcActionButton>
												<NcActionButton
													v-else
													@click="
														updateStatus(
															campaign,
															'draft',
														)
													">
													<template #icon
														><NcIconSvgWrapper
															:path="mdiBackupRestore"
															:size="20"
													/></template>
													{{
														t(
															'communications',
															'Restore',
														)
													}}
												</NcActionButton>
												<NcActionButton
													@click="openMetrics(campaign)">
													<template #icon
														><NcIconSvgWrapper
															:path="mdiChartBar"
															:size="20"
													/></template>
													{{
														t(
															'communications',
															'Statistics',
														)
													}}
												</NcActionButton>
												<NcActionButton
													@click="duplicate(campaign)">
													<template #icon
														><NcIconSvgWrapper
															:path="mdiContentCopy"
															:size="20"
													/></template>
													{{
														t(
															'communications',
															'Duplicate',
														)
													}}
												</NcActionButton>
												<NcActionButton
													v-if="
														campaign.status
														=== 'archived'
													"
													@click="askDelete(campaign)">
													{{
														t(
															'communications',
															'Delete permanently',
														)
													}}
												</NcActionButton>
												<NcActionButton
													v-if="
														campaign.status
														!== 'archived'
													"
													@click="askArchive(campaign)">
													<template #icon
														><NcIconSvgWrapper
															:path="mdiArchiveOutline"
															:size="20"
													/></template>
													{{
														t(
															'communications',
															'Archive',
														)
													}}
												</NcActionButton>
											</NcActions>
										</div>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					<nav
						v-if="pageCount > 1"
						class="cc-native-pagination"
						:aria-label="t('communications', 'Publication pages')">
						<span
							>{{ t('communications', 'Showing') }}
							{{ (currentPage - 1) * PAGE_SIZE + 1 }}–{{
								Math.min(currentPage * PAGE_SIZE, visible.length)
							}}
							{{ t('communications', 'of') }}
							{{ visible.length }}</span
						>
						<NcButton
							:text="t('communications', 'Previous')"
							:disabled="currentPage <= 1"
							@click="page = currentPage - 1" />
						<span
							>{{ t('communications', 'Page') }} {{ currentPage }}
							{{ t('communications', 'of') }} {{ pageCount }}</span
						>
						<NcButton
							:text="t('communications', 'Next')"
							:disabled="currentPage >= pageCount"
							@click="page = currentPage + 1" />
					</nav>
				</section>
				<section
					class="cc-editor-settings"
					:aria-label="t('communications', 'Editorial settings')">
					<details
						v-if="catalog.canConfigureEditors"
						class="cc-progressive-details cc-editor-delegation">
						<summary>
							{{ t('communications', 'Editorial permissions') }}
						</summary>
						<p class="cc-native-hint">
							{{
								t(
									'communications',
									'Authorized groups can manage publications. Only administrators can change permissions.',
								)
							}}
						</p>
						<div class="cc-native-control">
							<label for="cc-editor-groups">{{
								t(
									'communications',
									'Groups with editorial permissions',
								)
							}}</label>
							<NcSelect
								v-model="editorGroupValues"
								:options="groupOptions"
								:reduce="selectable"
								multiple
								:clearable="true"
								input-id="cc-editor-groups"
								:placeholder="
									t('communications', 'Select editor groups')
								" />
						</div>
						<NcButton
							:text="t('communications', 'Save delegation')"
							variant="primary"
							:disabled="editorConfigLoading"
							@click="saveEditorGroups" />
					</details>
				</section>
				<p class="cc-native-caption">
					{{
						t(
							'communications',
							'Browser-reported impressions are estimates, not audited reading or billing metrics.',
						)
					}}
				</p>
			</template>

			<template v-else>
				<div class="cc-native-heading cc-native-heading-editor">
					<div class="cc-wizard-heading">
						<NcButton
							variant="tertiary"
							:text="t('communications', 'Back to publications')"
							@click="cancelEdit" />
						<h1>
							{{
								editMode
									? t('communications', 'Edit publication')
									: t('communications', 'New publication')
							}}
						</h1>
						<p>
							{{
								t(
									'communications',
									'One message, multiple delivery methods. Configure the timing, content, and recipients.',
								)
							}}
						</p>
					</div>
				</div>

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
									{{ t('communications', 'Availability period') }}
								</h3>
								<div class="cc-native-pair">
									<NcDateTimePickerNative
										v-model="form.starts_at"
										:label="t('communications', 'Start *')"
										type="datetime-local"
										clearable />
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
									{{
										t(
											'communications',
											'Message, audience and delivery',
										)
									}}
								</h2>
								<p>
									{{
										t(
											'communications',
											'Write a message, select recipients, and configure every channel in one workflow.',
										)
									}}
								</p>
							</div>
							<section class="cc-native-section">
								<h2>{{ t('communications', 'Identification') }}</h2>
								<NcTextField
									v-model="form.name"
									:label="t('communications', 'Internal name *')"
									:maxlength="160"
									:placeholder="
										t(
											'communications',
											'Example: Welcome notice',
										)
									" />
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
								<h2>{{ t('communications', 'Content') }}</h2>
								<NcTextField
									v-model="form.headline"
									:label="t('communications', 'Displayed title *')"
									:maxlength="180" />
								<div class="cc-native-rich">
									<MarkdownEditor
										v-model="form.message"
										:label="
											t(
												'communications',
												'Displayed message *',
											)
										"
										:description="contentFormattingHelp"
										:placeholder="
											t(
												'communications',
												'Write your message…',
											)
										"
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
										{{
											t(
												'communications',
												'Add an action button (optional)',
											)
										}}
									</summary>
									<div class="cc-native-pair">
										<NcTextField
											v-model="form.cta_label"
											:label="
												t('communications', 'Button text')
											"
											:maxlength="80" />
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
										{{
											t(
												'communications',
												'Message translations (optional)',
											)
										}}
									</summary>
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
											t(
												'communications',
												'Translation language',
											)
										}}</label>
										<NcSelect
											v-model="translationLocale"
											:options="languageOptions"
											:reduce="selectable"
											:clearable="false"
											input-id="cc-translation-locale" />
									</div>
									<NcTextField
										v-model="translatedHeadline"
										:label="
											t('communications', 'Translated title')
										"
										:maxlength="180" />
									<NcTextArea
										v-model="translatedMessage"
										:label="
											t('communications', 'Translated message')
										"
										:maxlength="4000" />
									<NcTextField
										v-model="translatedCta"
										:label="
											t('communications', 'Translated button')
										"
										:maxlength="80" />
								</details>
							</section>
							<section class="cc-native-section">
								<h2>{{ t('communications', 'Recipients') }}</h2>
								<div class="cc-native-control">
									<label for="cc-audience">{{
										t('communications', 'Send to')
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
										:input-label="
											t('communications', 'Recipient groups')
										"
										:placeholder="
											t(
												'communications',
												'Select one or more groups',
											)
										"
										multiple
										keep-open />
									<div class="cc-native-control">
										<label for="cc-group-match">{{
											t('communications', 'Group matching')
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
										:input-label="
											t('communications', 'Recipient users')
										"
										:placeholder="
											t(
												'communications',
												'Search by name or identifier',
											)
										"
										multiple
										keep-open
										@search="searchUsers" />
									<p class="cc-native-hint">
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
											:input-label="
												t(
													'communications',
													'Excluded groups',
												)
											"
											:placeholder="
												t(
													'communications',
													'These groups will not receive the publication',
												)
											"
											multiple
											keep-open />
										<label for="cc-excluded-match">{{
											t(
												'communications',
												'How to match excluded groups',
											)
										}}</label>
										<NcSelect
											v-model="form.excluded_match"
											:options="excludedMatchOptions"
											:reduce="selectable"
											:clearable="false"
											input-id="cc-excluded-match" />
										<p class="cc-native-hint">
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
									{{ t('communications', 'Delivery methods') }}
								</h2>
								<p class="cc-native-hint">
									{{
										t(
											'communications',
											'Select one or more destinations. Contextual banners and modal dialogs are alternatives: selecting one disables the other. Inbox, notifications, activity, email, and messaging can be freely combined.',
										)
									}}
								</p>
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
												t(
													'communications',
													'Messaging and SMS (Two-Factor Gateway)',
												)
											}}</strong>
											<span>{{
												gatewayGroup?.installed
													? gatewayReadyCount
														+ ' de '
														+ gatewayOptions.length
														+ ' provedores configurados'
													: t(
															'communications',
															'App not installed',
														)
											}}</span>
										</summary>
										<p class="cc-native-hint">
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
									{{ t('communications', 'Allow Inbox comments') }}
								</NcCheckboxRadioSwitch>
								<NcNoteCard
									v-if="!hasDestination"
									type="warning"
									class="cc-delivery-empty-warning">
									{{
										t(
											'communications',
											'Select at least one delivery method to continue.',
										)
									}}
								</NcNoteCard>
								<p v-if="eventDrivenDelivery" class="cc-native-hint">
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
									{{
										t(
											'communications',
											'A banner or modal appears on the next eligible visit. Other selected channels are queued once per activation, respecting the start date.',
										)
									}}
								</p>
								<p v-if="noticeEmail" class="cc-native-hint">
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
											>{{
												t('communications', 'Message type')
											}}</span
										>
										<p class="cc-native-hint">
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
												t('communications', 'Banner color')
											}}</span>
											<NcColorPicker
												v-model="form.banner_color"
												:palette="colorPickerPalette"
												advanced-fields
												container="body">
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
														{{
															t(
																'communications',
																'— Choose color',
															)
														}}</span
													>
												</NcButton>
											</NcColorPicker>
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
												t('communications', 'Banner icon')
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
											t(
												'communications',
												'Allow dismissing the banner',
											)
										}}</NcCheckboxRadioSwitch
									>
								</div>
								<div
									v-if="
										form.presentation === 'banner'
										|| form.presentation === 'board'
									"
									class="cc-native-control">
									<label for="cc-text-align">{{
										t('communications', 'Text alignment')
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
										|| form.presentation === 'board'
									"
									class="cc-banner-color-picker">
									<label>{{
										t('communications', 'Text color (optional)')
									}}</label>
									<NcColorPicker
										v-model="selectedTextColor"
										clearable
										advanced-fields
										container="body">
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
									{{ t('communications', 'Review and publish') }}
								</h2>
								<p>
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
									{{ t('communications', 'Configured delivery') }}
								</h2>
								<p class="cc-review-delivery-line">
									{{ deliveryReview }}
								</p>
								<p
									v-if="hasAdditionalNotices"
									class="cc-native-hint">
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
											t('communications', 'Visual destination')
										}}</span>
										<strong>{{
											form.presentation === 'modal'
												? t('communications', 'Modal dialog')
												: form.presentation === 'banner'
													? t(
															'communications',
															'Contextual banner',
														)
													: t(
															'communications',
															'No contextual display',
														)
										}}</strong>
									</div>
									<div
										class="cc-native-control cc-native-priority">
										<label for="cc-priority">{{
											t('communications', 'Priority (0–100)')
										}}</label>
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
											&& form.app_target_mode === 'legacy'
										"
										class="cc-native-control">
										<label for="cc-placement">{{
											t('communications', 'Where to display')
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
											t('communications', 'Frequency limit')
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
										t(
											'communications',
											'Where to display the publication',
										)
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
											t(
												'communications',
												'Included or excluded applications *',
											)
										}}</label>
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
											|| !['legacy', 'all'].includes(
												form.app_target_mode,
											)
										">
										{{
											t(
												'communications',
												'Also show to signed-out visitors (public pages)',
											)
										}}
									</NcCheckboxRadioSwitch>
									<p class="cc-native-hint">
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
									<NcTextField
										v-model="form.max_total"
										:label="
											t(
												'communications',
												'Maximum per user (0 = unlimited)',
											)
										"
										type="number" />
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
											t('communications', 'Category')
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
											t('communications', 'Visual theme')
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
									<NcDateTimePickerNative
										v-model="form.starts_at"
										:label="
											t('communications', 'Start (optional)')
										"
										type="datetime-local"
										clearable />
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
										t('communications', 'Publication status')
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
										? t(
												'communications',
												'The publication will become active according to the configured schedule and rules.',
											)
										: t(
												'communications',
												'While draft or paused, this publication will not be displayed or sent.',
											)
								}}
							</NcNoteCard>
						</div>

						<div class="cc-native-editor-footer cc-wizard-footer">
							<NcButton
								:text="t('communications', 'Cancel')"
								variant="secondary"
								@click="cancelEdit" />
							<div class="cc-wizard-footer-actions">
								<NcButton
									v-if="wizardStep > 1"
									:text="t('communications', 'Back')"
									@click="goToWizardStep(wizardStep - 1)" />
								<span
									v-if="wizardStep === 1 && !canContinue"
									class="cc-wizard-required-hint"
									>{{
										t(
											'communications',
											'Choose a valid condition to continue.',
										)
									}}</span
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
											? t('communications', 'Saving…')
											: t('communications', 'Save publication')
									"
									@click="save" />
							</div>
						</div>
					</div>

					<aside
						v-if="wizardStep > 1"
						class="cc-wizard-sidebar"
						:aria-label="
							t('communications', 'Publication summary and preview')
						">
						<section class="cc-wizard-summary">
							<h2>
								<NcIconSvgWrapper
									:path="mdiBullhornOutline"
									:size="19" />
								{{ t('communications', 'Publication summary') }}
							</h2>
							<dl class="cc-wizard-summary-list">
								<div>
									<NcIconSvgWrapper
										:path="mdiLightningBoltOutline"
										:size="17" />
									<dt>{{ t('communications', 'When') }}</dt>
									<dd>{{ summaryWhen }}</dd>
								</div>
								<div>
									<NcIconSvgWrapper
										:path="mdiAccountGroupOutline"
										:size="17" />
									<dt>{{ t('communications', 'For whom') }}</dt>
									<dd>{{ audienceReview }}</dd>
								</div>
								<div>
									<NcIconSvgWrapper
										:path="mdiMapMarkerOutline"
										:size="17" />
									<dt>{{ t('communications', 'Context') }}</dt>
									<dd>{{ locationReview }}</dd>
								</div>
								<div>
									<NcIconSvgWrapper
										:path="mdiBullhornOutline"
										:size="17" />
									<dt>{{ t('communications', 'Delivery') }}</dt>
									<dd>{{ deliveryReview }}</dd>
								</div>
								<div>
									<NcIconSvgWrapper :path="mdiRepeat" :size="17" />
									<dt>{{ t('communications', 'Frequency') }}</dt>
									<dd>{{ frequencyReview }}</dd>
								</div>
								<div>
									<NcIconSvgWrapper
										:path="mdiCalendarRange"
										:size="17" />
									<dt>
										{{
											t(
												'communications',
												'Availability period',
											)
										}}
									</dt>
									<dd>{{ scheduleReview }}</dd>
								</div>
							</dl>
						</section>

						<section class="cc-wizard-preview">
							<h2>
								<NcIconSvgWrapper :path="mdiEyeOutline" :size="19" />
								{{
									form.presentation === 'none'
										? t('communications', 'Message preview')
										: t('communications', 'Preview')
								}}
							</h2>
							<component
								:is="
									form.presentation === 'banner'
										? NcNoteCard
										: 'div'
								"
								:type="
									form.presentation === 'banner'
										? bannerPreviewAppearance.type
										: undefined
								"
								:class="
									form.presentation === 'banner'
										? [
												'cc-wizard-preview-notecard',
												bannerPreviewAppearance.noteClass,
											]
										: 'cc-wizard-preview-content'
								"
								:style="
									form.presentation === 'banner'
										? bannerPreviewAppearance.style
										: undefined
								"
								:role="
									form.presentation === 'banner'
										? 'group'
										: undefined
								"
								:aria-label="
									form.presentation === 'banner'
										? t(
												'communications',
												'Banner visual preview',
											)
										: undefined
								"
								:data-presentation="form.presentation">
								<template
									v-if="
										form.presentation === 'banner'
										&& (bannerPreviewAppearance.iconPath
											|| bannerPreviewAppearance.hideIcon)
									"
									#icon>
									<NcIconSvgWrapper
										v-if="bannerPreviewAppearance.iconPath"
										class="cc-banner-note-icon"
										:path="bannerPreviewAppearance.iconPath"
										:size="20"
										:style="{ color: 'var(--note-theme)' }"
										inline />
									<span
										v-else
										class="cc-note-empty-icon"
										aria-hidden="true"></span>
								</template>
								<strong
									:style="
										form.text_align
											? { textAlign: form.text_align }
											: undefined
									"
									>{{
										form.headline
										|| t('communications', 'Publication title')
									}}</strong
								>
								<div
									class="cc-wizard-preview-message"
									:style="
										form.text_align
											? { textAlign: form.text_align }
											: undefined
									">
									<NcRichText
										v-if="form.message"
										:text="form.message"
										:use-markdown="true" />
									<p v-else>
										{{
											t(
												'communications',
												'Write a message in the Message and audience step to see a preview.',
											)
										}}
									</p>
								</div>
								<span
									v-if="form.cta_label"
									class="cc-native-preview-link"
									>{{ form.cta_label }}</span
								>
							</component>
							<p
								v-if="form.presentation === 'none'"
								class="cc-native-hint">
								{{
									t(
										'communications',
										'Content preview. Appearance depends on the channel (email, Notifications, WhatsApp, etc.).',
									)
								}}
							</p>
							<NcButton
								wide
								:text="
									form.presentation === 'none'
										? t('communications', 'Preview message')
										: t('communications', 'Test presentation')
								"
								:disabled="
									!form.headline.trim() || !form.message.trim()
								"
								@click="openPreview()">
								<template #icon
									><NcIconSvgWrapper
										:path="mdiEyeOutline"
										:size="18"
								/></template>
							</NcButton>
						</section>
					</aside>
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

	<NcDialog
		v-if="
			previewOpen
			&& ['board', 'none'].includes((previewCampaign || form).presentation)
		"
		:open="previewOpen"
		:name="
			(previewCampaign || form).headline
			|| t('communications', 'Publication preview')
		"
		container="body"
		size="normal"
		@update:open="!$event && closePreview()">
		<div
			class="cc-board-preview-dialog"
			role="document"
			:aria-label="t('communications', 'Publication content')">
			<p
				v-if="(previewCampaign || form).presentation === 'none'"
				class="cc-native-hint">
				{{
					t(
						'communications',
						'Content preview; no message will be sent. Each provider may apply its own formatting.',
					)
				}}
			</p>
			<NcRichText
				:text="(previewCampaign || form).message || ''"
				:use-markdown="true" />
		</div>
		<template #actions>
			<NcButton
				variant="primary"
				:text="t('communications', 'Close')"
				@click="closePreview" />
		</template>
	</NcDialog>

	<NcDialog
		v-model:open="metricsOpen"
		:name="'Resultados: ' + (metricsCampaign?.name || '')"
		size="normal"
		container="body">
		<div class="cc-native-metrics">
			<p>
				{{
					t(
						'communications',
						'Aggregate statistics estimated from browser-reported events. Not audited for billing.',
					)
				}}
			</p>
			<p v-if="metricsLoading">{{ t('communications', 'Loading…') }}</p>
			<div v-else-if="metricsData" class="cc-native-metrics-grid">
				<section>
					<strong>{{ metricsData.estimatedImpressions }}</strong
					><span>{{ t('communications', 'Estimated impressions') }}</span>
				</section>
				<section>
					<strong>{{ metricsData.clicks }}</strong
					><span>{{ t('communications', 'Clicks') }}</span>
				</section>
				<section>
					<strong>{{ metricsData.dismissals }}</strong
					><span>{{ t('communications', 'Dismissals') }}</span>
				</section>
				<section>
					<strong>{{ metricsData.queuedOrClaimed }}</strong
					><span>{{
						t('communications', 'Queued or reserved deliveries')
					}}</span>
				</section>
			</div>
			<NcButton
				:text="t('communications', 'Close')"
				@click="metricsOpen = false" />
		</div>
	</NcDialog>

	<NcDialog
		v-model:open="confirmDeleteOpen"
		:name="t('communications', 'Permanently delete publication')"
		size="small"
		container="body">
		<div class="cc-permanent-delete-confirm">
			<p>
				{{
					t(
						'communications',
						'Irreversible deletion: the publication, comments, deliveries, metrics, and read records will be removed.',
					)
				}}
			</p>
			<p>
				{{
					t('communications', 'To confirm, enter the exact internal name')
				}}
				<strong>{{ deleteTarget?.name }}</strong
				>.
			</p>
			<NcTextField
				v-model="deleteName"
				:label="t('communications', 'Internal name for confirmation')" />
		</div>
		<template #actions>
			<NcButton
				:text="t('communications', 'Cancel')"
				@click="confirmDeleteOpen = false" />
			<NcButton
				variant="error"
				:text="t('communications', 'Delete permanently')"
				:disabled="deleting || deleteName !== deleteTarget?.name"
				@click="deletePermanently" />
		</template>
	</NcDialog>

	<NcDialog
		v-model:open="confirmArchiveOpen"
		:name="t('communications', 'Archive publication')"
		size="small"
		container="body">
		<p>
			{{ t('communications', 'Archive “') }}{{ archiveTarget?.name
			}}{{
				t(
					'communications',
					'”? It will no longer be displayed and can be restored later.',
				)
			}}
		</p>
		<template #actions>
			<NcButton
				:text="t('communications', 'Cancel')"
				@click="confirmArchiveOpen = false" />
			<NcButton
				variant="error"
				:text="t('communications', 'Archive')"
				@click="archive" />
		</template>
	</NcDialog>
</template>

<style lang="scss" scoped>
.cc-native-app {
	width: 100%;
	max-width: none;
	min-height: 0;
	overflow: auto;
}

.cc-native-page {
	width: 100%;
	min-height: 100%;
	max-height: none;
	padding: 24px clamp(14px, 3vw, 48px) 44px;
	box-sizing: border-box;
	overflow-wrap: anywhere;
}

.cc-native-page *,
.cc-native-page *::before,
.cc-native-page *::after {
	box-sizing: border-box;
}

.cc-native-page h1 {
	font-size: clamp(25px, 2.5vw, 32px);
	font-weight: 650;
	margin: 8px 0;
}

.cc-native-page h2 {
	font-size: 18px;
	font-weight: 650;
	margin-bottom: 14px;
}

.cc-native-page p {
	line-height: 1.5;
}

.cc-native-heading {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 16px;
	margin-bottom: 24px;
}

.cc-native-heading > div:first-child {
	flex: 1;
	min-width: 260px;
}

.cc-native-heading p {
	color: var(--color-text-maxcontrast);
	margin: 6px 0 0;
}

.cc-native-stats {
	display: grid;
	grid-template-columns: repeat(5, minmax(0, 1fr));
	gap: 12px;
	margin-bottom: 24px;
}

.cc-native-stat {
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 12px);
	background: var(--color-background-hover);
	display: grid;
	gap: 8px;
}

.cc-native-stat span {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.cc-native-stat strong {
	font-size: 26px;
	font-weight: 650;
	font-variant-numeric: tabular-nums;
}

.cc-native-panel {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 12px);
	overflow: hidden;
	background: var(--color-main-background);
}

.cc-native-list-head {
	display: flex;
	flex-wrap: wrap;
	gap: 18px;
	align-items: center;
	justify-content: space-between;
	padding: 18px;
}

.cc-native-list-head h2 {
	margin: 0;
}

.cc-native-list-head p {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	margin: 5px 0 0;
}

.cc-native-filters {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 12px;
}

.cc-native-filters > * {
	min-width: 175px;
	width: min(240px, 100%);
}

.cc-native-table-scroll {
	overflow-x: auto;
	max-width: 100%;
}

.cc-native-table {
	width: 100%;
	min-width: 820px;
	border-collapse: collapse;
}

.cc-native-table thead {
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
}

.cc-native-table th,
.cc-native-table td {
	padding: 12px 15px;
	text-align: start;
	border-bottom: 1px solid var(--color-border);
	font-size: 13px;
	vertical-align: middle;
}

.cc-native-table th {
	font-weight: 650;
}

.cc-native-table td:first-child {
	min-width: 195px;
}

.cc-native-table td strong {
	display: block;
	font-weight: 650;
}

.cc-native-table td small {
	display: block;
	color: var(--color-text-maxcontrast);
	margin-top: 5px;
}

.cc-native-table-actions {
	display: table-cell;
	vertical-align: middle !important;
	text-align: end !important;
	white-space: nowrap;
}

.cc-native-row-actions {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
	min-height: 36px;
}

.cc-native-status {
	display: inline-flex;
	padding: 4px 10px;
	border-radius: 14px;
	border: 1px solid var(--color-border);
	color: var(--color-main-text);
	background: var(--color-background-hover);
	font-size: 12px;
	white-space: nowrap;
}

.cc-native-status[data-status='active'] {
	border-color: var(--color-success);
}

.cc-native-status[data-status='scheduled'] {
	border-color: var(--color-primary-element);
}

.cc-native-caption {
	margin: 20px 0;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.cc-native-heading-editor {
	border-bottom: 1px solid var(--color-border);
	padding-bottom: 17px;
}

.cc-native-pagination {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	flex-wrap: wrap;
	gap: 12px;
	border-top: 1px solid var(--color-border);
	padding: 12px 18px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.cc-native-priority {
	display: flex;
	flex-direction: column;
	justify-content: flex-start;
	min-width: 0;
}

.cc-native-editor-layout {
	display: grid;
	grid-template-columns: minmax(0, 1.4fr) minmax(265px, 0.7fr);
	gap: clamp(18px, 2.5vw, 36px);
	align-items: start;
}

.cc-native-section {
	display: flex;
	flex-direction: column;
	gap: 15px;
	padding: 22px 0;
	border-bottom: 1px solid var(--color-border);
}

.cc-native-section:first-child {
	padding-top: 5px;
}

.cc-native-section:last-child {
	border: none;
}

.cc-native-section h2 {
	margin: 0 0 4px;
}

.cc-native-control {
	display: flex;
	flex-direction: column;
	gap: 5px;
}

.cc-native-control label,
.cc-native-label {
	font-size: 13px;
	font-weight: 650;
}

.cc-native-pair {
	display: grid;
	grid-template-columns: 1fr 1fr;
	align-items: start;
	gap: 14px;
}

.cc-native-hint {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	margin: 3px 0 0;
}

.cc-native-rich {
	display: flex;
	flex-direction: column;
	gap: 7px;
}

.cc-native-rich .rich-contenteditable,
.cc-native-rich [contenteditable='true'] {
	min-height: 126px;
	max-height: 380px;
	overflow-y: auto;
	white-space: pre-wrap;
}

.cc-native-preview-link {
	color: var(--color-primary-element);
	font-weight: 650;
}

.cc-native-editor-footer {
	display: flex;
	justify-content: flex-end;
	gap: 12px;
	flex-wrap: wrap;
	align-items: center;
	padding: 24px 0 32px;
	margin-top: 16px;
	border-top: 1px solid var(--color-border);
	position: static;
}

.cc-native-feedback {
	margin-bottom: 20px;
}

@media (max-width: 1090px) {
	.cc-native-editor-layout {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 1090px) {
	.cc-native-stats {
		grid-template-columns: repeat(3, minmax(0, 1fr));
	}
}

@media (max-width: 640px) {
	.cc-native-stats {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (max-width: 640px) {
	.cc-native-pair {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 640px) {
	.cc-native-filters {
		width: 100%;
	}
}

@media (max-width: 640px) {
	.cc-native-filters > * {
		flex: 1;
		min-width: 160px;
	}
}

@media (max-width: 640px) {
	.cc-native-page {
		padding: 14px 12px 28px;
	}
}

.cc-native-metrics {
	padding: 12px 18px 24px;
}

.cc-native-metrics > p {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.cc-native-metrics-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
	margin: 16px 0 26px;
}

.cc-native-metrics-grid section {
	display: flex;
	flex-direction: column;
	gap: 6px;
	border: 1px solid var(--color-border);
	border-radius: 10px;
	padding: 16px;
}

.cc-native-metrics-grid strong {
	font-size: 25px;
	font-variant-numeric: tabular-nums;
}

.cc-native-metrics-grid span {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.cc-native-heading-editor {
	border-bottom: 0;
	margin-bottom: 12px;
	padding-bottom: 0;
}

.cc-wizard-heading {
	min-width: 0;
}

.cc-wizard-heading h1 {
	margin: 8px 0 2px;
}

.cc-wizard-heading p {
	margin: 4px 0;
}

.cc-wizard-steps {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 10px;
	margin-bottom: 16px;
	padding: 0 0 12px;
	border-bottom: 1px solid var(--color-border);
}

.cc-wizard-steps button {
	min-width: 0;
}

.cc-native-editor-layout {
	grid-template-columns: minmax(0, 1fr) minmax(285px, 335px);
	gap: clamp(14px, 1.8vw, 22px);
	align-items: start;
}

.cc-wizard-main {
	min-width: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 12px);
	padding: 18px 20px 0;
	background: var(--color-main-background);
}

.cc-wizard-step-content {
	min-width: 0;
}

.cc-wizard-step-content > .cc-native-section {
	padding: 15px 0 20px;
}

.cc-wizard-step-content > .cc-native-section:first-of-type {
	padding-top: 5px;
}

.cc-wizard-step-title {
	padding: 0 0 12px;
	border-bottom: 1px solid var(--color-border);
}

.cc-wizard-step-title h2 {
	margin: 0 0 3px;
	font-size: 18px;
}

.cc-wizard-step-title p {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.cc-wizard-footer {
	margin-top: 18px;
	padding: 15px 0 17px;
	justify-content: space-between;
	position: static;
	background: none;
}

.cc-wizard-footer-actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 10px;
}

.cc-wizard-error {
	margin-bottom: 16px;
}

.cc-automation-action-field .cc-native-hint {
	margin-top: 6px;
}

.cc-wizard-sidebar {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: 0;
	position: sticky;
	top: 16px;
}

.cc-wizard-summary,
.cc-wizard-preview {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 12px);
	background: var(--color-background-hover);
	padding: 14px;
	min-width: 0;
}

.cc-wizard-summary h2,
.cc-wizard-preview h2 {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 15px;
	font-weight: 650;
	margin: 0 0 12px;
}

.cc-wizard-summary-list {
	margin: 0;
}

.cc-wizard-summary-list > div {
	display: grid;
	grid-template-columns: 20px minmax(75px, 0.83fr) minmax(0, 1.15fr);
	gap: 7px;
	padding: 9px 0;
	align-items: center;
	border-bottom: 1px solid var(--color-border);
	min-width: 0;
}

.cc-wizard-summary-list > div:first-child {
	padding-top: 2px;
}

.cc-wizard-summary-list > div:last-child {
	border: 0;
	padding-bottom: 0;
}

.cc-wizard-summary-list dt {
	font-size: 12px;
	font-weight: 650;
}

.cc-wizard-summary-list dd {
	margin: 0;
	font-size: 12px;
	line-height: 1.35;
	color: var(--color-main-text);
	overflow-wrap: anywhere;
}

.cc-wizard-preview-content {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-inline-start: 3px solid var(--color-primary-element);
	border-radius: 8px;
	padding: 13px;
	margin: 0 0 12px;
}

.cc-wizard-preview-content[data-presentation='modal'] {
	border-inline-start-width: 1px;
	box-shadow: 0 2px 12px var(--color-box-shadow);
}

.cc-wizard-preview-notecard:deep(.notecard) {
	margin: 0 0 12px;
	padding: 13px;
	min-width: 0;
}

.cc-wizard-preview-notecard > div {
	min-width: 0;
}

.cc-wizard-preview-content > strong,
.cc-wizard-preview-notecard strong {
	display: block;
	margin-bottom: 6px;
	font-size: 14px;
	line-height: 1.3;
}

.cc-wizard-preview-message {
	max-height: 125px;
	overflow-y: auto;
	font-size: 12px;
	line-height: 1.45;
}

.cc-wizard-preview-message :is(p, ul, ol) {
	font-size: 12px;
	margin: 0 0 5px;
}

.cc-wizard-preview-message :last-child {
	margin-bottom: 0;
}

.cc-wizard-preview-content .cc-native-preview-link,
.cc-wizard-preview-notecard .cc-native-preview-link {
	display: inline-block;
	margin-top: 8px;
}

@media (max-width: 1180px) {
	.cc-native-editor-layout {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 1180px) {
	.cc-wizard-sidebar {
		position: static;
		display: grid;
		grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	}
}

@media (max-width: 800px) {
	.cc-wizard-sidebar {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 490px) {
	.cc-wizard-main {
		padding: 14px 12px 0;
	}
}

@media (max-width: 490px) {
	.cc-wizard-steps {
		gap: 4px;
	}
}

@media (max-width: 490px) {
	.cc-wizard-steps button {
		font-size: 11px;
		flex: 1;
	}
}

@media (max-width: 490px) {
	.cc-wizard-footer {
		gap: 10px;
	}
}

@media (max-width: 490px) {
	.cc-wizard-footer-actions {
		margin-inline-start: auto;
	}
}

.cc-wizard-summary-list > div {
	grid-template-columns: 18px minmax(66px, 82px) minmax(0, 1fr);
	gap: 9px;
	min-height: 34px;
	padding: 6px 0;
}

.cc-wizard-summary-list > div:first-child {
	padding-top: 0;
}

.cc-wizard-summary-list > div:last-child {
	padding-bottom: 0;
}

.cc-wizard-summary-list > div > :is(dt, dd) {
	display: block;
	float: none !important;
	width: auto !important;
	height: auto !important;
	min-height: 0 !important;
	min-width: 0;
	margin: 0 !important;
	padding: 0 !important;
	line-height: 1.35 !important;
	text-align: start;
}

.cc-wizard-summary-list > div > dt {
	font-size: 12px;
	font-weight: 650;
}

.cc-wizard-summary-list > div > dd {
	font-size: 12px;
	font-weight: 400;
}

.cc-wizard-summary-list > div > :deep(.icon-vue) {
	width: 18px !important;
	height: 18px !important;
	min-width: 18px !important;
	min-height: 18px !important;
}

.cc-wizard-summary-list > div > :deep(.icon-vue) svg {
	width: 18px !important;
	height: 18px !important;
}

.cc-wizard-step-short {
	display: none;
}
@media (max-width: 640px) {
	.cc-wizard-step-long {
		display: none;
	}
}
@media (max-width: 640px) {
	.cc-wizard-step-short {
		display: inline;
	}
}
@media (max-width: 640px) {
	.cc-wizard-steps {
		gap: 5px;
	}
}
@media (max-width: 640px) {
	.cc-wizard-steps button {
		flex: 1 1 0;
		min-width: 0;
		padding-inline: 6px;
		font-size: 12px;
	}
}

.cc-automation-field .cc-native-hint {
	margin: 2px 0;
}

.cc-wizard-steps :deep(.icon-vue) {
	min-width: 18px;
}

.cc-native-editor-layout.cc-wizard-unconfigured {
	grid-template-columns: minmax(0, 1fr);
}

.cc-wizard-unconfigured .cc-wizard-main {
	max-width: 960px;
}

.cc-wizard-required-hint {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	align-self: center;
}

.cc-wizard-footer button:disabled {
	cursor: not-allowed;
}

.cc-schedule-inline {
	padding: 10px 0 18px;
}

.cc-schedule-inline h3 {
	font-size: 14px;
	font-weight: 650;
	margin: 0;
}

.cc-banner-type-settings {
	display: grid;
	gap: 10px;
}

.cc-banner-type-label .cc-native-label {
	display: block;
	font-size: 13px;
	font-weight: 650;
}

.cc-banner-type-label .cc-native-hint {
	margin: 4px 0 0;
}

.cc-banner-type-options {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 10px;
}

.cc-banner-type-card {
	min-width: 0;
	width: 100% !important;
	height: auto !important;
	min-height: 56px !important;
	padding: 10px !important;
	border-radius: 9px !important;
	border: 1px solid var(--color-border) !important;
	border-inline-start: 4px solid var(--cc-banner-type-accent) !important;
	background: color-mix(
		in srgb,
		var(--cc-banner-type-background) 55%,
		var(--color-main-background)
	) !important;
	color: var(--color-main-text) !important;
}

.cc-banner-type-card[data-type='info'] {
	--cc-banner-type-background: var(--color-info);
	--cc-banner-type-accent: var(--color-info-text);
}

.cc-banner-type-card[data-type='success'] {
	--cc-banner-type-background: var(--color-success);
	--cc-banner-type-accent: var(--color-success-text);
}

.cc-banner-type-card[data-type='warning'] {
	--cc-banner-type-background: var(--color-warning);
	--cc-banner-type-accent: var(--color-warning-text);
}

.cc-banner-type-card[data-type='error'] {
	--cc-banner-type-background: var(--color-error);
	--cc-banner-type-accent: var(--color-error-text);
}

.cc-banner-type-card[data-type='neutral'] {
	--cc-banner-type-background: var(--color-main-background);
	--cc-banner-type-accent: var(--color-border-dark, var(--color-border));
}

.cc-banner-type-card[data-type='custom'] {
	--cc-banner-type-background: var(--color-primary-element);
	--cc-banner-type-accent: var(--color-primary-element);
	background: color-mix(
		in srgb,
		var(--cc-banner-type-background) 22%,
		var(--color-main-background)
	) !important;
}

.cc-banner-type-card[aria-pressed='true'] {
	outline: 2px solid var(--cc-banner-type-accent);
	outline-offset: -3px;
}

.cc-banner-type-heading {
	display: flex;
	gap: 7px;
	align-items: center;
	font-weight: 600;
	font-size: 12px;
}

.cc-banner-type-heading .material-design-icon {
	flex-shrink: 0;
	color: var(--cc-banner-type-accent);
}

.cc-banner-custom-fields {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 16px;
	align-items: start;
}

.cc-banner-custom-fields > * {
	min-width: 0;
}

.cc-banner-color-picker {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.cc-banner-color-trigger {
	max-width: 100%;
	min-height: 40px !important;
}

.cc-banner-color-trigger :deep(.button-vue__text) {
	display: flex;
	gap: 9px;
	align-items: center;
	white-space: normal;
}

.cc-banner-color-swatch {
	display: inline-block;
	flex: none;
	width: 24px;
	height: 24px;
	border: 1px solid var(--color-border-dark, var(--color-border));
	border-radius: 5px;
}

.cc-banner-icon-picker {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.cc-banner-icon-picker label {
	font-size: 13px;
	font-weight: 650;
}

@media (max-width: 640px) {
	.cc-banner-custom-fields {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 960px) {
	.cc-banner-type-options {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

.cc-format-summary {
	align-self: center;
}

.cc-format-summary strong {
	font-size: 13px;
	font-weight: 650;
}

.cc-progressive-details {
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-radius: 10px;
	background: var(--color-main-background);
	min-width: 0;
}

.cc-progressive-details > summary {
	cursor: pointer;
	font-size: 13px;
	font-weight: 600;
}

.cc-progressive-details[open] > summary {
	margin-bottom: 13px;
}

.cc-progressive-details .cc-native-pair {
	margin-top: 12px;
}

@media (max-width: 640px) {
	.cc-format-options,
	.cc-schedule-inline .cc-native-pair {
		grid-template-columns: 1fr;
	}
}

.cc-hub-admin .cc-native-page {
	padding-top: 24px;
}

.cc-hub-admin .cc-native-heading h1 {
	font-size: 23px;
}

.cc-hub-admin .cc-native-app,
.cc-hub-board .cc-board-app {
	min-height: 0;
	overflow: visible;
}

.cc-hub-admin .cc-native-page {
	padding-top: 20px;
}

.cc-hub-admin .cc-native-heading-overview {
	margin-bottom: 15px;
}

.cc-hub-admin .cc-native-heading-overview h2 {
	font-size: 18px;
	margin: 0;
}

.cc-hub-admin .cc-native-heading-overview p {
	font-size: 13px;
	margin-top: 6px;
}

.cc-hub-admin .cc-native-stats {
	display: flex;
	flex-wrap: wrap;
	gap: 9px;
	margin-bottom: 21px;
}

.cc-hub-admin .cc-native-stat {
	flex: 1 1 135px;
	min-width: 112px;
	padding: 11px 14px;
	gap: 4px;
}

.cc-hub-admin .cc-native-stat strong {
	font-size: 22px;
}

.cc-hub-admin .cc-native-stat span {
	font-size: 12px;
}

.cc-hub-admin .cc-editor-settings {
	margin-top: 22px;
}

.cc-hub-admin .cc-editor-delegation {
	max-width: 720px;
}

@media (max-width: 640px) {
	.cc-hub-admin .cc-native-stat {
		flex-basis: 125px;
	}
}

.cc-board-preview-dialog {
	min-width: 0;
	max-height: min(60vh, 540px);
	overflow-y: auto;
	overflow-wrap: anywhere;
	padding: 18px 24px 16px;
}

.cc-board-preview-dialog :deep(.rich-text--wrapper) {
	min-width: 0;
	line-height: 1.55;
}

.cc-board-preview-dialog :deep(.rich-text--wrapper) :is(img, video) {
	max-width: 100%;
	height: auto;
}

.cc-board-preview-dialog :deep(.rich-text--wrapper) > :first-child {
	margin-block-start: 0;
}

.cc-board-preview-dialog :deep(.rich-text--wrapper) > :last-child {
	margin-block-end: 0;
}

@media (max-width: 600px) {
	.cc-board-preview-dialog {
		padding: 16px 16px 14px;
	}
}

.cc-permanent-delete-confirm {
	padding: 18px 24px;
	display: grid;
	gap: 12px;
}

.cc-permanent-delete-confirm p {
	margin: 0;
	line-height: 1.5;
}

.cc-unified-channel-group .cc-native-hint {
	font-size: 12px;
	margin: 0;
	line-height: 1.45;
}

.cc-review-delivery-line {
	font-size: 14px;
	font-weight: 650;
	line-height: 1.45;
	margin: 0;
	overflow-wrap: anywhere;
}

.cc-gateway-provider-details {
	margin-top: 12px;
	border: 1px solid var(--color-border);
	border-radius: 10px;
	padding: 9px 13px;
	background: var(--color-main-background);
}

.cc-gateway-provider-details > summary {
	display: flex;
	gap: 12px;
	justify-content: space-between;
	align-items: center;
	cursor: pointer;
	list-style: revert;
	font-size: 13px;
}

.cc-gateway-provider-details > summary > span {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	text-align: end;
}

.cc-gateway-provider-details[open] > p.cc-native-hint {
	margin: 12px 0 4px;
}

.cc-gateway-provider-details .cc-delivery-channel:last-child {
	border-bottom: 0;
}

.cc-delivery-section .cc-delivery-subheading {
	font-size: 14px;
	font-weight: 650;
	margin: 12px 0 9px;
}

.cc-delivery-section > .cc-native-hint {
	max-width: 790px;
	line-height: 1.5;
}

.cc-delivery-empty-warning {
	margin: 12px 0;
}

.cc-wizard-preview[data-presentation='none'] {
	border: 1px solid var(--color-border);
}

@media (min-width: 901px) {
	.cc-delivery-section .cc-unified-channels {
		grid-template-columns: minmax(0, 1fr);
		gap: 13px;
	}
}

@media (min-width: 901px) {
	.cc-delivery-section .cc-unified-channel-group + .cc-unified-channel-group {
		border-inline-start: 0;
		border-top: 1px solid var(--color-border);
		padding-inline-start: 0;
		padding-top: 14px;
	}
}

@media (min-width: 901px) {
	.cc-delivery-section .cc-delivery-channel-list {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		column-gap: 24px;
	}
}

@media (min-width: 901px) {
	.cc-delivery-section .cc-gateway-provider-details {
		grid-column: 1/-1;
	}
}

.cc-delivery-section .cc-delivery-selector {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 0 22px;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-background-hover);
	padding: 10px 17px 15px;
}

.cc-delivery-section .cc-delivery-selector > .cc-delivery-channel {
	padding: 13px 8px 12px;
	border-bottom: 1px solid var(--color-border);
}

.cc-delivery-section
	.cc-delivery-selector
	> .cc-delivery-channel[data-selected='true'] {
	background: color-mix(in srgb, var(--color-primary-element) 6%, transparent);
	border-radius: 8px;
}

.cc-delivery-section .cc-delivery-selector .cc-gateway-provider-details {
	grid-column: 1/-1;
	margin: 12px 0 0;
}

.cc-delivery-section
	.cc-delivery-selector
	.cc-gateway-provider-details
	.cc-delivery-channel {
	margin-inline: 6px;
}

.cc-delivery-selection-feedback {
	display: flex;
	flex-wrap: wrap;
	gap: 6px 12px;
	align-items: baseline;
	font-size: 12px;
	padding: 11px 13px;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-main-background);
}

.cc-delivery-selection-feedback strong {
	font-weight: 650;
	white-space: nowrap;
}

.cc-delivery-selection-feedback span {
	color: var(--color-text-maxcontrast);
	overflow-wrap: anywhere;
}

.cc-delivery-comments {
	margin: 2px 0 0 6px;
}

@media (max-width: 700px) {
	.cc-delivery-section .cc-delivery-selector {
		grid-template-columns: minmax(0, 1fr);
		padding-inline: 13px;
	}
}

@media (max-width: 700px) {
	.cc-delivery-section .cc-delivery-selector .cc-gateway-provider-details {
		grid-column: 1;
	}
}
</style>
