<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t, getLanguage } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import { effectiveCampaignStatus, selectCampaigns } from '../services/campaignList'
import type { CampaignRecord, CampaignCatalog } from '../types/communications'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiEyeOutline,
	mdiContentCopy,
	mdiArchiveOutline,
	mdiPlayCircleOutline,
	mdiPauseCircleOutline,
	mdiBackupRestore,
	mdiChartBar,
	mdiArrowUp,
	mdiArrowDown,
} from '@mdi/js'

const props = defineProps<{
	campaigns: CampaignRecord[]
	catalog: CampaignCatalog
	loading: boolean
	orderSaving: boolean
	editorConfigLoading: boolean
	groupOptions: { label: string; value: string }[]
	triggerOptions: { label: string; value: string }[]
}>()
const editorGroupValues = defineModel<string[]>('editorGroupValues', {
	required: true,
})
const emit = defineEmits<{
	edit: [CampaignRecord]
	preview: [CampaignRecord]
	metrics: [CampaignRecord]
	move: [CampaignRecord, string, (number | null)?]
	'status-change': [CampaignRecord, string]
	duplicate: [CampaignRecord]
	delete: [CampaignRecord]
	archive: [CampaignRecord]
	'save-editor-groups': []
}>()
const selectable = (option: { value: string }) => option.value
const rowMenuOpen = ref<Record<number, boolean>>({})
const draggingCampaignId = ref<number | null>(null)
function previewCampaign(campaign: CampaignRecord): void {
	rowMenuOpen.value = {}
	emit('preview', campaign)
}

const statuses = [
	// TRANSLATORS Filter option showing publications regardless of lifecycle status.
	{ label: t('communications', 'All statuses'), value: 'all' },
	// TRANSLATORS State of a publication that can currently be displayed or delivered.
	{ label: t('communications', 'Active'), value: 'active' },
	// TRANSLATORS State of a publication whose start date is in the future.
	{ label: t('communications', 'Scheduled'), value: 'scheduled' },
	// TRANSLATORS State of a publication whose availability window has expired.
	{ label: t('communications', 'Ended'), value: 'ended' },
	// TRANSLATORS List filter for publications that have not been activated.
	{ label: t('communications', 'Drafts'), value: 'draft' },
	// TRANSLATORS Publication temporarily disabled by an editor.
	{ label: t('communications', 'Paused'), value: 'paused' },
	// TRANSLATORS Publication removed from the active management list.
	{ label: t('communications', 'Archived'), value: 'archived' },
]

const search = ref('')
const filterStatus = ref('all')

const page = ref(1)
const PAGE_SIZE = 10

function effectiveStatus(c: CampaignRecord): string {
	return effectiveCampaignStatus(c, Math.floor(Date.now() / 1000))
}
const labels: Record<string, string> = {
	// TRANSLATORS State of a publication that can currently be displayed or delivered.
	active: t('communications', 'Active'),
	// TRANSLATORS Publication that has not yet been activated.
	draft: t('communications', 'Draft'),
	// TRANSLATORS Publication temporarily disabled by an editor.
	paused: t('communications', 'Paused'),
	// TRANSLATORS Publication removed from the active management list.
	archived: t('communications', 'Archived'),
	// TRANSLATORS State of a publication whose start date is in the future.
	scheduled: t('communications', 'Scheduled'),
	// TRANSLATORS State of a publication whose availability window has expired.
	ended: t('communications', 'Ended'),
}
const currentCampaigns = computed(() =>
	props.campaigns.filter((c) => c.status !== 'archived'),
)
const stats = computed(() => [
	{
		// TRANSLATORS Short label for an option or field in the publications management screen.
		label: t('communications', 'Registered publications'),
		count: currentCampaigns.value.length,
	},
	{
		// TRANSLATORS Short label for an option or field in the publications management screen.
		label: t('communications', 'Active now'),
		count: currentCampaigns.value.filter((c) => effectiveStatus(c) === 'active')
			.length,
	},
	{
		// TRANSLATORS State of a publication whose start date is in the future.
		label: t('communications', 'Scheduled'),
		count: currentCampaigns.value.filter(
			(c) => effectiveStatus(c) === 'scheduled',
		).length,
	},
	{
		// TRANSLATORS List filter for publications that have not been activated.
		label: t('communications', 'Drafts'),
		count: currentCampaigns.value.filter((c) => effectiveStatus(c) === 'draft')
			.length,
	},
	{
		// TRANSLATORS Publication temporarily disabled by an editor.
		label: t('communications', 'Paused'),
		count: currentCampaigns.value.filter((c) => effectiveStatus(c) === 'paused')
			.length,
	},
])
const visible = computed(() =>
	selectCampaigns(
		props.campaigns,
		filterStatus.value,
		search.value,
		Math.floor(Date.now() / 1000),
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

function dateDisplay(ts: number | null): string {
	return ts === null
		? ''
		: new Date(ts * 1000).toLocaleDateString(getLanguage().replace('_', '-'))
}
function period(c: CampaignRecord): string {
	// TRANSLATORS Publication with neither a start nor an end date restriction.
	if (!c.starts_at && !c.ends_at) return t('communications', 'No deadline')
	if (c.starts_at && c.ends_at)
		return (
			// TRANSLATORS Prefix of a date range; preserve the trailing space.
			t('communications', 'From ')
			+ dateDisplay(c.starts_at)
			// TRANSLATORS Separator within a date range; preserve the surrounding spaces.
			+ t('communications', ' until ')
			+ dateDisplay(c.ends_at)
		)
	return c.starts_at
		? // TRANSLATORS Prefix followed by a dynamic value in the publications management screen; preserve its trailing space.
			t('communications', 'Since ') + dateDisplay(c.starts_at)
		: // TRANSLATORS Prefix followed by a dynamic value in the publications management screen; preserve its trailing space.
			t('communications', 'Until ') + dateDisplay(c.ends_at)
}
function groupLabel(c: CampaignRecord): string {
	if (c.audience_type === 'group') {
		const ids = c.audience_groups
		return (
			ids
				.map(
					(id) =>
						props.groupOptions.find((g) => g.value === id)?.label || id,
				)
				// TRANSLATORS No audience group has been selected for the publication.
				.join(', ') || t('communications', 'No group')
		)
	}
	if (c.audience_type === 'users') {
		const users = c.audience_users || []
		return users.length === 1
			? // TRANSLATORS Prefix before the identifier of a single selected recipient.
				t('communications', 'User: ') + users[0]
			: // TRANSLATORS Suffix after a count of individually selected recipient accounts.
				users.length + t('communications', ' specific users')
	}
	return c.audience_type === 'admins'
		? // TRANSLATORS Audience scope restricted to Nextcloud administrators.
			t('communications', 'Administrators only')
		: // TRANSLATORS Audience scope including every eligible user.
			t('communications', 'All users')
}
function triggerLabel(c: CampaignRecord): string {
	return (
		props.triggerOptions.find((t) => t.value === c.trigger_key)?.label
		|| c.trigger_key
	)
}

function campaignActionsLabel(name: string): string {
	// TRANSLATORS {name} is the publication name shown in the actions menu.
	return t('communications', 'Actions for {name}', { name })
}

function startReorderDrag(event: DragEvent, c: CampaignRecord) {
	if (c.status === 'archived' || props.orderSaving) return event.preventDefault()
	draggingCampaignId.value = c.id
	if (event.dataTransfer) {
		event.dataTransfer.effectAllowed = 'move'
		event.dataTransfer.setData('text/plain', String(c.id))
	}
}
function endReorderDrag() {
	draggingCampaignId.value = null
}
function dropReorder(_event: DragEvent, target: CampaignRecord) {
	const from = draggingCampaignId.value
	draggingCampaignId.value = null
	if (from == null || from === target.id || target.status === 'archived') return
	const moving = props.campaigns.find((c) => c.id === from)
	if (!moving) return
	emit('move', moving, 'before', target.id)
}

// TRANSLATORS Heading for the publications management overview.
const adminOverviewLabel = t('communications', 'Overview')
// TRANSLATORS Short label for an option or field in the publications management screen.
const adminAllPublicationsLabel = t('communications', 'All publications')
// TRANSLATORS Column heading for the publication name.
const adminPublicationLabel = t('communications', 'Publication')
// TRANSLATORS Column heading for the event or schedule that activates publication.
const adminTriggerLabel = t('communications', 'Trigger')
// TRANSLATORS Column heading for intended recipients.
const adminAudienceLabel = t('communications', 'Audience')
// TRANSLATORS Column heading for publication availability.
const adminPeriodLabel = t('communications', 'Period')
// TRANSLATORS Short label for an option or field in the publications management screen.
const adminGroupsWithEditorialPermissionsLabel = t(
	'communications',
	'Groups with editorial permissions',
)

// TRANSLATORS Empty-state instruction for the publication management list.
const adminCreateAPublicationOrChangeLabel = t(
	'communications',
	'Create a publication or change the filters.',
)
// TRANSLATORS Empty-state title when no publications match filters.
const adminNoPublicationsFoundLabel = t('communications', 'No publications found')

// TRANSLATORS Action to pause an active publication.
const adminPauseActionLabel = t('communications', 'Pause')
// TRANSLATORS Action to activate a publication.
const adminActivateActionLabel = t('communications', 'Activate')
</script>

<template>
	<div class="cc-native-overview">
		<div class="cc-native-heading cc-native-heading-overview">
			<div>
				<h2>
					{{ adminOverviewLabel }}
				</h2>
				<p>
					<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
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
			<section v-for="stat in stats" :key="stat.label" class="cc-native-stat">
				<span>{{ stat.label }}</span>
				<strong>{{ stat.count }}</strong>
			</section>
		</div>

		<section class="cc-native-panel">
			<div class="cc-native-list-head">
				<div>
					<h2>
						{{ adminAllPublicationsLabel }}
					</h2>
					<p>
						<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
						{{
							t(
								'communications',
								'Manage persistent and contextual notices in one place. Reorder by dragging or using keyboard controls.',
							)
						}}
					</p>
				</div>
				<div class="cc-native-filters">
					<!-- TRANSLATORS Action or instruction in the publications management screen. -->
					<NcTextField
						v-model="search"
						:label="t('communications', 'Search publications')"
						type="search" />
					<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
					<NcSelect
						v-model="filterStatus"
						:options="statuses"
						:reduce="selectable"
						:clearable="false"
						input-id="cc-status-filter"
						:aria-label="t('communications', 'Filter by status')" />
				</div>
			</div>

			<NcEmptyContent
				v-if="!loading && !visible.length"
				:name="adminNoPublicationsFoundLabel"
				:description="adminCreateAPublicationOrChangeLabel" />
			<div v-else class="cc-native-table-scroll">
				<table class="cc-native-table">
					<thead>
						<tr>
							<th>
								{{ adminPublicationLabel }}
							</th>
							<th>
								{{ adminTriggerLabel }}
							</th>
							<th>
								{{ adminAudienceLabel }}
							</th>
							<th>
								{{ adminPeriodLabel }}
							</th>
							<!-- TRANSLATORS Column indicating the publication lifecycle state. -->
							<th>{{ t('communications', 'Status') }}</th>
							<!-- TRANSLATORS Column containing management actions for a publication. -->
							<th>{{ t('communications', 'Actions') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="campaign in pageVisible"
							:key="campaign.id"
							:draggable="
								campaign.status !== 'archived' && !orderSaving
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
									<!-- TRANSLATORS Open the publication editor for this item. -->
									<NcButton
										size="small"
										:text="t('communications', 'Edit')"
										@click="emit('edit', campaign)" />
									<NcActions
										:aria-label="
											campaignActionsLabel(campaign.name)
										"
										:open="rowMenuOpen[campaign.id] || false"
										@update:open="
											rowMenuOpen[campaign.id] = $event
										">
										<NcActionButton
											v-if="campaign.status !== 'archived'"
											:disabled="orderSaving"
											@click="emit('move', campaign, 'up')">
											<template #icon
												><NcIconSvgWrapper
													:path="mdiArrowUp"
													:size="20"
											/></template>
											<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
											{{ t('communications', 'Move up') }}
										</NcActionButton>
										<NcActionButton
											v-if="campaign.status !== 'archived'"
											:disabled="orderSaving"
											@click="emit('move', campaign, 'down')">
											<template #icon
												><NcIconSvgWrapper
													:path="mdiArrowDown"
													:size="20"
											/></template>
											<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
											{{ t('communications', 'Move down') }}
										</NcActionButton>
										<NcActionButton
											@click="previewCampaign(campaign)">
											<template #icon
												><NcIconSvgWrapper
													:path="mdiEyeOutline"
													:size="20"
											/></template>
											<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
											{{
												t(
													'communications',
													'Preview presentation',
												)
											}}
										</NcActionButton>
										<!-- TRANSLATORS Stop presenting or delivering an active publication. -->
										<NcActionButton
											v-if="campaign.status !== 'archived'"
											@click="
												emit(
													'status-change',
													campaign,
													campaign.status === 'active'
														? 'paused'
														: 'active',
												)
											">
											<template #icon
												><NcIconSvgWrapper
													:path="
														campaign.status === 'active'
															? mdiPauseCircleOutline
															: mdiPlayCircleOutline
													"
													:size="20"
											/></template>
											<!-- TRANSLATORS Action or instruction in the publications management screen. -->
											{{
												campaign.status === 'active'
													? adminPauseActionLabel
													: adminActivateActionLabel
											}}
										</NcActionButton>
										<!-- TRANSLATORS Restore an archived publication to the editable list. -->
										<NcActionButton
											v-else
											@click="
												emit(
													'status-change',
													campaign,
													'draft',
												)
											">
											<template #icon
												><NcIconSvgWrapper
													:path="mdiBackupRestore"
													:size="20"
											/></template>
											{{ t('communications', 'Restore') }}
										</NcActionButton>
										<NcActionButton
											@click="emit('metrics', campaign)">
											<template #icon
												><NcIconSvgWrapper
													:path="mdiChartBar"
													:size="20"
											/></template>
											<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
											{{ t('communications', 'Statistics') }}
										</NcActionButton>
										<NcActionButton
											@click="emit('duplicate', campaign)">
											<template #icon
												><NcIconSvgWrapper
													:path="mdiContentCopy"
													:size="20"
											/></template>
											<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
											{{ t('communications', 'Duplicate') }}
										</NcActionButton>
										<NcActionButton
											v-if="campaign.status === 'archived'"
											@click="emit('delete', campaign)">
											<!-- TRANSLATORS Action or instruction in the publications management screen. -->
											{{
												t(
													'communications',
													'Delete permanently',
												)
											}}
										</NcActionButton>
										<!-- TRANSLATORS Remove a publication from normal active management without deleting it. -->
										<NcActionButton
											v-if="campaign.status !== 'archived'"
											@click="emit('archive', campaign)">
											<template #icon
												><NcIconSvgWrapper
													:path="mdiArchiveOutline"
													:size="20"
											/></template>
											{{ t('communications', 'Archive') }}
										</NcActionButton>
									</NcActions>
								</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
			<nav
				v-if="pageCount > 1"
				class="cc-native-pagination"
				:aria-label="t('communications', 'Publication pages')">
				<!-- TRANSLATORS Separator between current and total page counts. -->
				<span
					>{{ t('communications', 'Showing') }}
					{{ (currentPage - 1) * PAGE_SIZE + 1 }}
					{{ t('communications', 'to') }}
					{{ Math.min(currentPage * PAGE_SIZE, visible.length) }}
					<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
					{{ t('communications', 'of') }}
					{{ visible.length }}</span
				>
				<!-- TRANSLATORS Navigate to the previous page of the publication list. -->
				<NcButton
					:text="t('communications', 'Previous')"
					:disabled="currentPage <= 1"
					@click="page = currentPage - 1" />
				<!-- TRANSLATORS Label preceding the current number in paginated publications. -->
				<span
					>{{ t('communications', 'Page') }} {{ currentPage }}
					<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
					{{ t('communications', 'of') }} {{ pageCount }}</span
				>
				<!-- TRANSLATORS Navigate to the next page of the publication list. -->
				<NcButton
					:text="t('communications', 'Next')"
					:disabled="currentPage >= pageCount"
					@click="page = currentPage + 1" />
			</nav>
		</section>
		<!-- TRANSLATORS Action or instruction in the publications management screen. -->
		<section
			class="cc-editor-settings"
			:aria-label="t('communications', 'Editorial settings')">
			<details
				v-if="catalog.canConfigureEditors"
				class="cc-progressive-details cc-editor-delegation">
				<summary>
					<!-- TRANSLATORS Action or instruction in the publications management screen. -->
					{{ t('communications', 'Editorial permissions') }}
				</summary>
				<p class="cc-native-hint">
					<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
					{{
						t(
							'communications',
							'Authorized groups can manage publications. Only administrators can change permissions.',
						)
					}}
				</p>
				<div class="cc-native-control">
					<label for="cc-editor-groups">{{
						adminGroupsWithEditorialPermissionsLabel
					}}</label>
					<!-- TRANSLATORS Action or instruction in the publications management screen. -->
					<NcSelect
						v-model="editorGroupValues"
						:options="groupOptions"
						:reduce="selectable"
						multiple
						:clearable="true"
						input-id="cc-editor-groups"
						:placeholder="t('communications', 'Select editor groups')" />
				</div>
				<!-- TRANSLATORS Action or instruction in the publications management screen. -->
				<NcButton
					:text="t('communications', 'Save delegation')"
					variant="primary"
					:disabled="editorConfigLoading"
					@click="emit('save-editor-groups')" />
			</details>
		</section>
		<p class="cc-native-caption">
			<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
			{{
				t(
					'communications',
					'Browser-reported impressions are estimates, not audited reading or billing metrics.',
				)
			}}
		</p>
	</div>
</template>

<style lang="scss" scoped>
.cc-native-overview {
	min-width: 0;
}

.cc-native-overview *,
.cc-native-overview *::before,
.cc-native-overview *::after {
	box-sizing: border-box;
}

.cc-native-overview p {
	line-height: 1.5;
}

.cc-native-overview {
	h1 {
		font-size: clamp(25px, 2.5vw, 32px);
		font-weight: 650;
		margin: 8px 0;
	}

	h2 {
		font-size: 18px;
		font-weight: 650;
		margin-bottom: 14px;
	}
}

.cc-native-overview p {
	line-height: 1.5;
}

.cc-native-heading {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 16px;
	margin-bottom: 24px;

	> div:first-child {
		flex: 1;
		min-width: 260px;
	}

	p {
		color: var(--color-text-maxcontrast);
		margin: 6px 0 0;
	}
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

	span {
		color: var(--color-text-maxcontrast);
		font-size: 12px;
	}

	strong {
		font-size: 26px;
		font-weight: 650;
		font-variant-numeric: tabular-nums;
	}
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

	h2 {
		margin: 0;
	}

	p {
		color: var(--color-text-maxcontrast);
		font-size: 13px;
		margin: 5px 0 0;
	}
}

.cc-native-filters {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 12px;

	> * {
		min-width: 175px;
		width: min(240px, 100%);
	}
}

.cc-native-table-scroll {
	overflow-x: auto;
	max-width: 100%;
}

.cc-native-table {
	width: 100%;
	min-width: 820px;
	border-collapse: collapse;

	thead {
		background: var(--color-background-hover);
		color: var(--color-text-maxcontrast);
	}
}

.cc-native-table th,
.cc-native-table td {
	padding: 12px 15px;
	text-align: start;
	border-bottom: 1px solid var(--color-border);
	font-size: 13px;
	vertical-align: middle;
}

.cc-native-table {
	th {
		font-weight: 650;
	}

	td:first-child {
		min-width: 195px;
	}
}

.cc-native-table {
	td strong {
		display: block;
		font-weight: 650;
	}

	td small {
		display: block;
		color: var(--color-text-maxcontrast);
		margin-top: 5px;
	}
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

	&[data-status='active'] {
		border-color: var(--color-success);
	}

	&[data-status='scheduled'] {
		border-color: var(--color-primary-element);
	}
}

.cc-native-caption {
	margin: 20px 0;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
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

.cc-native-hint {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	margin: 3px 0 0;
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

.cc-editor-settings {
	margin-top: 22px;
}

.cc-editor-delegation {
	max-width: 720px;
}
</style>
