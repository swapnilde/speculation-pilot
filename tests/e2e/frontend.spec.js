const { test, expect } = require( '@playwright/test' );

test.describe( 'Speculation Pilot frontend', () => {
	test( 'prints speculation rules and measurement script for logged-out visitors', async ( { page } ) => {
		await page.goto( '/' );

		const rules = page.locator( 'script[type="speculationrules"]' );
		await expect( rules ).toHaveCount( 1 );
		await expect( page.locator( '#speculation-pilot-measurement-js, script[src*="measurement.js"]' ) ).toHaveCount( 1 );
	} );

	test( 'does not leak query strings into measurement payloads', async ( { page } ) => {
		const requests = [];
		page.on( 'request', ( request ) => {
			if ( request.url().includes( '/wp-json/speculation-pilot/v1/measurement' ) ) {
				requests.push( request.postData() || '' );
			}
		} );

		await page.goto( '/sample-page/?coupon=secret#private' );
		await page.waitForLoadState( 'networkidle' );

		if ( requests.length ) {
			expect( requests[0] ).not.toContain( 'coupon=secret' );
			expect( requests[0] ).not.toContain( '#private' );
		}
	} );
} );

