// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
/**
 * Compatibility acceptance for a third-party event source. The application
 * contains NO LibreSign-specific adapter: the standard native-event discovery,
 * subscriber and delivery paths are tested against a LibreSign fixture.
 *
 * The fixture represents a real LibreSign *event type* and a synthetic state
 * transition; it does not create or sign a genuine PDF. Never dispatch such
 * a synthetic event globally, as that could run unrelated LibreSign callbacks.
 */
import { test, expect } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { randomUUID, createHash } from 'node:crypto'

const APP_ID = 'communications'
const APP_PATH = `/index.php/apps/${APP_ID}`
const EVENT = 'OCA\\Libresign\\Events\\SigningRequestCompletedEvent'
const EVENT_KEY = 'php_event.' + createHash('sha256').update(EVENT).digest('hex').slice(0, 24)
const CONTAINER = process.env.NC_CONTAINER || 'communications-nc36-qa'
const ADMIN = process.env.NC_ADMIN_USER || 'qa_admin'
const RECIPIENT = process.env.NC_RECIPIENT_USER || 'poc_second'
const UNRELATED = process.env.NC_UNRELATED_USER || 'qa_unrelated'
const PASSWORD = process.env.NC_TEST_PASSWORD

function requireFixturePassword() {
  if (!PASSWORD) throw new Error('NC_TEST_PASSWORD is required for isolated acceptance tests')
}

async function login(page, user) {
  requireFixturePassword()
  await page.goto('/index.php/login')
  await page.locator('#user').fill(user)
  await page.locator('#password').fill(PASSWORD)
  await Promise.all([
    page.waitForURL(url => !url.pathname.endsWith('/login'), { timeout: 30000 }),
    page.locator('button[type="submit"]').click(),
  ])
}

async function appApi(page, method, route, body = undefined) {
  const token = await page.evaluate(() => window.OC?.requestToken
    || document.querySelector('[data-requesttoken]')?.getAttribute('data-requesttoken'))
  return page.evaluate(async ({ method, route, body, token }) => {
    const response = await fetch(`/index.php/apps/${'communications'}${route}`, {
      method,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        requesttoken: token || '',
        ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
      },
      ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    })
    return { status: response.status, body: await response.text() }
  }, { method, route, body, token })
}

function fixturePHP() {
  return JSON.parse(execFileSync('docker', [
    'exec', '-u', 'www-data', CONTAINER, 'php',
    `/var/www/html/custom_apps/${APP_ID}/tests/manual/mvp_libresign_native_event_v29.php`,
  ], { encoding: 'utf8', timeout: 60000 }))
}

function runCron() {
  execFileSync('docker', ['exec', '-u', 'www-data', CONTAINER,
    'php', '/var/www/html/cron.php'], { encoding: 'utf8', timeout: 75000 })
}

test('LibreSign completion is discovered and delivered through generic native-event flow', async ({ browser }) => {
  const adminContext = await browser.newContext()
  const admin = await adminContext.newPage()
  const recipientContext = await browser.newContext()
  const recipient = await recipientContext.newPage()
  const unrelatedContext = await browser.newContext()
  const unrelated = await unrelatedContext.newPage()
  let publication

  try {
    await login(admin, ADMIN)
    await admin.goto(`${APP_PATH}/`)
    await expect(admin.locator('.cc-hub-tabs')).toBeVisible()

    const catalogResponse = await appApi(admin, 'GET', '/api/catalog')
    expect(catalogResponse.status).toBe(200)
    const catalog = JSON.parse(catalogResponse.body)
    const source = catalog.automation.applications.find(app => app.id === 'libresign')
    expect(source, 'LibreSign app discovered without a built-in adapter').toBeDefined()
    expect(source.actions).toEqual(expect.arrayContaining([
      expect.objectContaining({ eventClass: EVENT, key: EVENT_KEY }),
    ]))
    expect(source.actions.some(action => action.key === 'libresign.signer.signed')).toBe(false)

    // Exercise the real UI: the event must be selectable using the same
    // application/event controls as any other enabled Nextcloud app.
    await admin.locator('.cc-hub-tabs button').filter({ hasText: 'Publicações' }).click()
    await admin.locator('.cc-hub-top button').filter({ hasText: 'Nova publicação' }).click()
    await admin.locator('.cc-automation-intent-card').filter({ hasText: 'Em um aplicativo' }).click()
    await admin.locator('#cc-app-source').click()
    await admin.getByRole('option').filter({ hasText: 'LibreSign' }).first().click()
    await expect(admin.locator('#cc-app-event')).toBeVisible()
    await admin.locator('#cc-app-event').click()
    await expect(admin.getByRole('option').filter({ hasText: 'SigningRequestCompletedEvent' }).first()).toBeVisible()
    await admin.getByRole('option').filter({ hasText: 'SigningRequestCompletedEvent' }).first().click()
    await expect(admin.locator('.cc-automation')).toContainText('SigningRequestCompletedEvent')

    const name = `QA LibreSign event ${randomUUID()}`
    const created = await appApi(admin, 'POST', '/api/campaigns', {
      name, headline: name, message: 'A signed document workflow has completed',
      trigger_key: EVENT_KEY, placement: 'libresign', status: 'active',
      presentation: 'none', board_visible: true, notify_email: false,
      notify_users: false, notify_activity: false, gateway_channels: [],
      audience_type: 'users', audience_users: [RECIPIENT],
      category: 'institutional', frequency: 'event', priority: 10,
      starts_at: null, ends_at: null,
    })
    expect(created.status, created.body.slice(0, 600)).toBe(201)
    publication = JSON.parse(created.body).campaign

    await login(recipient, RECIPIENT)
    await recipient.goto(`${APP_PATH}/`)
    const before = await appApi(recipient, 'GET', `/api/board/${publication.id}`)
    expect(before.status, 'Event-only publication must not leak before trigger').toBe(404)

    const event = fixturePHP()
    expect(event.trigger).toBe(EVENT_KEY)
    expect(event.user).toBe(RECIPIENT)
    expect(event.context).toBe('libresign')
    expect(event.webhookContract.status).toBe('completed')
    expect(JSON.stringify(event.webhookContract)).not.toContain(RECIPIENT)
    runCron()

    const received = await appApi(recipient, 'GET', `/api/board/${publication.id}`)
    expect(received.status, received.body.slice(0, 450)).toBe(200)
    await recipient.goto(`${APP_PATH}/board?id=${publication.id}`)
    await expect(recipient.getByText(name).first()).toBeVisible()

    await login(unrelated, UNRELATED)
    await unrelated.goto(`${APP_PATH}/`)
    const forbidden = await appApi(unrelated, 'GET', `/api/board/${publication.id}`)
    expect(forbidden.status, 'Unrelated user cannot read private event').toBe(404)
  } finally {
    if (publication) {
      const archived = await appApi(admin, 'DELETE', `/api/campaigns/${publication.id}`, {})
      expect(archived.status).toBe(200)
      const purged = await appApi(admin, 'POST', `/api/campaigns/${publication.id}/purge`,
        { confirmName: publication.name })
      expect(purged.status).toBe(200)
    }
    await Promise.all([adminContext.close(), recipientContext.close(), unrelatedContext.close()])
  }
})
