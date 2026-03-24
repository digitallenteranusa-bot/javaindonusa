import { test, expect } from '@playwright/test'

test.describe('Smoke Tests', () => {
    test('admin login page loads', async ({ page }) => {
        await page.goto('/login')
        await expect(page).toHaveTitle(/Login|Masuk/)
        await expect(page.locator('input[type="email"], input[name="email"], input[name="username"]')).toBeVisible()
        await expect(page.locator('input[type="password"]')).toBeVisible()
    })

    test('customer portal login page loads', async ({ page }) => {
        await page.goto('/portal/login')
        await expect(page).toHaveTitle(/Login|Masuk|Portal/)
        await expect(page.locator('input[type="tel"], input[name="phone"]')).toBeVisible()
    })

    test('admin login with invalid credentials shows error', async ({ page }) => {
        await page.goto('/login')
        await page.fill('input[type="email"], input[name="email"], input[name="username"]', 'wrong@test.com')
        await page.fill('input[type="password"]', 'wrongpassword')
        await page.click('button[type="submit"]')
        // Should stay on login page or show error
        await expect(page).toHaveURL(/login/)
    })

    test('unauthenticated admin access redirects to login', async ({ page }) => {
        await page.goto('/admin')
        await expect(page).toHaveURL(/login/)
    })

    test('unauthenticated portal access redirects to login', async ({ page }) => {
        await page.goto('/portal')
        await expect(page).toHaveURL(/login/)
    })

    test('health check endpoint responds', async ({ page }) => {
        const response = await page.goto('/up')
        expect(response.status()).toBe(200)
    })
})
