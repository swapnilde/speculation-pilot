const { test, expect } = require( '@playwright/test' );

const user = process.env.WP_ADMIN_USER || 'admin';
const password = process.env.WP_ADMIN_PASSWORD || 'password';

async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( user );
	await page.locator( '#user_pass' ).fill( password );
	await page.locator( '#wp-submit' ).click();
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}

test.describe( 'Speculation Pilot admin', () => {
	test( 'loads settings screens and diagnostics', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/options-general.php?page=speculation-pilot' );

		await expect( page.getByRole( 'heading', { name: 'Speculation Pilot' } ) ).toBeVisible();
		await expect( page.getByText( 'Current mode' ) ).toBeVisible();

		await page.getByRole( 'button', { name: 'Diagnostics' } ).click();
		await expect( page.getByText( 'Overall status' ) ).toBeVisible();
		await expect( page.getByText( 'Link opt-out selectors' ) ).toBeVisible();

		await page.getByRole( 'button', { name: 'Measurements' } ).click();
		await expect( page.getByText( 'Daily p75 duration' ) ).toBeVisible();
		await expect( page.getByText( 'Path groups' ) ).toBeVisible();
	} );

	test( 'shows Plan metric on overview', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/options-general.php?page=speculation-pilot' );

		await expect( page.getByText( 'Plan' ) ).toBeVisible();
	} );

	test( 'shows License section in settings tab', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/options-general.php?page=speculation-pilot' );

		await page.getByRole( 'button', { name: 'Settings' } ).click();
		await expect( page.getByRole( 'heading', { name: 'License' } ) ).toBeVisible();
	} );

	test( 'shows integration presets on exclusions tab', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/options-general.php?page=speculation-pilot' );

		await page.getByRole( 'button', { name: 'Exclusions' } ).click();
		await expect( page.getByText( 'Integration presets' ) ).toBeVisible();
		await expect( page.getByText( 'WooCommerce' ) ).toBeVisible();
		await expect( page.getByText( 'Manual exclusions' ) ).toBeVisible();
	} );

	test( 'measurements tab shows retention and CSV controls', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/options-general.php?page=speculation-pilot' );

		await page.getByRole( 'button', { name: 'Measurements' } ).click();
		await expect( page.getByText( 'Retention days' ) ).toBeVisible();
		await expect( page.getByText( 'Export CSV' ) ).toBeVisible();
	} );
} );
