// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { test, expect } from '@playwright/test'

test('publication editor keeps local state when navigating between Inbox and Publications', async ({
	page,
}) => {
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
	await expect(page.locator('.cc-hub-tabs')).toBeVisible()
	await page
		.locator('.cc-hub-tabs')
		.getByRole('button', { name: 'Publications' })
		.click()
	await expect(page.locator('.cc-native-heading-overview')).toBeVisible()
	await page
		.locator('.cc-hub-top')
		.getByRole('button', { name: 'New publication' })
		.click()
	await expect(page.locator('.cc-native-heading-editor')).toBeVisible()
	await page
		.locator('.cc-automation-intents')
		.getByRole('button', { name: 'On publication activation' })
		.click()
	await page
		.locator('.cc-wizard-footer')
		.getByRole('button', { name: 'Continue: Message and audience' })
		.click()
	const nameField = page.getByLabel('Internal name *')
	await nameField.fill('Unsaved draft survives tab navigation')
	// Extracted content, audience, delivery and preview boundaries stay mounted.
	await expect(page.getByLabel('Displayed title *')).toBeVisible()
	await expect(page.getByRole('heading', { name: 'Recipients' })).toBeVisible()
	await expect(
		page.getByRole('heading', { name: 'Delivery methods' }),
	).toBeVisible()
	await expect(page.locator('.cc-wizard-sidebar')).toBeVisible()

	// No publication is persisted; navigation must preserve the typed draft and its wizard.
	await page.locator('.cc-hub-tabs').getByRole('button', { name: 'Inbox' }).click()
	await expect(page.locator('.cc-native-heading-editor')).toBeHidden()
	await page
		.locator('.cc-hub-tabs')
		.getByRole('button', { name: 'Publications' })
		.click()
	await expect(page.locator('.cc-native-heading-editor')).toBeVisible()
	await expect(nameField).toHaveValue('Unsaved draft survives tab navigation')
})
