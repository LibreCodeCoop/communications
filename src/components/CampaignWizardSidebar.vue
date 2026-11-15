<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import { bannerAppearance } from '../bannerAppearance'
import type { CampaignForm } from '../types/communications'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiBullhornOutline,
	mdiLightningBoltOutline,
	mdiAccountGroupOutline,
	mdiMapMarkerOutline,
	mdiRepeat,
	mdiCalendarRange,
	mdiEyeOutline,
} from '@mdi/js'

const props = defineProps<{
	form: CampaignForm
	summaryWhen: string
	audienceReview: string
	locationReview: string
	deliveryReview: string
	frequencyReview: string
	scheduleReview: string
}>()
const emit = defineEmits<{ preview: [] }>()
const bannerPreviewAppearance = computed(() => bannerAppearance(props.form))

// TRANSLATORS Heading for the preview when a publication has no visual destination.
const adminMessagePreviewTitleLabel = t('communications', 'Message preview')
// TRANSLATORS Heading of the contextual publication preview.
const adminPreviewTitleLabel = t('communications', 'Preview')
// TRANSLATORS Placeholder title when the publication has not been named.
const adminPublicationTitleLabel = t('communications', 'Publication title')
// TRANSLATORS Action for previewing the content of a non-visual message.
const adminPreviewMessageLabel = t('communications', 'Preview message')
// TRANSLATORS Action for testing a banner or modal presentation without delivering it.
const adminTestPresentationLabel = t('communications', 'Test presentation')
// TRANSLATORS Label for recipients in the publication summary.
const adminForWhomLabel = t('communications', 'For whom')
</script>

<template>
	<aside
		class="cc-wizard-sidebar"
		:aria-label="
			t('communications', 'Publication summary and preview')
		">
		<section class="cc-wizard-summary">
			<h2>
				<NcIconSvgWrapper
					:path="mdiBullhornOutline"
					:size="19" />
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				{{ t('communications', 'Publication summary') }}
			</h2>
			<dl class="cc-wizard-summary-list">
				<div>
					<NcIconSvgWrapper
						:path="mdiLightningBoltOutline"
						:size="17" />
					<!-- TRANSLATORS Section showing the selected trigger and schedule. -->
					<dt>{{ t('communications', 'When') }}</dt>
					<dd>{{ summaryWhen }}</dd>
				</div>
				<div>
					<NcIconSvgWrapper
						:path="mdiAccountGroupOutline"
						:size="17" />
					<dt>
						{{ adminForWhomLabel }}
					</dt>
					<dd>{{ audienceReview }}</dd>
				</div>
				<div>
					<NcIconSvgWrapper
						:path="mdiMapMarkerOutline"
						:size="17" />
					<!-- TRANSLATORS Section showing placement and presentation of the publication. -->
					<dt>{{ t('communications', 'Context') }}</dt>
					<dd>{{ locationReview }}</dd>
				</div>
				<div>
					<NcIconSvgWrapper
						:path="mdiBullhornOutline"
						:size="17" />
					<!-- TRANSLATORS Section summarizing channels selected for sending. -->
					<dt>{{ t('communications', 'Delivery') }}</dt>
					<dd>{{ deliveryReview }}</dd>
				</div>
				<div>
					<NcIconSvgWrapper :path="mdiRepeat" :size="17" />
					<!-- TRANSLATORS Section summarizing how often messages may be shown or sent. -->
					<dt>{{ t('communications', 'Frequency') }}</dt>
					<dd>{{ frequencyReview }}</dd>
				</div>
				<div>
					<NcIconSvgWrapper
						:path="mdiCalendarRange"
						:size="17" />
					<dt>
						<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
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
			<!-- TRANSLATORS Open a non-delivering preview of a publication. -->
			<h2>
				<NcIconSvgWrapper :path="mdiEyeOutline" :size="19" />
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				{{
					form.presentation === 'none'
						? adminMessagePreviewTitleLabel
						: adminPreviewTitleLabel
				}}
			</h2>
			<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
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
						form.headline || adminPublicationTitleLabel
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
						<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
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
				<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
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
						? adminPreviewMessageLabel
						: adminTestPresentationLabel
				"
				:disabled="
					!form.headline.trim() || !form.message.trim()
				"
				@click="emit('preview')">
				<template #icon
					><NcIconSvgWrapper
						:path="mdiEyeOutline"
						:size="18"
				/></template>
			</NcButton>
		</section>
	</aside>
</template>
<strong
	:style="
		form.text_align
			? { textAlign: form.text_align }
			: undefined
	"
	>{{
		form.headline || adminPublicationTitleLabel
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
		<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
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
<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
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
		? adminPreviewMessageLabel
		: adminTestPresentationLabel
"
:disabled="
	!form.headline.trim() || !form.message.trim()
"
@click="emit('preview')">
<template #icon
	><NcIconSvgWrapper
		:path="mdiEyeOutline"
		:size="18"
/></template>
			</NcButton>
		</section>
	</aside>
</template>

<style lang="scss" scoped>

.cc-native-preview-link {
	color: var(--color-primary-element);
	font-weight: 650;
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

	> div {
		display: grid;
		grid-template-columns: 20px minmax(75px, 0.83fr) minmax(0, 1.15fr);
		gap: 7px;
		padding: 9px 0;
		align-items: center;
		border-bottom: 1px solid var(--color-border);
		min-width: 0;
	}

	> div:first-child {
		padding-top: 2px;
	}

	> div:last-child {
		border: 0;
		padding-bottom: 0;
	}

	dt {
		font-size: 12px;
		font-weight: 650;
	}

	dd {
		margin: 0;
		font-size: 12px;
		line-height: 1.35;
		color: var(--color-main-text);
		overflow-wrap: anywhere;
	}
}

.cc-wizard-preview-content {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-inline-start: 3px solid var(--color-primary-element);
	border-radius: 8px;
	padding: 13px;
	margin: 0 0 12px;

	&[data-presentation='modal'] {
		border-inline-start-width: 1px;
		box-shadow: 0 2px 12px var(--color-box-shadow);
	}
}

.cc-wizard-preview-notecard {
	&:deep(.notecard) {
		margin: 0 0 12px;
		padding: 13px;
		min-width: 0;
	}

	> div {
		min-width: 0;
	}
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

.cc-wizard-summary-list {
	> div {
		grid-template-columns: 18px minmax(66px, 82px) minmax(0, 1fr);
		gap: 9px;
		min-height: 34px;
		padding: 6px 0;
	}

	> div:first-child {
		padding-top: 0;
	}
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

.cc-wizard-summary-list {
	> div > dt {
		font-size: 12px;
		font-weight: 650;
	}

	> div > dd {
		font-size: 12px;
		font-weight: 400;
	}
}

.cc-wizard-summary-list {
	> div > :deep(.icon-vue) {
		width: 18px !important;
		height: 18px !important;
		min-width: 18px !important;
		min-height: 18px !important;
	}

	> div > :deep(.icon-vue) svg {
		width: 18px !important;
		height: 18px !important;
	}
}

.cc-wizard-preview[data-presentation='none'] {
	border: 1px solid var(--color-border);
}
</style>
