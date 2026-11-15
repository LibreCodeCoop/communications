// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { test, expect } from '@playwright/test'

test('automation selection exposes app pages and external integration without saving a campaign', async ({
	page,
}) => {
	test.setTimeout(60_000)
	const password = process.env.NC_TEST_PASSWORD
	if (!password) throw new Error('Disposable Nextcloud credentials are required')
	await page.goto('/index.php/login')
	await page.locator('#user').fill(process.env.NC_ADMIN_USER || 'qa_admin')
	await page.locator('#password').fill(password)
	await Promise.all([
		page.waitForURL((url) => !url.pathname.endsWith('/login')),
		page.locator('button[type="submit"]').click(),
	])
	await page.goto('/index.php/apps/communications/')
	await page
		.locator('.cc-hub-tabs')
		.getByRole('button', { name: 'Publications' })
		.click()
	await page
		.locator('.cc-hub-top')
		.getByRole('button', { name: 'New publication' })
		.click()

	const triggers = page.locator('.cc-automation-intents')
	await triggers.getByRole('button', { name: 'In an app' }).click()
	await expect(page.locator('label[for="cc-app-source"]')).toHaveText(
		'Choose an application *',
	)
	await expect(
		page.getByRole('button', { name: 'Help: selecting applications' }),
	).toBeVisible()

	await page.locator('#cc-app-source').click()
	// Choose by declared capability, not by app name: an events-only app has no page mode.
	const pageCapableApp = page
		.locator('.cc-automation-option')
		.filter({ hasText: /Events and pages|Pages available/ })
		.first()
	await expect(pageCapableApp).toBeVisible()
	await pageCapableApp.click()
	// An app with pages only enters visit mode directly. A dual-capability app exposes a mode switch.
	const visitMode = page.getByText('Page visit', { exact: true })
	if (await visitMode.isVisible()) await visitMode.click()
	await expect(page.locator('label[for="cc-app-route"]')).toHaveText(
		'Web page or route *',
	)

	await triggers
		.getByRole('button', { name: 'Advanced rule or integration' })
		.click()
	await expect(page.locator('label[for="cc-advanced"]')).toHaveText(
		'Event source *',
	)
	await expect(
		page.getByText('Integration or Flow', { exact: true }),
	).toBeVisible()
})
