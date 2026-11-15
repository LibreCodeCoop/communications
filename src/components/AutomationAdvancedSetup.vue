<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { mdiOpenInNew } from '@mdi/js'
import type { NativeSource } from '../types/communications'

const props = defineProps<{
	modelValue: string | null
	sources: NativeSource[]
	campaignId: number | null
	webhookExample: string
}>()
const emit = defineEmits<{
	'update:modelValue': [source: string | null]
}>()

// TRANSLATORS Heading for integrating a publication with Nextcloud Flow or a webhook.
const integrationHeading = t('communications', 'Integration or Flow')
// TRANSLATORS Label for choosing an integration event provider.
const eventSourceLabel = t('communications', 'Event source *')
// TRANSLATORS Placeholder for the integration event provider selector.
const chooseIntegrationLabel = t('communications', 'Choose an integration...')
// TRANSLATORS Instruction when the publication must be saved to obtain its numeric identifier.
const saveCampaignFirstLabel = t(
	'communications',
	'Save the campaign first to get its identifier and configure the integration.',
)
// TRANSLATORS Instruction to select a publication inside Nextcloud Flow.
const configureFlowLabel = t(
	'communications',
	"Select this campaign by name in Flow's “Trigger contextual campaign” operation.",
)
// TRANSLATORS Instruction to save a publication before using it in Nextcloud Flow.
const saveBeforeFlowLabel = t(
	'communications',
	'Save the campaign before linking it to the Flow rule.',
)
// TRANSLATORS Button opening the Nextcloud Flow configuration page.
const openFlowLabel = t('communications', 'Open Nextcloud Flow')
// TRANSLATORS Prefix for a fallback integration description mentioning the specified user ID.
const flowDescriptionStart = t(
	'communications',
	'Flow evaluates internal conditions. An HMAC webhook records an event only for the user specified in',
)
// TRANSLATORS Suffix for a fallback integration description explaining that a user-specific event is not broadcast.
const flowDescriptionEnd = t('communications', '; it never broadcasts to all users.')

const source = computed(
	() => props.sources.find((entry) => entry.id === props.modelValue) || null,
)
const selected = computed({
	get: () => props.modelValue,
	set: (value: string | null) => emit('update:modelValue', value),
})

const selectSource = (option: NativeSource): string => option.id
const flowUrl = generateUrl('/settings/admin/workflow')
// TRANSLATORS Prefix before the numeric ID of the saved publication.
const useIdentifierLabel = t('communications', 'Use the identifier')
// TRANSLATORS Label before the literal webhook field named campaignId.
const inFieldLabel = t('communications', 'in the field')
// TRANSLATORS Suffix explaining that an integration does not broadcast to a group.
const noGroupBroadcastLabel = t(
	'communications',
	'. The integration does not send the campaign to an entire group.',
)
</script>

<template>
	<div class="cc-automation-configuration">
		<h3>{{ integrationHeading }}</h3>
		<div class="cc-automation-field">
			<label for="cc-advanced">{{ eventSourceLabel }}</label>
			<NcSelect
				v-model="selected"
				:options="sources"
				:reduce="selectSource"
				:clearable="true"
				input-id="cc-advanced"
				label="label"
				:placeholder="chooseIntegrationLabel" />
		</div>
		<NcNoteCard v-if="source" type="info">
			<p>{{ source.setup }}</p>
			<div v-if="source.id === 'webhook'" class="cc-automation-webhook">
				<p v-if="!campaignId">{{ saveCampaignFirstLabel }}</p>
				<p v-else>
					{{ useIdentifierLabel }} {{ campaignId }} {{ inFieldLabel }}
					<code>campaignId</code>{{ noGroupBroadcastLabel }}
				</p>
				<pre>{{ webhookExample }}</pre>
			</div>
			<div v-if="source.id === 'flow'" class="cc-automation-flow">
				<p>{{ campaignId ? configureFlowLabel : saveBeforeFlowLabel }}</p>
				<NcButton :text="openFlowLabel" :href="flowUrl" target="_blank">
					<template #icon>
						<NcIconSvgWrapper :path="mdiOpenInNew" :size="18" />
					</template>
				</NcButton>
			</div>
		</NcNoteCard>
		<NcNoteCard v-else type="info">
			{{ flowDescriptionStart }} <code>uid</code>{{ flowDescriptionEnd }}
		</NcNoteCard>
	</div>
</template>

<style lang="scss" scoped>
.cc-automation-configuration,
.cc-automation-field,
.cc-automation-webhook {
	display: flex;
	flex-direction: column;
	gap: 14px;
	min-width: 0;
}

.cc-automation-configuration {
	max-width: 760px;
}

.cc-automation-configuration h3 {
	font-size: 15px;
	font-weight: 650;
	margin: 0;
}

.cc-automation-field label {
	font-size: 13px;
	font-weight: 650;
}

.cc-automation-field :deep(.v-select) {
	width: 100%;
	max-width: none;
}

.cc-automation-webhook {
	gap: 8px;
	margin-top: 12px;
}

.cc-automation-webhook p,
.cc-automation-flow p {
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

.cc-automation-flow {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: center;
	padding-top: 8px;
}

.cc-automation-flow p {
	width: 100%;
}
</style>
