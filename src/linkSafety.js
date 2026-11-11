// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
// Same allowlist as CtaUrl::isAllowed; never render unsafe saved legacy schemes.
export function safeCtaLink(value) {
  if (typeof value !== 'string' || !value || value.length > 1900
      // eslint-disable-next-line no-control-regex
      || /[\x00-\x20\x7f"'<>\x60\\]/.test(value)) return ''
  if (value.startsWith('/')) {
    if (value.startsWith('//')) return ''
    const webroot=window.OC?.webroot||''
    return webroot && !value.startsWith(webroot+'/') ? webroot+value : value
  }
  if (/^https?:\/\//i.test(value)) {
    try {
      const parsed=new URL(value)
      if (!['https:','http:'].includes(parsed.protocol) || !parsed.hostname
          || parsed.username || parsed.password) return ''
      return value
    } catch { return '' }
  }
  if (/^mailto:[^@\s]+@[^@\s]+$/i.test(value)) return value
  if (/^tel:\+?[0-9().-]{3,30}$/i.test(value)) return value
  return ''
}
