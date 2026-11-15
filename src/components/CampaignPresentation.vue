<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import { computed } from 'vue'
import type { PublicationPresentation } from '../types/communications'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { bannerAppearance } from '../bannerAppearance.ts'
import { safeCtaLink } from '../linkSafety.ts'
import NcRichText from '@nextcloud/vue/components/NcRichText'

const props = withDefaults(
	defineProps<{
		campaign: PublicationPresentation
		open?: boolean
		preview?: boolean
	}>(),
	{ open: false, preview: false },
)
const emit = defineEmits<{ close: []; cta: []; optout: [] }>()
const sponsored = computed(() => props.campaign.category === 'sponsored')
const theme = computed(() => props.campaign.theme || 'default')
// Keeps live delivery identical to the editor preview, including legacy themes.
const appearance = computed(() => bannerAppearance(props.campaign))
const textStyle = computed(() => {
	const c =
		typeof props.campaign.text_color === 'string'
			? props.campaign.text_color
			: ''
	const color = /^#[0-9a-fA-F]{6}(?:[0-9a-fA-F]{2})?$/.test(c)
		? 'color-mix(in srgb, ' + c + ' 25%, var(--color-main-text) 75%)'
		: undefined
	return { textAlign: props.campaign.text_align || 'left', color }
})
const title = computed(
	() =>
		props.campaign.headline
		|| props.campaign.title
		|| t('communications', 'Untitled campaign'),
)
const message = computed(() => props.campaign.message || '')
const presentation = computed(() =>
	props.campaign.presentation === 'modal' ? 'modal' : 'banner',
)
const ctaLabel = computed(
	() => props.campaign.cta_label || props.campaign.ctaLabel || '',
)
const rawUrl = computed(() => props.campaign.cta_url || props.campaign.ctaUrl || '')
const link = computed(() => safeCtaLink(rawUrl.value))
const target = computed(() =>
	/^https?:\/\//i.test(link.value) ? '_blank' : '_self',
)
function close() {
	emit('close')
}
function click() {
	if (props.preview) emit('close')
	else emit('cta')
}
function optout() {
	if (props.preview) emit('close')
	else emit('optout')
}
function updateOpen(open: boolean): void {
	if (!open) close()
}
</script>

<template>
	<NcNoteCard
		v-if="open && presentation === 'banner'"
		class="cc-live-banner"
		:class="appearance.noteClass"
		:style="appearance.style"
		:type="appearance.type"
		:data-theme="theme"
		:aria-label="title">
		<template v-if="appearance.iconPath || appearance.hideIcon" #icon>
			<NcIconSvgWrapper
				v-if="appearance.iconPath"
				class="cc-banner-note-icon"
				:path="appearance.iconPath"
				:size="20"
				:style="{ color: 'var(--note-theme)' }"
				inline />
			<span v-else class="cc-note-empty-icon" aria-hidden="true"></span>
		</template>
		<span v-if="preview" class="cc-preview-screenreader-note">{{
			t('communications', 'Test mode. No interaction is recorded.')
		}}</span>
		<!-- TRANSLATORS Label identifying a promotional announcement. -->
		<p v-if="sponsored" class="cc-sponsored-label">
			{{ t('communications', 'Sponsored content') }}
		</p>
		<div class="cc-live-content" :style="textStyle" dir="auto">
			<h2 dir="auto">{{ title }}</h2>
			<NcRichText :text="message" :use-markdown="true" :reference-limit="0" />
		</div>
		<div class="cc-live-actions">
			<NcButton
				v-if="ctaLabel && link"
				variant="tertiary"
				:href="preview ? undefined : link"
				:target="preview ? undefined : target"
				:text="ctaLabel"
				@click="click" />
			<NcButton
				v-if="sponsored"
				variant="tertiary"
				:text="t('communications', 'Hide sponsored content')"
				@click="optout" />
			<!-- TRANSLATORS Button dismissing a contextual announcement after the recipient reads it. -->
			<NcButton
				v-if="campaign.dismissible !== false"
				variant="primary"
				:text="t('communications', 'Got it')"
				@click="close" />
		</div>
	</NcNoteCard>

	<NcDialog
		v-else-if="presentation === 'modal'"
		:open="open"
		:name="title"
		container="body"
		size="normal"
		@update:open="updateOpen">
		<div
			class="cc-live-dialog-content"
			dir="auto"
			:data-theme="theme"
			:style="[{ ...appearance.style }, textStyle]">
			<!-- TRANSLATORS Label identifying a promotional announcement. -->
			<p v-if="sponsored" class="cc-sponsored-label">
				{{ t('communications', 'Sponsored content') }}
			</p>
			<span v-if="preview" class="cc-preview-screenreader-note">{{
				t('communications', 'Test mode. No interaction is recorded.')
			}}</span>
			<NcRichText :text="message" :use-markdown="true" :reference-limit="0" />
			<div class="cc-live-actions">
				<NcButton
					v-if="ctaLabel && link"
					variant="tertiary"
					:href="preview ? undefined : link"
					:target="preview ? undefined : target"
					:text="ctaLabel"
					@click="click" />
				<NcButton
					v-if="sponsored"
					variant="tertiary"
					:text="t('communications', 'Hide sponsored content')"
					@click="optout" />
				<!-- TRANSLATORS Button dismissing a contextual announcement after the recipient reads it. -->
				<NcButton
					variant="primary"
					:text="t('communications', 'Got it')"
					@click="close" />
			</div>
		</div>
	</NcDialog>
</template>

<style lang="scss" scoped>
/*
 * Shared by editor and delivery. The built-in message type retains its native
 * semantics. Neutral/custom override only presentation, never the event role.
 */
.cc-note-neutral.notecard {
	--note-theme: var(--color-border-dark, var(--color-border));
	--note-background: var(--color-main-background);
	background-color: var(--color-main-background) !important;
	border-inline-start: 2px solid var(--color-border) !important;
}

.cc-note-custom.notecard {
	/* Use a restrained tint to preserve readable text with either NC theme. */
	--note-background: color-mix(
		in srgb,
		var(--cc-selected-color) 18%,
		var(--color-main-background)
	);
	/* Blend towards the user's text color to keep icons/borders distinguishable. */
	--note-theme: color-mix(
		in srgb,
		var(--cc-selected-color) 45%,
		var(--color-main-text) 55%
	);
	background-color: var(--note-background) !important;
}

.cc-note-no-icon.notecard :deep(.notecard__icon) {
	display: none !important;
}

.cc-note-empty-icon {
	display: none !important;
}

.cc-banner-note-icon {
	flex: none;
	color: var(--note-theme);
}

.cc-live-banner {
	position: fixed;
	z-index: 1100;
	top: calc(var(--header-height, 50px) + 13px);
	inset-inline-start: 50%;
	transform: translateX(-50%);
	width: min(670px, 95vw);
	box-sizing: border-box;
	/* Native NcNoteCard owns the semantic background, border and icon. */
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 12px);
	padding: 16px 18px;
	box-shadow: 0 12px 35px var(--color-box-shadow);
	max-height: 60vh;
	overflow-y: auto;
}

.cc-live-banner.notecard {
	margin: 0;
}

.cc-live-banner > div {
	min-width: 0;
	width: 100%;
}

.cc-live-content h2 {
	font-size: 16px;
	font-weight: 700;
	line-height: 1.4;
	margin: 0 0 8px;
}

.cc-live-content p,
.cc-live-content ul,
.cc-live-dialog-content p,
.cc-live-dialog-content ul {
	margin: 6px 0;
	line-height: 1.5;
	overflow-wrap: anywhere;
}

.cc-live-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	align-items: center;
	gap: 10px;
	margin-top: 14px;
}

.cc-live-dialog-content {
	padding: 14px 16px 20px;
	min-width: 0;
}

.cc-preview-screenreader-note {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip-path: inset(50%);
	white-space: nowrap;
	border: 0;
}

.cc-sponsored-label {
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.06em;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
	margin: 0 0 8px !important;
}

.cc-live-dialog-content[data-theme='warning'] {
	border-top: 4px solid var(--color-warning-text);
}

.cc-live-dialog-content[data-theme='primary'] {
	border-top: 4px solid var(--color-primary-element);
}

.cc-live-dialog-content[data-theme='info'] {
	border-top: 4px solid var(--color-info-text);
}

.cc-live-dialog-content[data-theme='success'] {
	border-top: 4px solid var(--color-success-text);
}

.cc-live-dialog-content[data-theme='error'] {
	border-top: 4px solid var(--color-error-text);
}

.cc-live-dialog-content[data-theme='custom'] {
	border-top: 4px solid
		color-mix(in srgb, var(--cc-selected-color) 45%, var(--color-main-text) 55%);
}

.cc-live-dialog-content[data-theme='neutral'] {
	border-top: none;
}
</style>
