<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import type { DeliveryChannel } from '../types/communications'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import { mdiHelpCircleOutline } from '@mdi/js'

defineProps<{ channel: DeliveryChannel }>()
const emit = defineEmits<{ change: [{ id: string; enabled: boolean }] }>()
function change(channel: DeliveryChannel, enabled: boolean): void {
	if (channel.selectable) emit('change', { id: channel.id, enabled })
}
function channelDetailsLabel(channel: string): string {
	// TRANSLATORS {channel} is the delivery channel for which help is available.
	return t('communications', 'Details about {channel}', { channel })
}
function channelStatusLabel(status: string): string {
	// TRANSLATORS {status} is the delivery channel availability state shown to the user.
	return t('communications', 'Why is this channel {status}?', { status })
}
</script>

<template>
	<div
		class="cc-delivery-channel"
		:data-channel="channel.id"
		:data-availability="channel.availability"
		:data-selected="String(channel.selected)"
		:title="channel.help">
		<div class="cc-delivery-channel-header">
			<div class="cc-delivery-channel-control">
				<NcCheckboxRadioSwitch
					v-if="channel.selectable"
					:model-value="channel.selected"
					type="switch"
					@update:modelValue="change(channel, $event)">
					{{ channel.label }}
				</NcCheckboxRadioSwitch>
				<strong v-else class="cc-delivery-channel-unavailable">
					{{ channel.label }}
				</strong>
			</div>
			<div class="cc-delivery-channel-status">
				<span>{{ channel.status }}</span>
				<NcPopover
					:triggers="['hover', 'focus', 'click']"
					popup-role="dialog"
					placement="bottom-end">
					<template #trigger>
						<NcButton
							variant="tertiary"
							class="cc-delivery-channel-help"
							:aria-label="channelDetailsLabel(channel.label)"
							:title="
								channelStatusLabel(channel.status.toLowerCase())
							">
							<template #icon>
								<NcIconSvgWrapper
									:path="mdiHelpCircleOutline"
									:size="18" />
							</template>
						</NcButton>
					</template>
					<div class="cc-delivery-channel-help-content">
						<strong>{{ channel.label }}</strong>
						<p>{{ channel.help }}</p>
					</div>
				</NcPopover>
			</div>
		</div>
		<p class="cc-delivery-channel-description">{{ channel.description }}</p>
	</div>
</template>

<style lang="scss" scoped>
.cc-delivery-channel {
	min-width: 0;
	padding: 11px 0;
	border-bottom: 1px solid var(--color-border);
}

.cc-delivery-channel:last-child {
	border-bottom: none;
	padding-bottom: 0;
}

.cc-delivery-channel-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
	min-width: 0;
}

.cc-delivery-channel-control {
	min-width: 0;
	flex: 1 1 auto;
	line-height: 1.35;
}

.cc-delivery-channel-control :deep(.checkbox-radio-switch) {
	min-width: 0;
	margin: 0;
}

.cc-delivery-channel-control .checkbox-radio-switch__label {
	overflow-wrap: anywhere;
}

.cc-delivery-channel-unavailable {
	font-size: 13px;
	font-weight: 600;
	overflow-wrap: anywhere;
}

.cc-delivery-channel-status {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 3px;
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	flex: 0 0 auto;
	max-width: 50%;
}

.cc-delivery-channel-status span {
	text-align: end;
	line-height: 1.3;
}

.cc-delivery-channel-help {
	min-width: 28px !important;
	min-height: 28px !important;
	flex: none;
}

.cc-delivery-channel-help-content {
	max-width: 310px;
	min-width: 180px;
	padding: 10px 12px;
	line-height: 1.5;
}

.cc-delivery-channel-help-content strong {
	display: block;
	margin-bottom: 6px;
}

.cc-delivery-channel-help-content p {
	margin: 0;
	white-space: normal;
	overflow-wrap: anywhere;
	font-size: 13px;
}

.cc-delivery-channel-description {
	font-size: 11px;
	line-height: 1.4;
	color: var(--color-text-maxcontrast);
	margin: 4px 0 0;
}

.cc-delivery-channel[data-availability='missing_app'],
.cc-delivery-channel[data-availability='adapter_missing'],
.cc-delivery-channel[data-availability='missing_app_and_adapter'] {
	color: var(--color-main-text);
}

@media (max-width: 600px) {
	.cc-delivery-channel-header {
		flex-wrap: wrap;
		align-items: flex-start;
	}
}

@media (max-width: 600px) {
	.cc-delivery-channel-status {
		max-width: 100%;
		margin-inline-start: auto;
	}
}

@media (max-width: 600px) {
	.cc-delivery-channel-help-content {
		min-width: 0;
		max-width: min(280px, calc(100vw - 48px));
	}
}

.cc-gateway-provider-details .cc-delivery-channel:last-child {
	border-bottom: 0;
}

.cc-delivery-section .cc-delivery-selector > .cc-delivery-channel {
	padding: 13px 8px 12px;
	border-bottom: 1px solid var(--color-border);
}

.cc-delivery-section
	.cc-delivery-selector
	> .cc-delivery-channel[data-selected='true'] {
	background: color-mix(in srgb, var(--color-primary-element) 6%, transparent);
	border-radius: 8px;
}

.cc-delivery-section
	.cc-delivery-selector
	.cc-gateway-provider-details
	.cc-delivery-channel {
	margin-inline: 6px;
}
</style>
