const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	timeout: 30000,
	expect: {
		timeout: 10000,
	},
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8080',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: {
				browserName: 'chromium',
			},
		},
	],
} );

