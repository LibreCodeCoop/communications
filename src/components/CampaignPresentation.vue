<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup>import { t } from '@nextcloud/l10n'

import { computed } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { bannerAppearance } from '../bannerAppearance.js'
import { safeCtaLink } from '../linkSafety.js'
import NcRichText from '@nextcloud/vue/components/NcRichText'

const props = defineProps({
  campaign: { type: Object, required: true },
  open: { type: Boolean, default: false },
  preview: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'cta', 'optout'])
const sponsored = computed(() => props.campaign.category === 'sponsored')
const theme = computed(() => props.campaign.theme || 'default')
// Keeps live delivery identical to the editor preview, including legacy themes.
const appearance = computed(() => bannerAppearance(props.campaign))
const textStyle = computed(()=>{
  const c = typeof props.campaign.text_color === 'string' ? props.campaign.text_color : ''
  const color = /^#[0-9a-fA-F]{6}(?:[0-9a-fA-F]{2})?$/.test(c)
    ? 'color-mix(in srgb, '+c+' 25%, var(--color-main-text) 75%)' : undefined
  return {textAlign:props.campaign.text_align || 'left', color}
})
const title = computed(() => props.campaign.headline || props.campaign.title || t('communications', 'Untitled campaign'))
const message = computed(() => props.campaign.message || '')
const presentation = computed(() => props.campaign.presentation === 'modal' ? 'modal' : 'banner')
const ctaLabel = computed(() => props.campaign.cta_label || props.campaign.ctaLabel || '')
const rawUrl = computed(() => props.campaign.cta_url || props.campaign.ctaUrl || '')
const link = computed(() => safeCtaLink(rawUrl.value))
const target = computed(() => /^https?:\/\//i.test(link.value) ? '_blank' : '_self')
function close() { emit('close') }
function click() { if (props.preview) emit('close'); else emit('cta') }
function optout() { if (props.preview) emit('close'); else emit('optout') }
function updateOpen(open) { if (!open) close() }
</script>

<template>
  <NcNoteCard v-if="open && presentation === 'banner'" class="cc-live-banner"
              :class="appearance.noteClass" :style="appearance.style"
              :type="appearance.type" :data-theme="theme" :aria-label="title">
    <template v-if="appearance.iconPath || appearance.hideIcon" #icon>
      <NcIconSvgWrapper v-if="appearance.iconPath" class="cc-banner-note-icon" :path="appearance.iconPath" :size="20" :style="{color:'var(--note-theme)'}" inline />
      <span v-else class="cc-note-empty-icon" aria-hidden="true"></span>
    </template>
    <span v-if="preview" class="cc-preview-screenreader-note">{{ t('communications', 'Test mode. No interaction is recorded.') }}</span>
    <p v-if="sponsored" class="cc-sponsored-label">{{ t('communications', 'Sponsored content') }}</p>
    <div class="cc-live-content" :style="textStyle" dir="auto">
      <h2 dir="auto">{{ title }}</h2>
      <NcRichText :text="message" :use-markdown="true" :reference-limit="0" />
    </div>
    <div class="cc-live-actions">
      <NcButton v-if="ctaLabel && link" variant="tertiary" :href="preview ? undefined : link" :target="preview ? undefined : target"
                :text="ctaLabel" @click="click" />
      <NcButton v-if="sponsored" variant="tertiary" :text="t('communications', 'Hide sponsored content')" @click="optout" />
      <NcButton v-if="campaign.dismissible!==false" variant="primary" :text="t('communications', 'Got it')" @click="close" />
    </div>
  </NcNoteCard>

  <NcDialog v-else-if="presentation === 'modal'"
            :open="open"
            :name="title"
            container="body"
            size="normal"
            @update:open="updateOpen">
    <div class="cc-live-dialog-content" dir="auto" :data-theme="theme" :style="[{...appearance.style}, textStyle]">
      <p v-if="sponsored" class="cc-sponsored-label">{{ t('communications', 'Sponsored content') }}</p>
      <span v-if="preview" class="cc-preview-screenreader-note">{{ t('communications', 'Test mode. No interaction is recorded.') }}</span>
      <NcRichText :text="message" :use-markdown="true" :reference-limit="0" />
      <div class="cc-live-actions">
        <NcButton v-if="ctaLabel && link" variant="tertiary" :href="preview ? undefined : link" :target="preview ? undefined : target"
                  :text="ctaLabel" @click="click" />
        <NcButton v-if="sponsored" variant="tertiary" :text="t('communications', 'Hide sponsored content')" @click="optout" />
        <NcButton variant="primary" :text="t('communications', 'Got it')" @click="close" />
      </div>
    </div>
  </NcDialog>
</template>
