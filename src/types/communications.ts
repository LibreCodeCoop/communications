/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** Public presentation fields returned by the Nextcloud backend. */
export interface PublicationPresentation {
	id?: number
	campaignId?: number
	title?: string
	headline?: string
	message?: string
	category?: string
	theme?: string
	presentation?: 'banner' | 'modal' | 'none' | 'board'
	ctaLabel?: string
	ctaUrl?: string
	cta_label?: string
	cta_url?: string
	banner_color?: string
	banner_icon?: string
	text_color?: string
	text_align?: 'left' | 'center' | 'right'
	dismissible?: boolean
}

export interface DeliveryChannel {
	id: string
	label: string
	status: string
	help: string
	description: string
	selected: boolean
	selectable: boolean
	availability: string
}

export interface FlowCampaign {
	id: number
	name: string
	status: string
	trigger_key: string
}

export interface FlowCampaignOption {
	value: string
	label: string
}

/** One claimed publication occurrence with a short-lived claim token. */
export interface ClaimedPresentation extends PublicationPresentation {
	deliveryId: number
	claimToken: string
	presentation: 'banner' | 'modal'
}

export interface ClaimBatchResponse {
	campaign: ClaimedPresentation | null
	campaigns: ClaimedPresentation[]
}

export interface NativeAction {
	key: string
	label: string
	description: string
	eventClass?: string
}
export interface NativeApplication {
	id: string
	label: string
	description: string
	keywords?: string[]
	actions: NativeAction[]
	icon?: string
	canEvents: boolean
	canPages: boolean
}
export interface NativeSource {
	setup?: string
	id: string
	label: string
	intent: string
	actions: NativeAction[]
}
export type TriggerIntent = 'board' | 'schedule' | 'app' | 'advanced'
export type TriggerMode = 'visit' | 'event'
export interface NativeIntent {
	id: TriggerIntent
	label: string
	description: string
}
export interface PageRouteOption {
	value: string
	label: string
	description: string
}
export interface PageRouteRule {
	operator: string
	value: string
}
export type PageRouteFilters = Record<string, PageRouteRule>
export interface AutomationCatalog {
	sources?: NativeSource[]
	applications?: NativeApplication[]
	intents?: NativeIntent[]
	placements?: PageRouteOption[]
}

export interface InboxAnnouncement {
	id: number
	headline: string
	message: string
	author: string
	category: string
	source: string
	publishedAt: number
	endsAt?: number | null
	readAt?: number | null
	commentCount: number
	commentsEnabled: boolean
	textAlign?: 'left' | 'center' | 'right'
	ctaLabel?: string
	ctaUrl?: string
	inInbox?: boolean
}
export interface InboxComment {
	id: number
	author: string
	message: string
	createdAt: number
	canDelete: boolean
}
export interface MentionOption {
	id: string
	displayName?: string
}
export interface GatewayPreferenceChannel {
	id: string
	label: string
	installed: boolean
	available: boolean
	enabled: boolean
	destination: string
	sponsored: boolean
}

export type ChannelNoticeField = 'notify_users' | 'notify_activity' | 'notify_email'
export type TranslatedPublicationField = 'headline' | 'message' | 'cta_label'
export type PublicationTranslations = Record<
	string,
	Partial<Record<TranslatedPublicationField, string>>
>

/** Editor state: dates are Date instances, unlike the serialized HTTP contract. */
export interface CampaignForm {
	name: string
	description: string
	headline: string
	message: string
	cta_label: string
	cta_url: string
	trigger_key: string
	page_route: string
	page_filters: PageRouteFilters
	audience_type: string
	audience_group: string
	audience_groups: string[]
	audience_users: string[]
	presentation: 'banner' | 'modal' | 'none' | 'board'
	status: string
	priority: number
	placement: string
	app_target_mode: string
	app_target_apps: string[]
	frequency: string
	min_interval_minutes: number
	max_total: number
	max_per_day: number
	category: string
	theme: string
	banner_color: string
	banner_icon: string
	board_visible: boolean
	guest_visible: boolean
	comments_enabled: boolean
	dismissible: boolean
	notify_users: boolean
	notify_activity: boolean
	notify_email: boolean
	gateway_channels: string[]
	text_align: 'left' | 'center' | 'right'
	text_color: string
	manual_order: number
	translated_content: PublicationTranslations
	group_match: string
	excluded_match: string
	excluded_groups: string[]
	starts_at: Date | null
	ends_at: Date | null
}

export interface CampaignRecord extends Omit<CampaignForm, 'starts_at' | 'ends_at'> {
	id: number
	created_at: number
	updated_at: number
	published_at: number
	starts_at: number | null
	ends_at: number | null
}

export interface GroupSelection {
	id: string
	displayName: string
	isNoUser?: boolean
}
export interface UserSelection {
	id: string
	displayName: string
	user?: string
}
export interface CampaignTrigger {
	key: string
	label: string
}
export interface CampaignGroup {
	id: string
	name: string
}
export interface CatalogDeliveryChannel {
	installed?: boolean
	id: string
	ready: boolean
	availability: string
	status: string
	help: string
}
export interface CampaignCatalog {
	triggers: CampaignTrigger[]
	groups: CampaignGroup[]
	automation: AutomationCatalog
	deliveryChannels: CatalogDeliveryChannel[]
	editorGroups?: string[]
	canConfigureEditors?: boolean
}
export interface CampaignMetrics {
	estimatedImpressions: number
	dismissals: number
	clicks: number
	queuedOrClaimed: number
}
export interface CampaignApiPayload {
	campaign?: CampaignRecord
	campaigns?: CampaignRecord[]
	metrics?: CampaignMetrics
	groups?: string[]
	users?: Array<{ id: string; name: string }>
	error?: string
	message?: string
	triggers?: CampaignTrigger[]
	automation?: AutomationCatalog
	editorGroups?: string[]
	deliveryChannels?: CatalogDeliveryChannel[]
	canConfigureEditors?: boolean
}
