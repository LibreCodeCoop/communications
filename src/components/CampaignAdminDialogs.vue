<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import type {
	CampaignForm,
	CampaignRecord,
	CampaignMetrics,
} from '../types/communications'
const props = defineProps<{
	form: CampaignForm
	previewCampaign: CampaignRecord | null
	metricsCampaign: CampaignRecord | null
	metricsLoading: boolean
	metricsData: CampaignMetrics | null
	deleteTarget: CampaignRecord | null
	deleting: boolean
	archiveConfirmationMessage: string
}>()
const previewOpen = defineModel<boolean>('previewOpen', { required: true })
const metricsOpen = defineModel<boolean>('metricsOpen', { required: true })
const confirmDeleteOpen = defineModel<boolean>('confirmDeleteOpen', {
	required: true,
})
const confirmArchiveOpen = defineModel<boolean>('confirmArchiveOpen', {
	required: true,
})
const deleteName = defineModel<string>('deleteName', { required: true })
const emit = defineEmits<{
	'close-preview': []
	'delete-permanently': []
	archive: []
}>()

const metricsDialogName = computed(() => {
	// TRANSLATORS {name} is the publication name in the statistics dialog title.
	return t('communications', 'Statistics for {name}', {
		name: props.metricsCampaign?.name || '',
	})
})
// TRANSLATORS Progress indicator shown in the publications management screen.
const adminLoadingLabel = t('communications', 'Loading…')
// TRANSLATORS Metrics label for browser-reported estimates of announcement impressions.
const adminEstimatedImpressionsLabel = t('communications', 'Estimated impressions')
// TRANSLATORS Metrics label counting clicks on publication actions.
const adminClicksLabel = t('communications', 'Clicks')
// TRANSLATORS Metrics label counting dismissals of a contextual publication.
const adminDismissalsLabel = t('communications', 'Dismissals')
// TRANSLATORS Metrics label counting queued or leased publication deliveries.
const adminQueuedOrReservedDeliveriesLabel = t(
	'communications',
	'Queued or reserved deliveries',
)
</script>

<template>
	<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
	<NcDialog
		v-if="previewOpen && (previewCampaign || form).presentation === 'none'"
		:open="previewOpen"
		:name="
			(previewCampaign || form).headline
			|| t('communications', 'Publication preview')
		"
		container="body"
		size="normal"
		@update:open="!$event && emit('close-preview')">
		<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
		<div
			class="cc-board-preview-dialog"
			role="document"
			:aria-label="t('communications', 'Publication content')">
			<p
				v-if="(previewCampaign || form).presentation === 'none'"
				class="cc-native-hint">
				<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
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
			<!-- TRANSLATORS Close the current preview or dialog. -->
			<NcButton
				variant="primary"
				:text="t('communications', 'Close')"
				@click="emit('close-preview')" />
		</template>
	</NcDialog>

	<NcDialog
		v-model:open="metricsOpen"
		:name="metricsDialogName"
		size="normal"
		container="body">
		<div class="cc-native-metrics">
			<p>
				<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
				{{
					t(
						'communications',
						'Aggregate statistics estimated from browser-reported events. Not audited for billing.',
					)
				}}
			</p>
			<p v-if="metricsLoading">
				{{ adminLoadingLabel }}
			</p>
			<div v-else-if="metricsData" class="cc-native-metrics-grid">
				<section>
					<strong>{{ metricsData.estimatedImpressions }}</strong
					><span>{{ adminEstimatedImpressionsLabel }}</span>
				</section>
				<section>
					<strong>{{ metricsData.clicks }}</strong
					><span>{{ adminClicksLabel }}</span>
				</section>
				<section>
					<strong>{{ metricsData.dismissals }}</strong
					><span>{{ adminDismissalsLabel }}</span>
				</section>
				<section>
					<strong>{{ metricsData.queuedOrClaimed }}</strong
					><span>{{ adminQueuedOrReservedDeliveriesLabel }}</span>
				</section>
			</div>
			<!-- TRANSLATORS Close the current preview or dialog. -->
			<NcButton
				:text="t('communications', 'Close')"
				@click="metricsOpen = false" />
		</div>
	</NcDialog>

	<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
	<NcDialog
		v-model:open="confirmDeleteOpen"
		:name="t('communications', 'Permanently delete publication')"
		size="small"
		container="body">
		<div class="cc-permanent-delete-confirm">
			<p>
				<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
				{{
					t(
						'communications',
						'Irreversible deletion: the publication, comments, deliveries, metrics, and read records will be removed.',
					)
				}}
			</p>
			<p>
				<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
				{{
					t('communications', 'To confirm, enter the exact internal name')
				}}
				<strong>{{ deleteTarget?.name }}</strong
				>.
			</p>
			<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
			<NcTextField
				v-model="deleteName"
				:label="t('communications', 'Internal name for confirmation')" />
		</div>
		<template #actions>
			<!-- TRANSLATORS Dismiss the current editor or confirmation dialog without saving. -->
			<NcButton
				:text="t('communications', 'Cancel')"
				@click="confirmDeleteOpen = false" />
			<!-- TRANSLATORS Action or instruction in the publications management screen. -->
			<NcButton
				variant="error"
				:text="t('communications', 'Delete permanently')"
				:disabled="deleting || deleteName !== deleteTarget?.name"
				@click="emit('delete-permanently')" />
		</template>
	</NcDialog>

	<!-- TRANSLATORS Action or instruction in the publications management screen. -->
	<NcDialog
		v-model:open="confirmArchiveOpen"
		:name="t('communications', 'Archive publication')"
		size="small"
		container="body">
		<p>
			{{ archiveConfirmationMessage }}
		</p>
		<template #actions>
			<!-- TRANSLATORS Dismiss the current editor or confirmation dialog without saving. -->
			<NcButton
				:text="t('communications', 'Cancel')"
				@click="confirmArchiveOpen = false" />
			<!-- TRANSLATORS Remove a publication from normal active management without deleting it. -->
			<NcButton
				variant="error"
				:text="t('communications', 'Archive')"
				@click="emit('archive')" />
		</template>
	</NcDialog>
</template>

<style lang="scss" scoped>
.cc-native-metrics {
	padding: 12px 18px 24px;

	> p {
		font-size: 13px;
		color: var(--color-text-maxcontrast);
	}
}

.cc-native-metrics-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
	margin: 16px 0 26px;

	section {
		display: flex;
		flex-direction: column;
		gap: 6px;
		border: 1px solid var(--color-border);
		border-radius: 10px;
		padding: 16px;
	}

	strong {
		font-size: 25px;
		font-variant-numeric: tabular-nums;
	}

	span {
		color: var(--color-text-maxcontrast);
		font-size: 12px;
	}
}

.cc-board-preview-dialog {
	min-width: 0;
	max-height: min(60vh, 540px);
	overflow-y: auto;
	overflow-wrap: anywhere;
	padding: 18px 24px 16px;

	:deep(.rich-text--wrapper) {
		min-width: 0;
		line-height: 1.55;
	}
}

.cc-board-preview-dialog :deep(.rich-text--wrapper) :is(img, video) {
	max-width: 100%;
	height: auto;
}

.cc-board-preview-dialog {
	:deep(.rich-text--wrapper) > :first-child {
		margin-block-start: 0;
	}

	:deep(.rich-text--wrapper) > :last-child {
		margin-block-end: 0;
	}
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

	p {
		margin: 0;
		line-height: 1.5;
	}
}

.cc-native-hint {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	margin: 3px 0 0;
}
</style>
