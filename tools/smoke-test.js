#!/usr/bin/env node
/* eslint-disable no-console */

const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const pkg = require( path.join( root, 'package.json' ) );

const requiredFiles = [
	'speculation-pilot.php',
	'includes/Plugin.php',
	'includes/Settings.php',
	'includes/CoreIntegration.php',
	'includes/SafetyEngine.php',
	'includes/Diagnostics.php',
	'includes/Measurements.php',
	'includes/RestApi.php',
	'includes/Admin.php',
	'includes/Frontend.php',
	'includes/SiteHealth.php',
	'includes/Cli.php',
	'docker-compose.yml',
	'playwright.config.js',
	'tools/setup-qa.js',
	'tools/package-plugin.js',
	'languages/speculation-pilot.pot',
	'build/admin/index.js',
	'build/admin/index.css',
	'build/admin/index.asset.php',
	'build/frontend/measurement.js',
	'build/frontend/measurement.asset.php',
	'readme.txt',
	'README.md',
];

const sourceBuildPairs = [
	[ 'src/admin/index.js', 'build/admin/index.js' ],
	[ 'src/admin/style.css', 'build/admin/index.css' ],
	[ 'src/frontend/measurement.js', 'build/frontend/measurement.js' ],
];

const assetDir = fs.existsSync( path.join( root, 'assets' ) ) ? 'assets' : '.wordpress-org';
const imageFiles = [
	`${ assetDir }/icon-128x128.png`,
	`${ assetDir }/icon-256x256.png`,
	`${ assetDir }/banner-772x250.png`,
	`${ assetDir }/banner-1544x500.png`,
	`${ assetDir }/screenshot-1.png`,
	`${ assetDir }/screenshot-2.png`,
	`${ assetDir }/screenshot-3.png`,
];

function read( file ) {
	return fs.readFileSync( path.join( root, file ), 'utf8' );
}

function assert( condition, message ) {
	if ( ! condition ) {
		throw new Error( message );
	}
}

requiredFiles.forEach( ( file ) => {
	assert( fs.existsSync( path.join( root, file ) ), `Missing required file: ${ file }` );
} );

imageFiles.forEach( ( file ) => {
	const bytes = fs.readFileSync( path.join( root, file ) );
	assert( bytes.slice( 0, 8 ).toString( 'hex' ) === '89504e470d0a1a0a', `Invalid PNG signature: ${ file }` );
} );

sourceBuildPairs.forEach( ( pair ) => {
	assert( read( pair[ 0 ] ) === read( pair[ 1 ] ), `Build asset is out of sync: ${ pair[ 1 ] }` );
} );

const main = read( 'speculation-pilot.php' );
assert( main.includes( 'Plugin Name: Speculation Pilot' ), 'Missing plugin header.' );
assert( main.includes( `Version:     ${ pkg.version }` ), 'Plugin header version does not match package.json.' );
assert( main.includes( "Requires at least: 6.8" ), 'Missing WordPress requirement.' );

const readme = read( 'readme.txt' );
assert( readme.includes( `Stable tag: ${ pkg.version }` ), 'readme.txt stable tag does not match package.json.' );

const pot = read( 'languages/speculation-pilot.pot' );
assert( pot.includes( `Project-Id-Version: Speculation Pilot ${ pkg.version }` ), 'POT version does not match package.json.' );

const adminAsset = read( 'build/admin/index.asset.php' );
assert( adminAsset.includes( "'wp-api-fetch'" ), 'Admin asset metadata is missing wp-api-fetch.' );
assert( adminAsset.includes( "'wp-components'" ), 'Admin asset metadata is missing wp-components.' );

const measurement = read( 'build/frontend/measurement.js' );
[
	'document.cookie',
	'navigator.userAgent',
	'localStorage',
	'sessionStorage',
].forEach( ( forbidden ) => {
	assert( ! measurement.includes( forbidden ), `Measurement script contains forbidden token: ${ forbidden }` );
} );

[
	'wp_speculation_rules_configuration',
	'wp_speculation_rules_href_exclude_paths',
	'new SiteHealth',
	'Cli::register',
].forEach( ( token ) => {
	const found = fs
		.readdirSync( path.join( root, 'includes' ) )
		.concat( [ 'speculation-pilot.php' ] )
		.some( ( file ) => read( file.startsWith( 'speculation' ) ? file : `includes/${ file }` ).includes( token ) );

	assert( found, `Expected token was not found: ${ token }` );
} );

console.log( 'Speculation Pilot smoke test passed.' );
