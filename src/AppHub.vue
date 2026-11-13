<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import { computed, ref, onMounted, onUnmounted } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import Board from './CommunicationsBoard.vue'
import CampaignAdmin from './CampaignAdmin.vue'
import { mdiInboxOutline, mdiFileDocumentEditOutline, mdiPlus } from '@mdi/js'

const canEdit = document.getElementById('communications-vue')?.dataset.canEdit==='true'
type Section = 'inbox' | 'publications'
const section = ref<Section>('inbox')
const editorView = ref<string>('list')
const requestedCreate = ref(0)
const unreadCount = ref<number | null>(null)
function initialSection(): Section {
 const url = new URL(window.location.href)
 const query = url.searchParams.get('view')
 if (url.pathname.endsWith('/board')) return 'inbox'
 return canEdit && (query==='publications'||query==='new') ? 'publications' : 'inbox'
}
function navigate(next: Section, create = false): void {
 if (next==='publications' && !canEdit) return
 if (next===section.value && !create) return
 const url = new URL(window.location.href)
 // Readable deep links must survive switching back to the inbox.
 if (next==='publications') url.searchParams.delete('id')
 url.searchParams.set('view',create?'new':next)
 history.pushState({section:next},'',url)
 section.value=next
 if (create) requestedCreate.value++
}
function applyHistory() {
 section.value=initialSection()
 if(section.value==='publications' && new URL(location.href).searchParams.get('view')==='new') {
   requestedCreate.value++
 }
}
function onUnreadCount(total: number): void {unreadCount.value=total}
function onEditorView(next: string): void {editorView.value=next}
const hubTitle=computed(()=>section.value==='inbox'?t('communications', 'Inbox'):t('communications', 'Publications'))
onMounted(()=>{applyHistory();window.addEventListener('popstate',applyHistory)})
onUnmounted(()=>window.removeEventListener('popstate',applyHistory))
</script>

<template>
  <NcAppContent :page-heading="hubTitle" class="cc-hub">
    <div class="cc-hub-shell">
      <header class="cc-hub-top">
        <div class="cc-hub-brand">
          <h1>{{ t('communications', 'Communications') }}</h1>
          <p>{{ t('communications', 'Send and manage announcements and messages in Nextcloud.') }}</p>
        </div>
        <NcButton v-if="canEdit && section==='publications' && editorView==='list'" variant="primary" :text="t('communications', 'New publication')" @click="navigate('publications',true)">
          <template #icon><NcIconSvgWrapper :path="mdiPlus" :size="20" /></template>
        </NcButton>
      </header>
      <nav class="cc-hub-tabs" :aria-label="t('communications', 'Communication areas')">
        <NcButton class="cc-hub-tab" :class="{'cc-hub-tab-active':section==='inbox'}"
                  :pressed="section==='inbox'" variant="tertiary"
                  @click="navigate('inbox')">
          <template #icon><NcIconSvgWrapper :path="mdiInboxOutline" :size="19"/></template>
          <span>{{ t('communications', 'Inbox') }}</span>
          <span v-if="unreadCount!==null && unreadCount>0" class="cc-hub-count" :aria-label="t('communications', 'Unread messages: {count}', {count: unreadCount})">{{ unreadCount }}</span>
        </NcButton>
        <NcButton v-if="canEdit" class="cc-hub-tab" :class="{'cc-hub-tab-active':section==='publications'}"
                  :pressed="section==='publications'" variant="tertiary"
                  @click="navigate('publications')">
          <template #icon><NcIconSvgWrapper :path="mdiFileDocumentEditOutline" :size="19"/></template>
          <span>{{ t('communications', 'Publications') }}</span>
        </NcButton>
      </nav>
      <!-- v-show belongs on real DOM wrappers: CampaignAdmin has a fragment
           root (editor + dialogs), so v-show on the component leaks it through
           inactive tabs. Keep both panes mounted to preserve drafts and filters. -->
      <div v-show="section==='inbox'" class="cc-hub-pane cc-hub-board">
        <Board @unread-count="onUnreadCount" />
      </div>
      <div v-if="canEdit" v-show="section==='publications'" class="cc-hub-pane cc-hub-admin">
        <CampaignAdmin :create-request="requestedCreate" @editor-view="onEditorView"/>
      </div>
    </div>
  </NcAppContent>
</template>
