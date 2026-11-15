<!--
  SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { requestApi } from './services/api'
import { generateUrl } from '@nextcloud/router'
import { nextTick, onMounted, onUnmounted, ref } from 'vue'
import CampaignPresentation from './components/CampaignPresentation.vue'
import type { ClaimedPresentation, ClaimBatchResponse } from './types/communications'

const current = ref<ClaimedPresentation | null>(null)
const banners = ref<ClaimedPresentation[]>([])
const MAX_BANNERS = 5
let busy = false
const pendingDismissals = new Set<number>()
let polling: number | null = null
let updates: BroadcastChannel | null = null
let firstVisit = true
let lastApp: string | null = null
let lastPath: string | null = null

function appContext(): string {
	const path = window.location.pathname
	const match = path.match(/\/(?:index\.php\/)?apps\/([a-z0-9_-]{2,32})(?:\/|$)/)
	if (match?.[1]) return match[1]
	if (/\/settings\/admin(?:\/|$)/.test(path)) return 'admin_settings'
	if (/\/settings(?:\/|$)/.test(path)) return 'settings'
	return 'any'
}

async function post(
	path: string,
	data: Record<string, unknown>,
	keepalive = false,
): Promise<ClaimBatchResponse> {
	if (!keepalive) return requestApi<ClaimBatchResponse>(path, 'POST', data)
	// The rare keepalive acknowledgement still needs native fetch: Axios uses
	// XHR, which cannot guarantee keepalive behavior during page navigation.
	const response = await fetch(generateUrl('/apps/communications' + path), {
		method: 'POST',
		credentials: 'same-origin',
		keepalive,
		headers: {
			'Content-Type': 'application/json',
			requesttoken: window.OC?.requestToken || window.oc_requesttoken || '',
		},
		body: JSON.stringify(data),
	})
	if (!response.ok) throw new Error('Campaign API HTTP ' + response.status)
	return response.json()
}

async function impressions(items: ClaimedPresentation[]): Promise<void> {
	if (!items.length) return
	await nextTick()
	await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
	for (const item of items) {
		try {
			await post('/api/impression', {
				deliveryId: item.deliveryId,
				claimToken: item.claimToken,
			})
		} catch (error) {
			console.warn('[communications] impression unavailable:', error)
		}
	}
}

async function claim(): Promise<void> {
	if (
		busy
		|| current.value !== null
		|| document.visibilityState === 'hidden'
		|| banners.value.length >= MAX_BANNERS
	)
		return
	busy = true
	const context = appContext()
	const path = location.pathname
	const freshVisit = firstVisit || lastApp !== context || lastPath !== path
	try {
		const response = await post('/api/claim-batch', {
			page: context,
			path,
			newVisit: freshVisit,
			limit: MAX_BANNERS - banners.value.length,
		})
		firstVisit = false
		lastApp = context
		lastPath = path
		const newItems: ClaimedPresentation[] = []
		if (response.campaign && !current.value) {
			current.value = response.campaign
			newItems.push(response.campaign)
		}
		for (const item of response.campaigns || []) {
			if (banners.value.some((c) => c.deliveryId === item.deliveryId)) continue
			if (banners.value.length >= MAX_BANNERS) break
			banners.value.push(item)
			newItems.push(item)
		}
		await impressions(newItems)
	} catch (error) {
		console.warn('[communications] delivery check failed:', error)
	} finally {
		busy = false
	}
}

async function finish(
	item: ClaimedPresentation | null,
	action: 'dismiss' | 'click' = 'dismiss',
): Promise<void> {
	// A click on a second banner must not be discarded while the first one
	// is being acknowledged or another batch is refreshing.
	if (!item || pendingDismissals.has(item.deliveryId)) return
	pendingDismissals.add(item.deliveryId)
	if (current.value?.deliveryId === item.deliveryId) current.value = null
	else
		banners.value = banners.value.filter((c) => c.deliveryId !== item.deliveryId)
	try {
		await post(
			action === 'click' ? '/api/click' : '/api/ack',
			{
				deliveryId: item.deliveryId,
				claimToken: item.claimToken,
			},
			true,
		)
	} catch (error) {
		console.warn('[communications] campaign acknowledgement failed:', error)
	} finally {
		pendingDismissals.delete(item.deliveryId)
		void claim()
	}
}

async function sponsoredOptout(item: ClaimedPresentation | null): Promise<void> {
	if (!item) return
	try {
		await post('/api/preferences', { sponsoredOptout: true })
		const blocked = banners.value.filter((c) => c.category === 'sponsored')
		if (current.value?.category === 'sponsored') blocked.push(current.value)
		banners.value = banners.value.filter((c) => c.category !== 'sponsored')
		if (current.value?.category === 'sponsored') current.value = null
		// Every previously shown sponsored banner is explicitly acknowledged.
		for (const c of blocked) {
			await post(
				'/api/ack',
				{ deliveryId: c.deliveryId, claimToken: c.claimToken },
				true,
			)
		}
		void claim()
	} catch (error) {
		console.warn('[communications] sponsored preference failed:', error)
	}
}

function onVisible() {
	if (document.visibilityState === 'visible') void claim()
}
function onChange() {
	void claim()
}
function onStorage(event: StorageEvent): void {
	if (event.key === 'communications-campaign-update') void claim()
}

onMounted(() => {
	if (document.body.id !== 'body-user') return
	if (window.location.pathname.includes('/apps/communications/')) return
	const initial = window.__contextualCampaignInitial
	if (initial?.deliveryId && initial?.claimToken) {
		delete window.__contextualCampaignInitial
		firstVisit = false
		lastApp = appContext()
		lastPath = location.pathname
		if (initial.presentation === 'modal') current.value = initial
		else banners.value = [initial]
		void impressions([initial]).then(() => claim())
	} else {
		void claim()
	}
	polling = window.setInterval(() => void claim(), 30000)
	document.addEventListener('visibilitychange', onVisible)
	window.addEventListener('popstate', onChange)
	window.addEventListener('hashchange', onChange)
	document.addEventListener('nextcloud.navigation.entered', onChange)
	window.addEventListener('storage', onStorage)
	if (typeof BroadcastChannel !== 'undefined') {
		updates = new BroadcastChannel('communications')
		updates.onmessage = (event) => {
			if (event.data?.event === 'changed') void claim()
		}
	}
})
onUnmounted(() => {
	if (polling !== null) window.clearInterval(polling)
	document.removeEventListener('visibilitychange', onVisible)
	window.removeEventListener('popstate', onChange)
	window.removeEventListener('hashchange', onChange)
	document.removeEventListener('nextcloud.navigation.entered', onChange)
	window.removeEventListener('storage', onStorage)
	if (updates) updates.close()
})
</script>

<template>
	<!-- TRANSLATORS Short label for an option or field in the delivery interface. -->
	<section
		v-if="banners.length"
		class="cc-live-stack"
		role="region"
		:aria-label="t('communications', 'Available announcements')">
		<CampaignPresentation
			v-for="item in banners"
			:key="item.deliveryId"
			:campaign="item"
			:open="true"
			@close="finish(item, 'dismiss')"
			@cta="finish(item, 'click')"
			@optout="sponsoredOptout(item)" />
	</section>
	<CampaignPresentation
		v-if="current"
		:campaign="current"
		:open="true"
		@close="finish(current, 'dismiss')"
		@cta="finish(current, 'click')"
		@optout="sponsoredOptout(current)" />
</template>

<style lang="scss" scoped>
.cc-live-stack {
	position: fixed;
	top: calc(var(--header-height, 50px) + 13px);
	inset-inline-start: 50%;
	transform: translateX(-50%);
	width: min(670px, 95vw);
	max-height: calc(100vh - var(--header-height, 50px) - 28px);
	z-index: 1100;
	display: flex;
	flex-direction: column;
	gap: 12px;
	overflow: auto;
	scrollbar-gutter: stable;

	:deep(.cc-live-banner) {
		position: relative;
		flex-shrink: 0;
		top: auto;
		inset-inline-start: auto;
		transform: none;
		width: 100%;
		max-height: none;
		overflow: visible;
	}
}
</style>
