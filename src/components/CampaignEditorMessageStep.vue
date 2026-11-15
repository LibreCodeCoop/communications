<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { useCampaignEditorContext } from '../composables/campaignEditorContext'
import CampaignIdentityFields from './CampaignIdentityFields.vue'
import CampaignContentFields from './CampaignContentFields.vue'
import CampaignAudienceFields from './CampaignAudienceFields.vue'
import CampaignAppearanceFields from './CampaignAppearanceFields.vue'
import DeliveryChannelOption from './DeliveryChannelOption.vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { mdiPaletteOutline } from '@mdi/js'
const {
	editorId,
	feedback,
	form,
	hasAdditionalNotices,
	eventDrivenDelivery,
	hasDestination,
	destinationCount,
	inboxCopy,
	gatewayOptions,
	gatewayProviderCountLabel,
	deliveryChannelOptions,
	onDeliveryChannelChange,
	noticeEmail,
	visualDeliveryOptions,
	inboxDeliveryOption,
	deliveryReview,
	adminMessagingAndSMSTwoFactorLabel,
} = useCampaignEditorContext()
</script>
<template>
	<div class="cc-wizard-step-content">
		<div class="cc-wizard-step-title">
			<h2>
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				{{ t('communications', 'Message, audience and delivery') }}
			</h2>
			<p>
				<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
				{{
					t(
						'communications',
						'Write a message, select recipients, and configure every channel in one workflow.',
					)
				}}
			</p>
		</div>
		<CampaignIdentityFields />
		<CampaignContentFields />
		<CampaignAudienceFields />

		<section class="cc-native-section cc-delivery-section">
			<h2>
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				{{ t('communications', 'Delivery methods') }}
			</h2>
			<p class="cc-native-hint">
				<!-- TRANSLATORS Action or instruction in the publications management screen. -->
				{{
					t(
						'communications',
						'Select one or more destinations. Contextual banners and modal dialogs are alternatives: selecting one disables the other. Inbox, notifications, activity, email, and messaging can be freely combined.',
					)
				}}
			</p>
			<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
			<div
				class="cc-delivery-selector"
				role="group"
				:aria-label="t('communications', 'Publication delivery methods')">
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
						<strong>{{ adminMessagingAndSMSTwoFactorLabel }}</strong>
						<span>{{ gatewayProviderCountLabel }}</span>
					</summary>
					<p class="cc-native-hint">
						<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
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
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				{{ t('communications', 'Allow Inbox comments') }}
			</NcCheckboxRadioSwitch>
			<NcNoteCard
				v-if="!hasDestination"
				type="warning"
				class="cc-delivery-empty-warning">
				<!-- TRANSLATORS Action or instruction in the publications management screen. -->
				{{
					t(
						'communications',
						'Select at least one delivery method to continue.',
					)
				}}
			</NcNoteCard>
			<p v-if="eventDrivenDelivery" class="cc-native-hint">
				<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
				{{
					t(
						'communications',
						'Each event occurrence targets only the authorized recipient. Outbound channels are queued; a banner or modal appears on the next eligible visit. Frequency limits apply to all deliveries, with no mass dispatch on activation.',
					)
				}}
			</p>
			<p
				v-else-if="['banner', 'modal'].includes(form.presentation)"
				class="cc-native-hint">
				<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
				{{
					t(
						'communications',
						'A banner or modal appears on the next eligible visit. Other selected channels are queued once per activation, respecting the start date.',
					)
				}}
			</p>
			<p v-if="noticeEmail" class="cc-native-hint">
				<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
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
				<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
				{{
					t(
						'communications',
						'Changing channels does not resend earlier messages. New events follow the active configuration, consent, availability dates, and frequency limits.',
					)
				}}
			</p>
			<CampaignAppearanceFields />
		</section>
	</div>
</template>
