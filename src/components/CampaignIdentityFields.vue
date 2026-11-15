<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { useCampaignEditorContext } from '../composables/campaignEditorContext'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
const {
	form,
	adminIdentificationLabel,
	adminExampleWelcomeNoticeLabel,
	adminInternalNameLabel,
} = useCampaignEditorContext()
</script>
<template>
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
			:label="t('communications', 'Description for your team')"
			:maxlength="700" />
	</section>
</template>
