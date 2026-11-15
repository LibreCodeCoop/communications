// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
/* SPDX-License-Identifier: AGPL-3.0-or-later
 * Lightweight Nextcloud page adapter.
 * Vue delivery and its CSS are loaded only if a server-side campaign is eligible.
 * There are no external requests, trackers or advertisement networks.
 */
;(() => {
	'use strict'

	const originScript = document.currentScript?.src
	if (!originScript) return
	const loaderUrl = new URL(originScript, location.href)
	const jsDirectory = loaderUrl.pathname.replace(/\/loader\.js$/, '/')
	if (jsDirectory === loaderUrl.pathname) return
	const stylesheetUrl = new URL(
		jsDirectory.replace(/\/js\/$/, '/css/') + 'campaigns.css',
		location.origin,
	)
	const bundleUrl = new URL(jsDirectory + 'campaigns.js', location.origin)
	// Only use code delivered by this Nextcloud installation.
	if (
		stylesheetUrl.origin !== location.origin
		|| bundleUrl.origin !== location.origin
	)
		return

	const base = (window.OC?.webroot || '') + '/index.php/apps/communications'
	let firstVisit = true
	let lastApp = null
	let lastPath = null
	let busy = false
	let loaded = false
	let timer = null
	let retries = 0
	let retryTimer = null
	let updates = null

	function onChange() {
		void scan()
	}
	function onStorage(event) {
		if (event.key === 'communications-campaign-update') void scan()
	}

	function appContext() {
		const match = location.pathname.match(
			/\/(?:index\.php\/)?apps\/([a-z0-9_-]{2,32})(?:\/|$)/,
		)
		if (match) return match[1]
		if (/\/settings\/admin(?:\/|$)/.test(location.pathname))
			return 'admin_settings'
		if (/\/settings(?:\/|$)/.test(location.pathname)) return 'settings'
		return 'any'
	}

	function stop() {
		if (timer !== null) clearInterval(timer)
		if (retryTimer !== null) clearTimeout(retryTimer)
		retryTimer = null
		document.removeEventListener('visibilitychange', onVisible)
		window.removeEventListener('popstate', scan)
		window.removeEventListener('hashchange', scan)
		document.removeEventListener('nextcloud.navigation.entered', scan)
		window.removeEventListener('storage', onStorage)
		if (updates) {
			updates.close()
			updates = null
		}
	}

	function inject() {
		return new Promise((resolve, reject) => {
			if (!document.querySelector('link[data-communications-delivery]')) {
				const css = document.createElement('link')
				css.rel = 'stylesheet'
				css.href = stylesheetUrl.href
				css.dataset.communicationsDelivery = '1'
				document.head.appendChild(css)
			}
			const script = document.createElement('script')
			script.async = true
			script.src = bundleUrl.href
			script.onload = resolve
			script.onerror = () => reject(new Error('Campaign bundle unavailable'))
			document.head.appendChild(script)
		})
	}

	function canScan() {
		return (
			!loaded
			&& !busy
			&& document.visibilityState !== 'hidden'
			&& document.body.id === 'body-user'
			&& !location.pathname.includes('/apps/communications/')
		)
	}

	async function claimOnVisit(context, path, newVisit) {
		const response = await fetch(base + '/api/claim', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				requesttoken:
					window.OC?.requestToken || window.oc_requesttoken || '',
			},
			body: JSON.stringify({ page: context, path, newVisit }),
		})
		if (!response.ok) throw new Error('Claim HTTP ' + response.status)
		return response.json()
	}

	function recordVisit(context, path) {
		retries = 0
		if (retryTimer !== null) {
			clearTimeout(retryTimer)
			retryTimer = null
		}
		firstVisit = false
		lastApp = context
		lastPath = path
	}

	async function presentClaimedCampaign(campaign) {
		loaded = true
		window.__contextualCampaignInitial = campaign
		stop()
		try {
			await inject()
		} catch (error) {
			delete window.__contextualCampaignInitial
			loaded = false
			timer = setInterval(scan, 30000)
			console.warn('[communications] lazy loader failed:', error)
		}
	}

	function retryFailedPreflight() {
		// Retry only unclaimed visits while Nextcloud's CSRF token is initializing.
		if (loaded || retries >= 4 || retryTimer !== null) return
		const delay = Math.min(1000 * 2 ** retries, 8000)
		retries++
		retryTimer = setTimeout(() => {
			retryTimer = null
			void scan()
		}, delay)
	}

	async function scan() {
		if (!canScan()) return
		busy = true
		const context = appContext()
		const path = location.pathname
		const newVisit = firstVisit || context !== lastApp || path !== lastPath
		try {
			const data = await claimOnVisit(context, path, newVisit)
			recordVisit(context, path)
			if (data.campaign) await presentClaimedCampaign(data.campaign)
		} catch (error) {
			retryFailedPreflight()
			console.warn('[communications] preflight unavailable:', error)
		} finally {
			busy = false
		}
	}

	function onVisible() {
		if (document.visibilityState === 'visible') void scan()
	}
	function start() {
		if (document.body.id !== 'body-user') return
		if (location.pathname.includes('/apps/communications/')) return
		void scan()
		timer = setInterval(scan, 30000)
		document.addEventListener('visibilitychange', onVisible)
		window.addEventListener('popstate', scan)
		window.addEventListener('hashchange', scan)
		document.addEventListener('nextcloud.navigation.entered', scan)
		window.addEventListener('storage', onStorage)
		if (typeof BroadcastChannel !== 'undefined') {
			updates = new BroadcastChannel('communications')
			updates.onmessage = (event) => {
				if (event.data?.event === 'changed') void scan()
			}
		}
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, { once: true })
	} else {
		start()
	}
})()
