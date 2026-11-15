<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { useCampaignEditorContext } from '../composables/campaignEditorContext'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import MarkdownEditor from './MarkdownEditor.vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
const {
	selectable,
	form,
	languageOptions,
	translationLocale,
	translatedHeadline,
	translatedMessage,
	translatedCta,
	contentFormattingHelp,
	adminContentLabel,
	adminTranslationLanguageLabel,
	adminWriteYourMessageLabel,
	adminDisplayedMessageLabel,
} = useCampaignEditorContext()
</script>
<template>
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
			:open="Boolean(form.cta_label || form.cta_url) || undefined">
			<summary>
				<!-- TRANSLATORS Action or instruction in the publications management screen. -->
				{{ t('communications', 'Add an action button (optional)') }}
			</summary>
			<div class="cc-native-pair">
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				<NcTextField
					v-model="form.cta_label"
					:label="t('communications', 'Button text')"
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
		<details class="cc-progressive-details cc-translation-options">
			<summary>
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				{{ t('communications', 'Message translations (optional)') }}
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
				:label="t('communications', 'Translated title')"
				:maxlength="180" />
			<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
			<NcTextArea
				v-model="translatedMessage"
				:label="t('communications', 'Translated message')"
				:maxlength="4000" />
			<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
			<NcTextField
				v-model="translatedCta"
				:label="t('communications', 'Translated button')"
				:maxlength="80" />
		</details>
	</section>
</template>
