// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
// Match CtaUrl::isAllowed without executing or rendering untrusted link schemes.
function isSafeLinkText(value: unknown): value is string {
	return (
		typeof value === 'string'
		&& value.length > 0
		&& value.length <= 1900
		// eslint-disable-next-line no-control-regex
		&& !/[\x00-\x20\x7f"'<>\x60\\]/.test(value)
	)
}

function nextcloudPath(value: string): string {
	if (value.startsWith('//')) return ''
	const webroot = window.OC?.webroot || ''
	return webroot && !value.startsWith(webroot + '/') ? webroot + value : value
}

function safeHttpLink(value: string): string {
	try {
		const parsed = new URL(value)
		if (!['https:', 'http:'].includes(parsed.protocol)) return ''
		if (!parsed.hostname || parsed.username || parsed.password) return ''
		return value
	} catch {
		return ''
	}
}

export function safeCtaLink(value: unknown): string {
	if (!isSafeLinkText(value)) return ''
	if (value.startsWith('/')) return nextcloudPath(value)
	if (/^https?:\/\//i.test(value)) return safeHttpLink(value)
	if (/^mailto:[^@\s]+@[^@\s]+$/i.test(value)) return value
	if (/^tel:\+?[0-9().-]{3,30}$/i.test(value)) return value
	return ''
}
