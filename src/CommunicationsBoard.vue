<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t, getLanguage } from '@nextcloud/l10n'
import { requestApi } from './services/api'

import { onMounted, onUnmounted, ref, watch } from 'vue'
const emit = defineEmits<{ 'unread-count': [total: number] }>()
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import { safeCtaLink } from './linkSafety.ts'
import type {
	InboxAnnouncement,
	InboxComment,
	MentionOption,
	GatewayPreferenceChannel,
} from './types/communications'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiCommentTextOutline,
	mdiArrowLeft,
	mdiMagnify,
	mdiBellCogOutline,
} from '@mdi/js'

const items = ref<InboxAnnouncement[]>([])
const heading = ref<HTMLElement | null>(null)
const total = ref(0)
const page = ref(1)
const hasMore = ref(false)
const search = ref('')
const filter = ref('all')
const unreadTotal = ref(0)
const loading = ref(true)
const error = ref('')

// Settings belong to the recipient, not to the publication editor. These
// destinations are never obtained from the user's two-factor login settings.
const gatewaySettingsOpen = ref(false)
const gatewaySettingsLoaded = ref(false)
const gatewaySaving = ref(false)
const gatewayLoading = ref(false)
const gatewayError = ref('')
const gatewaySuccess = ref('')
const gatewayChannels = ref<GatewayPreferenceChannel[]>([])
const gatewayPlaceholder: Record<string, string> = {
	sms: '+5521999999999',
	signal: '+5521999999999',
	telegram: '123456789',
	gowhatsapp: '+5521999999999',
	whatsappbusiness: '+5521999999999',
	xmpp: 'user@example.com',
}
async function toggleGatewaySettings() {
	gatewaySettingsOpen.value = !gatewaySettingsOpen.value
	if (!gatewaySettingsOpen.value || gatewaySettingsLoaded.value) return
	gatewayLoading.value = true
	gatewayError.value = ''
	try {
		const data = await request<{ channels?: GatewayPreferenceChannel[] }>(
			'/api/gateway-preferences',
		)
		gatewayChannels.value = data.channels || []
		gatewaySettingsLoaded.value = true
	} catch (e) {
		gatewayError.value = e instanceof Error ? e.message : String(e)
	} finally {
		gatewayLoading.value = false
	}
}
async function saveGatewaySettings() {
	if (gatewaySaving.value) return
	gatewaySaving.value = true
	gatewayError.value = ''
	gatewaySuccess.value = ''
	try {
		const channels = Object.fromEntries(
			gatewayChannels.value.map((item) => [
				item.id,
				{
					enabled: !!item.enabled,
					destination: item.enabled ? item.destination.trim() : '',
					sponsored: !!item.enabled && !!item.sponsored,
				},
			]),
		)
		const data = await request<{ channels?: GatewayPreferenceChannel[] }>(
			'/api/gateway-preferences',
			'POST',
			{ channels },
		)
		gatewayChannels.value = data.channels || []
		gatewaySuccess.value = t(
			'communications',
			'Preferences saved. You can withdraw consent at any time.',
		)
	} catch (e) {
		gatewayError.value = e instanceof Error ? e.message : String(e)
	} finally {
		gatewaySaving.value = false
	}
}

const selected = ref<InboxAnnouncement | null>(null)
const comments = ref<InboxComment[]>([])
const commentDraft = ref('')
const mentionOptions = ref<MentionOption[]>([])
const mentionQuery = ref('')
let mentionSearchTimer: ReturnType<typeof setTimeout> | null = null
let mentionGeneration = 0
watch(commentDraft, (value) => {
	if (mentionSearchTimer !== null) clearTimeout(mentionSearchTimer)
	const match = String(value).match(/(?:^|\s)@([A-Za-z0-9_.-]{2,40})$/)
	mentionQuery.value = match?.[1] || ''
	mentionOptions.value = []
	const id = selected.value?.id
	const generation = ++mentionGeneration
	if (!match || !id) return
	mentionSearchTimer = setTimeout(async () => {
		try {
			const r = await request<{ users?: MentionOption[] }>(
				'/api/board/'
					+ id
					+ '/mentions?'
					+ new URLSearchParams({ search: mentionQuery.value }),
			)
			if (generation !== mentionGeneration || selected.value?.id !== id) return
			mentionOptions.value = (r.users || []).slice(0, 10)
		} catch {
			if (generation === mentionGeneration) mentionOptions.value = []
		}
	}, 250)
})
function selectMention(user: MentionOption): void {
	if (!user || !/^[A-Za-z0-9_.-]{2,64}$/.test(user.id)) return
	commentDraft.value = commentDraft.value.replace(
		/(?:^|\s)@[A-Za-z0-9_.-]{2,40}$/,
		(matched) =>
			matched[0] === '@'
				? '@' + user.id + ' '
				: matched[0] + '@' + user.id + ' ',
	)
	mentionOptions.value = []
}

const commentBusy = ref(false)
const commentHasMore = ref(false)
const commentBefore = ref<number | null>(null)
let generation = 0

function dateText(ts: number | null | undefined): string {
	return ts
		? new Date(ts * 1000).toLocaleString(getLanguage().replace('_', '-'), {
				dateStyle: 'medium',
				timeStyle: 'short',
			})
		: ''
}
function destinationLabel(channel: string): string {
	// TRANSLATORS {channel} is the name of a delivery channel, such as SMS.
	return t('communications', 'Destination for {channel}', { channel })
}
function unreadFilterLabel(count: number): string {
	// TRANSLATORS {count} is the number of unread messages in the Inbox filter.
	return t('communications', 'Unread ({count})', { count })
}
function commentsAriaLabel(title: string): string {
	// TRANSLATORS {title} is the announcement headline above its comments.
	return t('communications', 'Comments on {title}', { title })
}
function safeLink(url: unknown): string {
	return safeCtaLink(url)
}
async function request<T extends object>(
	path: string,
	method: 'GET' | 'POST' | 'DELETE' = 'GET',
	body: Record<string, unknown> = {},
): Promise<T> {
	return requestApi<T>(path, method, method === 'GET' ? undefined : body)
}

async function refresh() {
	const current = ++generation
	loading.value = true
	error.value = ''
	try {
		const q = new URLSearchParams({
			page: String(page.value),
			search: search.value.trim(),
			filter: filter.value,
		})
		const data = await request<{
			announcements?: InboxAnnouncement[]
			total?: number
			unreadTotal?: number
			hasMore?: boolean
		}>('/api/board?' + q)
		if (current !== generation) return
		items.value = data.announcements || []
		total.value = data.total || 0
		unreadTotal.value = data.unreadTotal || 0
		emit('unread-count', unreadTotal.value)
		hasMore.value = !!data.hasMore
	} catch (e) {
		if (current === generation)
			error.value = e instanceof Error ? e.message : String(e)
	} finally {
		if (current === generation) loading.value = false
	}
}
function doSearch() {
	page.value = 1
	void refresh()
}
function switchFilter(value: string): void {
	filter.value = value
	page.value = 1
	void refresh()
}
function changePage(delta: number): void {
	page.value += delta
	void refresh()
	heading.value?.scrollIntoView({ block: 'start', behavior: 'smooth' })
}
async function openItem(id: number): Promise<void> {
	error.value = ''
	try {
		const data = await request<{ announcement: InboxAnnouncement }>(
			'/api/board/' + id,
		)
		selected.value = data.announcement
		if (selected.value.inInbox && !selected.value.readAt) {
			await request('/api/board/' + id + '/read', 'POST', {})
			selected.value.readAt = Date.now() / 1000
			await refresh()
		}
		const url = new URL(location.href)
		url.searchParams.set('id', String(id))
		history.replaceState(null, '', url)
		comments.value = []
		commentBefore.value = null
		commentHasMore.value = false
		await loadComments()
	} catch (e) {
		error.value = e instanceof Error ? e.message : String(e)
		selected.value = null
	}
}
function closeItem() {
	selected.value = null
	comments.value = []
	commentDraft.value = ''
	const url = new URL(location.href)
	url.searchParams.delete('id')
	history.replaceState(null, '', url)
}
async function loadComments(more = false): Promise<void> {
	if (!selected.value) return
	try {
		const id = selected.value.id
		const query =
			more && commentBefore.value ? '?before=' + commentBefore.value : ''
		const data = await request<{
			comments: InboxComment[]
			hasMore?: boolean
			nextBefore?: number | null
		}>('/api/board/' + id + '/comments' + query)
		if (selected.value?.id !== id) return
		comments.value = more ? [...comments.value, ...data.comments] : data.comments
		commentHasMore.value = !!data.hasMore
		commentBefore.value = data.nextBefore ?? null
	} catch (e) {
		error.value = e instanceof Error ? e.message : String(e)
	}
}
async function sendComment() {
	if (commentBusy.value || !selected.value || !commentDraft.value.trim()) return
	commentBusy.value = true
	error.value = ''
	try {
		await request('/api/board/' + selected.value.id + '/comments', 'POST', {
			message: commentDraft.value,
		})
		commentDraft.value = ''
		await loadComments()
		selected.value.commentCount++
	} catch (e) {
		error.value = e instanceof Error ? e.message : String(e)
	} finally {
		commentBusy.value = false
	}
}
async function removeComment(comment: InboxComment): Promise<void> {
	if (!selected.value || !comment.canDelete || commentBusy.value) return
	commentBusy.value = true
	try {
		await request(
			'/api/board/' + selected.value.id + '/comments/' + comment.id,
			'DELETE',
			{},
		)
		comments.value = comments.value.filter((row) => row.id !== comment.id)
		selected.value.commentCount = Math.max(0, selected.value.commentCount - 1)
	} catch (e) {
		error.value = e instanceof Error ? e.message : String(e)
	} finally {
		commentBusy.value = false
	}
}
function onPopState() {
	const id = Number(new URLSearchParams(location.search).get('id'))
	if (Number.isInteger(id) && id > 0 && selected.value?.id !== id)
		void openItem(id)
	else if (!id) selected.value = null
}
onMounted(async () => {
	await refresh()
	onPopState()
	window.addEventListener('popstate', onPopState)
})
onUnmounted(() => window.removeEventListener('popstate', onPopState))
</script>

<template>
	<section class="cc-board-app" :aria-label="t('communications', 'Inbox')">
		<main class="cc-board-main">
			<header ref="heading" class="cc-board-heading">
				<div>
					<h2>{{ t('communications', 'Your messages') }}</h2>
					<p>
						{{
							t(
								'communications',
								'View announcements and notices addressed to you.',
							)
						}}
					</p>
				</div>
				<NcButton
					variant="tertiary"
					class="cc-gateway-preferences-button"
					:text="t('communications', 'Notification preferences')"
					:pressed="gatewaySettingsOpen"
					@click="toggleGatewaySettings">
					<template #icon
						><NcIconSvgWrapper :path="mdiBellCogOutline" :size="19"
					/></template>
				</NcButton>
			</header>
			<section
				v-if="gatewaySettingsOpen"
				class="cc-gateway-preferences"
				:aria-label="t('communications', 'Messaging preferences')">
				<h3>
					{{
						t(
							'communications',
							'Receive notifications through other channels',
						)
					}}
				</h3>
				<p>
					{{
						t(
							'communications',
							'Choose the channels where you want to receive publications. Two-factor authentication data is never reused. Messages are sent only with your explicit consent and a configured provider.',
						)
					}}
				</p>
				<NcNoteCard v-if="gatewayError" type="error" role="alert">{{
					gatewayError
				}}</NcNoteCard>
				<NcNoteCard v-if="gatewaySuccess" type="success" role="status">{{
					gatewaySuccess
				}}</NcNoteCard>
				<p v-if="gatewayLoading">
					{{ t('communications', 'Checking availability…') }}
				</p>
				<div
					v-else
					v-for="channel in gatewayChannels"
					:key="channel.id"
					class="cc-gateway-preference"
					:data-gateway="channel.id">
					<div class="cc-gateway-preference-heading">
						<NcCheckboxRadioSwitch
							v-model="channel.enabled"
							type="switch"
							:disabled="!channel.available && !channel.enabled">
							{{ channel.label }}
						</NcCheckboxRadioSwitch>
						<span>{{
							channel.available
								? t('communications', 'Configured')
								: channel.installed
									? t('communications', 'Provider not configured')
									: t('communications', 'App not installed')
						}}</span>
					</div>
					<template v-if="channel.enabled">
						<NcTextField
							v-model="channel.destination"
							:label="destinationLabel(channel.label)"
							:placeholder="gatewayPlaceholder[channel.id]"
							:maxlength="190"
							:disabled="!channel.available" />
						<NcCheckboxRadioSwitch
							v-model="channel.sponsored"
							type="checkbox"
							:disabled="!channel.available">
							{{
								t(
									'communications',
									'I also consent to sponsored content on this channel',
								)
							}}
						</NcCheckboxRadioSwitch>
					</template>
				</div>
				<p
					v-if="
						gatewaySettingsLoaded
						&& !gatewayChannels.some((ch) => ch.available)
					"
					class="cc-gateway-preferences-hint">
					{{
						t(
							'communications',
							'No provider is configured on this Nextcloud. Ask an administrator to install Two-Factor Gateway and configure a channel.',
						)
					}}
				</p>
				<p class="cc-gateway-preferences-hint">
					{{
						t(
							'communications',
							'Disabling a channel and saving removes its identifier from your preferences. Delivery is queued; sent messages cannot be recalled.',
						)
					}}
				</p>
				<NcButton
					v-if="gatewaySettingsLoaded"
					:text="t('communications', 'Save preferences')"
					variant="primary"
					:disabled="gatewaySaving"
					@click="saveGatewaySettings" />
			</section>
			<NcNoteCard v-if="error" type="error" role="alert">{{
				error
			}}</NcNoteCard>
			<template v-if="!selected">
				<div class="cc-board-search">
					<NcTextField
						v-model="search"
						type="search"
						:label="t('communications', 'Search messages')"
						@keyup.enter="doSearch" />
					<NcButton
						:text="t('communications', 'Search')"
						@click="doSearch">
						<template #icon
							><NcIconSvgWrapper :path="mdiMagnify" :size="20"
						/></template>
					</NcButton>
				</div>
				<div
					class="cc-inbox-filters"
					role="group"
					:aria-label="t('communications', 'Message filter')">
					<NcButton
						:pressed="filter === 'all'"
						:variant="filter === 'all' ? 'primary' : 'tertiary'"
						:text="t('communications', 'All')"
						@click="switchFilter('all')" />
					<NcButton
						:pressed="filter === 'unread'"
						:variant="filter === 'unread' ? 'primary' : 'tertiary'"
						:text="unreadFilterLabel(unreadTotal)"
						@click="switchFilter('unread')" />
				</div>
				<p v-if="loading" role="status">
					{{ t('communications', 'Loading messages…') }}
				</p>
				<NcEmptyContent
					v-else-if="items.length === 0"
					:name="t('communications', 'No messages found')"
					:description="
						t(
							'communications',
							'You have no messages matching this filter.',
						)
					" />
				<div v-else class="cc-board-list">
					<article
						v-for="item in items"
						:key="item.id"
						class="cc-board-article"
						:class="{ 'cc-board-unread': !item.readAt }"
						:style="{ textAlign: item.textAlign || 'left' }">
						<div class="cc-board-metadata">
							<strong v-if="!item.readAt" class="cc-unread-label">{{
								t('communications', 'Unread')
							}}</strong>
							<span>{{ item.author }}</span>
							<time
								:datetime="
									new Date(item.publishedAt * 1000).toISOString()
								"
								>{{ dateText(item.publishedAt) }}</time
							>
							<span v-if="item.endsAt" class="cc-board-chip"
								>{{ t('communications', 'Until') }}
								{{ dateText(item.endsAt) }}</span
							>
							<span
								v-if="item.source === 'contextual'"
								class="cc-board-chip"
								>{{ t('communications', 'Contextual notice') }}</span
							>
							<span
								v-if="item.category === 'sponsored'"
								class="cc-board-chip"
								>{{ t('communications', 'Sponsored') }}</span
							>
						</div>
						<h2>{{ item.headline }}</h2>
						<NcRichText
							:text="item.message"
							:use-markdown="true"
							:reference-limit="0" />
						<footer class="cc-board-article-actions">
							<NcButton
								:text="t('communications', 'Open message')"
								@click="openItem(item.id)">
								<template #icon
									><NcIconSvgWrapper
										:path="mdiCommentTextOutline"
										:size="18"
								/></template>
							</NcButton>
							<span v-if="item.commentsEnabled"
								>{{ item.commentCount }}
								{{ t('communications', 'comment(s)') }}</span
							>
						</footer>
					</article>
				</div>
				<nav
					v-if="total > 10"
					class="cc-board-pagination"
					:aria-label="t('communications', 'Message navigation')">
					<NcButton
						:text="t('communications', 'Previous')"
						:disabled="page <= 1"
						@click="changePage(-1)" />
					<span
						>{{ t('communications', 'Page') }} {{ page }}
						{{ t('communications', 'of') }}
						{{ Math.ceil(total / 10) }}</span
					>
					<NcButton
						:text="t('communications', 'Next')"
						:disabled="!hasMore"
						@click="changePage(1)" />
				</nav>
			</template>
			<template v-else>
				<NcButton
					:text="t('communications', 'Back to Inbox')"
					variant="tertiary"
					@click="closeItem">
					<template #icon
						><NcIconSvgWrapper :path="mdiArrowLeft" :size="18"
					/></template>
				</NcButton>
				<NcNoteCard
					v-if="selected.inInbox === false && selected.source === 'canal'"
					type="info"
					class="cc-direct-channel-note">
					{{
						t(
							'communications',
							'This message arrived through another channel and was not added to your Inbox.',
						)
					}}
				</NcNoteCard>
				<article
					class="cc-board-article cc-board-detail"
					:style="{ textAlign: selected.textAlign || 'left' }">
					<div class="cc-board-metadata">
						<span>{{ selected.author }}</span>
						<time
							:datetime="
								new Date(selected.publishedAt * 1000).toISOString()
							"
							>{{ dateText(selected.publishedAt) }}</time
						>
						<span v-if="selected.endsAt" class="cc-board-chip"
							>{{ t('communications', 'Until') }}
							{{ dateText(selected.endsAt) }}</span
						>
					</div>
					<h2 dir="auto">{{ selected.headline }}</h2>
					<NcRichText
						:text="selected.message"
						:use-markdown="true"
						:reference-limit="0" />
					<a
						v-if="selected.ctaLabel && safeLink(selected.ctaUrl)"
						class="cc-board-cta"
						:href="safeLink(selected.ctaUrl)"
						rel="noopener noreferrer"
						>{{ selected.ctaLabel }}</a
					>
				</article>
				<section
					v-if="selected.commentsEnabled"
					class="cc-board-comments"
					:aria-label="commentsAriaLabel(selected.headline)">
					<h2>
						{{ t('communications', 'Comments (')
						}}{{ selected.commentCount }})
					</h2>
					<form @submit.prevent="sendComment">
						<NcTextArea
							v-model="commentDraft"
							:label="t('communications', 'Write a comment')"
							:maxlength="4000"
							:rows="3"
							:placeholder="
								t(
									'communications',
									'Write your comment. Use @identifier to mention someone.',
								)
							" />
						<NcButton
							variant="primary"
							:text="t('communications', 'Post comment')"
							:disabled="commentBusy || !commentDraft.trim()"
							@click="sendComment" />
					</form>
					<div
						v-if="comments.length === 0"
						class="cc-board-empty-comments">
						{{ t('communications', 'No comments yet.') }}
					</div>
					<div
						v-for="comment in comments"
						:key="comment.id"
						class="cc-board-comment">
						<div class="cc-board-metadata">
							<strong>{{ comment.author }}</strong>
							<time
								:datetime="
									new Date(comment.createdAt * 1000).toISOString()
								"
								>{{ dateText(comment.createdAt) }}</time
							>
							<NcButton
								v-if="comment.canDelete"
								size="small"
								variant="tertiary"
								:text="t('communications', 'Delete')"
								:disabled="commentBusy"
								@click="removeComment(comment)" />
						</div>
						<NcRichText
							:text="comment.message"
							:use-markdown="true"
							:reference-limit="0" />
					</div>
					<NcButton
						v-if="commentHasMore"
						:text="t('communications', 'Load earlier comments')"
						@click="loadComments(true)" />
				</section>
			</template>
		</main>
	</section>
</template>

<style lang="scss" scoped>
.cc-board-app {
	width: 100%;
	max-width: none;
	overflow: auto;
}

.cc-board-main {
	max-width: 980px;
	margin: 0 auto;
	padding: 28px clamp(12px, 4vw, 40px) 64px;
}

.cc-board-heading {
	display: flex;
	align-items: center;
	gap: 17px;
	margin-bottom: 26px;
}

.cc-board-heading h1 {
	font-size: clamp(25px, 2.5vw, 34px);
	margin: 0 0 6px;
}

.cc-board-heading p {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.cc-board-search {
	display: flex;
	align-items: flex-end;
	gap: 12px;
	margin: 0 0 22px;
}

.cc-board-search > div {
	flex: 1;
}

.cc-board-article {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 12px);
	padding: 22px 24px;
	margin-bottom: 16px;
	min-width: 0;
	overflow-wrap: anywhere;
}

.cc-board-article h2 {
	font-size: 21px;
	margin: 12px 0 15px;
}

.cc-board-article :deep(.rich-text--wrapper img) {
	max-width: 100%;
	height: auto;
}

.cc-board-metadata {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	align-items: center;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.cc-board-chip {
	border: 1px solid var(--color-border);
	border-radius: 15px;
	padding: 2px 9px;
	background: var(--color-background-hover);
}

.cc-board-article-actions {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 12px;
	margin-top: 18px;
	padding-top: 12px;
	border-top: 1px solid var(--color-border);
}

.cc-board-article-actions > span {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.cc-board-pagination {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 15px;
	margin-top: 25px;
}

.cc-board-comments {
	margin-top: 28px;
}

.cc-board-comments h2 {
	font-size: 20px;
	margin: 0 0 18px;
}

.cc-board-comments form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-bottom: 20px;
}

.cc-board-comments form > button {
	align-self: flex-end;
}

.cc-board-comment {
	padding: 17px 0;
	border-bottom: 1px solid var(--color-border);
}

.cc-board-comment :deep(.rich-text--wrapper) {
	margin-top: 10px;
}

.cc-board-cta {
	display: inline-block;
	color: var(--color-primary-element);
	margin-top: 16px;
}

.cc-board-empty-comments {
	color: var(--color-text-maxcontrast);
	padding: 16px 0;
}
@media (max-width: 600px) {
	.cc-board-main {
		padding: 18px 12px 50px;
	}
	.cc-board-article {
		padding: 14px;
	}
	.cc-board-search {
		flex-wrap: wrap;
	}
	.cc-board-search > button {
		width: 100%;
	}
}

.cc-inbox-filters {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	margin: -5px 0 20px;
}

.cc-board-unread {
	border-inline-start: 4px solid var(--color-primary-element);
}

.cc-unread-label {
	color: var(--color-primary-element);
	font-weight: 700;
}

.cc-board-article-actions > button {
	max-width: 100%;
}

.cc-mention-choices {
	display: flex;
	flex-wrap: wrap;
	gap: 5px;
	padding: 6px 0 12px;
	border-bottom: 1px solid var(--color-border);
}

.cc-mention-choices button {
	max-width: 100%;
}

/* Recipient-side opt-in stays within the Inbox rather than a third app area. */
.cc-board-heading {
	flex-wrap: wrap;
	justify-content: space-between;
}

.cc-gateway-preferences-button {
	margin-inline-start: auto;
}

.cc-gateway-preferences {
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 18px 20px;
	margin: -8px 0 22px;
	background: var(--color-main-background);
}

.cc-gateway-preferences h3 {
	font-size: 16px;
	margin: 0 0 7px;
}

.cc-gateway-preferences > p {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	margin: 0 0 14px;
}

.cc-gateway-preference {
	padding: 11px 0;
	border-bottom: 1px solid var(--color-border);
	min-width: 0;
}

.cc-gateway-preference-heading {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 12px;
}

.cc-gateway-preference-heading > span {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	text-align: end;
}

.cc-gateway-preference > div:not(.cc-gateway-preference-heading) {
	margin: 8px 0;
}

.cc-gateway-preference :deep(.checkbox-radio-switch) {
	margin: 7px 0;
}

.cc-gateway-preferences-hint {
	font-size: 12px !important;
}
@media (max-width: 600px) {
	.cc-gateway-preferences {
		padding: 13px;
	}
	.cc-gateway-preference-heading {
		flex-wrap: wrap;
		align-items: flex-start;
	}
	.cc-gateway-preference-heading > span {
		margin-inline-start: auto;
	}
}

/* A direct-only link remains readable but is not a copy in the Inbox. */
.cc-direct-channel-note {
	margin: 12px 0;
}

.cc-hub-board .cc-board-main {
	padding-top: 24px;
}

.cc-hub-board .cc-board-heading {
	margin-bottom: 18px;
}

.cc-hub-board .cc-board-heading h2 {
	font-size: 21px;
	margin: 0 0 6px;
}

.cc-hub-admin .cc-native-app,
.cc-hub-board .cc-board-app {
	min-height: 0;
	overflow: visible;
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

@media (max-width: 640px) {
	.cc-hub-board .cc-board-main {
		padding: 16px 12px 36px;
	}
}
</style>
