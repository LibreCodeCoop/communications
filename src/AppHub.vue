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

const canEdit =
	document.getElementById('communications-vue')?.dataset.canEdit === 'true'
type Section = 'inbox' | 'publications'
const section = ref<Section>('inbox')
const editorView = ref<string>('list')
const requestedCreate = ref(0)
const unreadCount = ref<number | null>(null)
function initialSection(): Section {
	const url = new URL(window.location.href)
	const query = url.searchParams.get('view')
	if (url.pathname.endsWith('/board')) return 'inbox'
	return canEdit && (query === 'publications' || query === 'new')
		? 'publications'
		: 'inbox'
}
function navigate(next: Section, create = false): void {
	if (next === 'publications' && !canEdit) return
	if (next === section.value && !create) return
	const url = new URL(window.location.href)
	// Readable deep links must survive switching back to the inbox.
	if (next === 'publications') url.searchParams.delete('id')
	url.searchParams.set('view', create ? 'new' : next)
	history.pushState({ section: next }, '', url)
	section.value = next
	if (create) requestedCreate.value++
}
function applyHistory() {
	section.value = initialSection()
	if (
		section.value === 'publications'
		&& new URL(location.href).searchParams.get('view') === 'new'
	) {
		requestedCreate.value++
	}
}
function onUnreadCount(total: number): void {
	unreadCount.value = total
}
function onEditorView(next: string): void {
	editorView.value = next
}
const hubTitle = computed(() =>
	section.value === 'inbox'
		? t('communications', 'Inbox')
		: t('communications', 'Publications'),
)
onMounted(() => {
	applyHistory()
	window.addEventListener('popstate', applyHistory)
})
onUnmounted(() => window.removeEventListener('popstate', applyHistory))
</script>

<template>
	<NcAppContent :page-heading="hubTitle" class="cc-hub">
		<div class="cc-hub-shell">
			<header class="cc-hub-top">
				<div class="cc-hub-brand">
					<h1>{{ t('communications', 'Communications') }}</h1>
					<p>
						{{
							t(
								'communications',
								'Send and manage announcements and messages in Nextcloud.',
							)
						}}
					</p>
				</div>
				<NcButton
					v-if="
						canEdit
						&& section === 'publications'
						&& editorView === 'list'
					"
					variant="primary"
					:text="t('communications', 'New publication')"
					@click="navigate('publications', true)">
					<template #icon
						><NcIconSvgWrapper :path="mdiPlus" :size="20"
					/></template>
				</NcButton>
			</header>
			<nav
				class="cc-hub-tabs"
				:aria-label="t('communications', 'Communication areas')">
				<NcButton
					class="cc-hub-tab"
					:class="{ 'cc-hub-tab-active': section === 'inbox' }"
					:pressed="section === 'inbox'"
					variant="tertiary"
					@click="navigate('inbox')">
					<template #icon
						><NcIconSvgWrapper :path="mdiInboxOutline" :size="19"
					/></template>
					<span>{{ t('communications', 'Inbox') }}</span>
					<span
						v-if="unreadCount !== null && unreadCount > 0"
						class="cc-hub-count"
						:aria-label="
							t('communications', 'Unread messages: {count}', {
								count: unreadCount,
							})
						"
						>{{ unreadCount }}</span
					>
				</NcButton>
				<NcButton
					v-if="canEdit"
					class="cc-hub-tab"
					:class="{ 'cc-hub-tab-active': section === 'publications' }"
					:pressed="section === 'publications'"
					variant="tertiary"
					@click="navigate('publications')">
					<template #icon
						><NcIconSvgWrapper
							:path="mdiFileDocumentEditOutline"
							:size="19"
					/></template>
					<span>{{ t('communications', 'Publications') }}</span>
				</NcButton>
			</nav>
			<!-- v-show belongs on real DOM wrappers: CampaignAdmin has a fragment
           root (editor + dialogs), so v-show on the component leaks it through
           inactive tabs. Keep both panes mounted to preserve drafts and filters. -->
			<div v-show="section === 'inbox'" class="cc-hub-pane cc-hub-board">
				<Board @unread-count="onUnreadCount" />
			</div>
			<div
				v-if="canEdit"
				v-show="section === 'publications'"
				class="cc-hub-pane cc-hub-admin">
				<CampaignAdmin
					:create-request="requestedCreate"
					@editor-view="onEditorView" />
			</div>
		</div>
	</NcAppContent>
</template>

<style lang="scss" scoped>
.cc-hub {
	width: 100%;
	max-width: none;
	min-height: calc(100vh - var(--header-height, 50px));
	overflow: auto;
}

.cc-hub-shell {
	min-height: 100%;
	background: var(--color-main-background);
}

.cc-hub-top {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 16px;
	padding: 24px clamp(14px, 3vw, 48px) 16px;
}

.cc-hub-brand {
	min-width: 0;
}

.cc-hub-brand h1 {
	font-size: clamp(25px, 2.5vw, 32px);
	margin: 0;
}

.cc-hub-brand p {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	margin: 6px 0 0;
}

.cc-hub-tabs {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 0 clamp(14px, 3vw, 48px) 12px;
	border-bottom: 1px solid var(--color-border);
	flex-wrap: wrap;
}

.cc-hub-tabs button {
	min-height: 40px;
}

.cc-hub-count {
	font-size: 11px;
	font-weight: 700;
	padding: 2px 7px;
	border-radius: 12px;
	background: var(--color-background-hover);
	color: var(--color-main-text);
}

.cc-hub-admin .cc-native-page {
	padding-top: 24px;
}

.cc-hub-board .cc-board-main {
	padding-top: 24px;
}

.cc-hub-admin .cc-native-heading h1 {
	font-size: 23px;
}

.cc-hub-board .cc-board-heading {
	margin-bottom: 18px;
}

.cc-hub-board .cc-board-heading h2 {
	font-size: 21px;
	margin: 0 0 6px;
}

@media (max-width: 650px) {
	.cc-hub-top {
		align-items: flex-start;
		flex-wrap: wrap;
		padding-top: 16px;
	}
}

@media (max-width: 650px) {
	.cc-hub-top > button {
		width: 100%;
	}
}

.cc-hub-shell {
	width: 100%;
	min-width: 0;
}

.cc-hub-pane {
	width: 100%;
	min-width: 0;
}

.cc-hub-pane[style*='display: none'] {
	display: none !important;
}

.cc-hub-admin .cc-native-app,
.cc-hub-board .cc-board-app {
	min-height: 0;
	overflow: visible;
}

.cc-hub-admin .cc-native-page {
	padding-top: 20px;
}

.cc-hub-board .cc-board-main {
	max-width: 1200px;
	width: 100%;
	margin: 0;
	padding: 20px clamp(14px, 3vw, 48px) 54px;
}

.cc-hub-board .cc-board-heading {
	margin-bottom: 16px;
}

.cc-hub-board .cc-board-heading h2 {
	margin: 0 0 5px;
	font-size: 18px;
}

.cc-hub-board .cc-board-heading p {
	font-size: 13px;
}

.cc-hub-top {
	padding-top: 22px;
	padding-bottom: 15px;
}

.cc-hub-brand h1 {
	font-size: clamp(23px, 2.2vw, 30px);
}

.cc-hub-tabs {
	gap: 14px;
	padding-bottom: 0;
}

.cc-hub-tabs .cc-hub-tab {
	border: 0 !important;
	border-bottom: 3px solid transparent !important;
	border-radius: 0 !important;
	min-height: 46px;
	background: transparent !important;
	color: var(--color-main-text) !important;
	padding: 0 12px !important;
}

.cc-hub-tabs .cc-hub-tab-active {
	border-bottom-color: var(--color-primary-element) !important;
	background: var(--color-background-hover) !important;
	color: var(--color-main-text) !important;
}

.cc-hub-tabs .cc-hub-tab:hover {
	background: var(--color-background-hover) !important;
}

.cc-hub-tabs .cc-hub-tab:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.cc-hub-admin .cc-native-heading-overview {
	margin-bottom: 15px;
}

.cc-hub-admin .cc-native-heading-overview h2 {
	font-size: 18px;
	margin: 0;
}

.cc-hub-admin .cc-native-heading-overview p {
	font-size: 13px;
	margin-top: 6px;
}

.cc-hub-admin .cc-native-stats {
	display: flex;
	flex-wrap: wrap;
	gap: 9px;
	margin-bottom: 21px;
}

.cc-hub-admin .cc-native-stat {
	flex: 1 1 135px;
	min-width: 112px;
	padding: 11px 14px;
	gap: 4px;
}

.cc-hub-admin .cc-native-stat strong {
	font-size: 22px;
}

.cc-hub-admin .cc-native-stat span {
	font-size: 12px;
}

.cc-hub-admin .cc-editor-settings {
	margin-top: 22px;
}

.cc-hub-admin .cc-editor-delegation {
	max-width: 720px;
}

@media (max-width: 640px) {
	.cc-hub-top {
		padding-top: 16px;
	}
}

@media (max-width: 640px) {
	.cc-hub-board .cc-board-main {
		padding: 16px 12px 36px;
	}
}

@media (max-width: 640px) {
	.cc-hub-tabs {
		gap: 4px;
	}
}

@media (max-width: 640px) {
	.cc-hub-tabs .cc-hub-tab {
		padding: 0 8px !important;
	}
}

@media (max-width: 640px) {
	.cc-hub-admin .cc-native-stat {
		flex-basis: 125px;
	}
}
</style>
