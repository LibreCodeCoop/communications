// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { test, expect } from '@playwright/test'
import { randomUUID } from 'node:crypto'

const USERS = {
  admin: process.env.NC_ADMIN_USER || 'qa_admin',
  recipient: process.env.NC_RECIPIENT_USER || 'poc_second',
  unrelated: process.env.NC_UNRELATED_USER || 'qa_unrelated',
}
const password = process.env.NC_TEST_PASSWORD

async function signIn(page, uid) {
  if (!password) throw new Error('NC_TEST_PASSWORD is required for isolated tests')
  await page.goto('/index.php/login')
  await page.locator('#user').fill(uid)
  await page.locator('#password').fill(password)
  await Promise.all([
    page.waitForURL(url => !url.pathname.endsWith('/login'), { timeout: 30000 }),
    page.locator('button[type="submit"]').click(),
  ])
  await page.goto('/index.php/apps/communications/')
  await expect(page.locator('.cc-hub-tabs')).toBeVisible()
}

async function request(page, method, route, data) {
  return page.evaluate(async ({ method, route, data }) => {
    const token = window.OC?.requestToken || document.querySelector('[data-requesttoken]')?.getAttribute('data-requesttoken')
    const r = await fetch('/index.php/apps/communications' + route, {
      method, credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'requesttoken': token || '', ...(data === undefined ? {} : { 'Content-Type': 'application/json' }) },
      ...(data === undefined ? {} : { body: JSON.stringify(data) }),
    })
    return { status: r.status, body: await r.text() }
  }, { method, route, data })
}

test('publication and audience isolation are persisted by the selected database', async ({ browser }) => {
  const contexts = await Promise.all([browser.newContext(), browser.newContext(), browser.newContext()])
  const [admin, recipient, unrelated] = await Promise.all(contexts.map(c => c.newPage()))
  let created
  try {
    await signIn(admin, USERS.admin)
    await signIn(recipient, USERS.recipient)
    await signIn(unrelated, USERS.unrelated)
    const name = `QA DB audience ${randomUUID()}`
    const createdResponse = await request(admin, 'POST', '/api/campaigns', {
      name, headline: name, message: 'An isolated publication for a test recipient',
      trigger_key: 'nextcloud.board.published', presentation: 'none', board_visible: true,
      status: 'active', category: 'institutional', audience_type: 'users',
      audience_users: [USERS.recipient], placement: 'any', frequency: 'once',
      notify_users: false, notify_email: false, notify_activity: false,
      priority: 10, starts_at: null, ends_at: null,
    })
    expect(createdResponse.status, createdResponse.body.slice(0, 350)).toBe(201)
    created = JSON.parse(createdResponse.body).campaign
    const allowed = await request(recipient, 'GET', `/api/board/${created.id}`)
    expect(allowed.status, allowed.body.slice(0, 350)).toBe(200)
    const forbidden = await request(unrelated, 'GET', `/api/board/${created.id}`)
    expect(forbidden.status, 'Non-recipient must never see publication').toBe(404)
    expect((await request(admin, 'GET', `/api/board/${created.id}`)).status).toBe(404)
    await recipient.goto(`/index.php/apps/communications/board?id=${created.id}`)
    await expect(recipient.getByText(name).first()).toBeVisible()
  } finally {
    if (created) {
      const archived = await request(admin, 'DELETE', `/api/campaigns/${created.id}`, {})
      expect(archived.status).toBe(200)
      const removed = await request(admin, 'POST', `/api/campaigns/${created.id}/purge`, { confirmName: created.name })
      expect(removed.status).toBe(200)
    }
    await Promise.all(contexts.map(c => c.close()))
  }
})
