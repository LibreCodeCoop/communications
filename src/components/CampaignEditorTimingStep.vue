<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { useCampaignEditorContext } from '../composables/campaignEditorContext'
import AutomationBuilder from './AutomationBuilder.vue'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
const { catalog, editorId, onIntentChange, form } = useCampaignEditorContext()
</script>
<template>
	<div class="cc-wizard-step-content">
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
					:label="t('communications', 'End (optional)')"
					type="datetime-local"
					clearable />
			</div>
		</section>
	</div>
</template>
