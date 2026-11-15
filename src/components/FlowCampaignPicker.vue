<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { requestApi } from '../services/api'

import { ref, computed, onMounted, watch } from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import type { FlowCampaign, FlowCampaignOption } from '../types/communications'

const props = withDefaults(defineProps<{ modelValue?: string }>(), {
	modelValue: '',
})
const emit = defineEmits<{ 'update:model-value': [value: string] }>()
const campaigns = ref<FlowCampaign[]>([])
const loading = ref(true)
const loadError = ref('')
const selected = computed({
	get: () => String(props.modelValue || ''),
	set: (value: string) => emit('update:model-value', String(value || '')),
})
const options = computed(() =>
	campaigns.value.map((c) => ({
		value: String(c.id),
		label: c.name + (c.status === 'active' ? '' : ' (' + c.status + ')'),
	})),
)
const reduce = (option: FlowCampaignOption) => option.value
onMounted(async () => {
	try {
		const data = await requestApi<{ campaigns?: FlowCampaign[] }>(
			'/api/campaigns',
		)
		campaigns.value = (data.campaigns || []).filter(
			(c) =>
				c.trigger_key === 'nextcloud.flow.matched'
				&& c.status !== 'archived',
		)
	} catch (err) {
		loadError.value =
			// TRANSLATORS Prefix followed by a dynamic value in the Flow campaign selector; preserve its trailing space.
			t('communications', 'Could not load campaigns: ')
			+ (err instanceof Error ? err.message : String(err))
	} finally {
		loading.value = false
	}
})

// TRANSLATORS Short label for an option or field in the Flow campaign selector.
const flowCampaignToDisplayLabel = t('communications', 'Campaign to display')
// TRANSLATORS Progress indicator shown in the Flow campaign selector.
const flowLoadingCampaignsLabel = t('communications', 'Loading campaigns…')
</script>

<template>
	<div class="cc-flow-picker" data-testid="flow-campaign-picker">
		<label class="cc-flow-picker-label" for="cc-flow-picker-select">{{
			flowCampaignToDisplayLabel
		}}</label>
		<p class="cc-flow-picker-hint">
			<!-- TRANSLATORS Action or instruction in the Flow campaign selector. -->
			{{
				t(
					'communications',
					'Select a campaign configured with the “Flow rule” trigger.',
				)
			}}
		</p>
		<p v-if="loading">
			{{ flowLoadingCampaignsLabel }}
		</p>
		<NcNoteCard v-else-if="loadError" type="error">{{ loadError }}</NcNoteCard>
		<NcNoteCard v-else-if="!options.length" type="info">
			<!-- TRANSLATORS Empty state or unavailable-condition message in the Flow campaign selector. -->
			{{
				t(
					'communications',
					'No Flow campaigns found. Create one in Campaigns and select “Advanced rule or integration”.',
				)
			}}
		</NcNoteCard>
		<!-- TRANSLATORS Action or instruction in the Flow campaign selector. -->
		<NcSelect
			v-else
			v-model="selected"
			:reduce="reduce"
			:options="options"
			input-id="cc-flow-picker-select"
			:clearable="false"
			:placeholder="t('communications', 'Choose a campaign')" />
	</div>
</template>

<style lang="scss" scoped>
.cc-flow-picker {
	width: 100%;
	min-width: 220px;
	max-width: 550px;
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: 6px;
}

.cc-flow-picker-label {
	font-size: 13px;
	font-weight: 600;
}

.cc-flow-picker-hint {
	margin: 0 0 8px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}
</style>
