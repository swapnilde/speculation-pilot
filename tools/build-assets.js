#!/usr/bin/env node
/* eslint-disable no-console */

const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const version = require( path.join( root, 'package.json' ) ).version;

const files = [
	{
		from: 'src/admin/index.js',
		to: 'build/admin/index.js',
	},
	{
		from: 'src/admin/style.css',
		to: 'build/admin/index.css',
	},
	{
		from: 'src/frontend/measurement.js',
		to: 'build/frontend/measurement.js',
	},
	{
		from: 'src/frontend/admin-bar-debugger.js',
		to: 'build/frontend/admin-bar-debugger.js',
	},
	{
		from: 'src/frontend/admin-bar-debugger.css',
		to: 'build/frontend/admin-bar-debugger.css',
	},
];

function copyFile( item ) {
	const from = path.join( root, item.from );
	const to = path.join( root, item.to );

	fs.mkdirSync( path.dirname( to ), { recursive: true } );
	fs.copyFileSync( from, to );
	console.log( `${ item.from } -> ${ item.to }` );
}

function writeAssetFiles() {
	const adminAsset = `<?php
/**
 * Admin asset metadata.
 *
 * @package SpeculationPilot
 */

return array(
\t'dependencies' => array( 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-i18n' ),
\t'version'      => '${ version }',
);
`;

	const frontendAsset = `<?php
/**
 * Frontend measurement asset metadata.
 *
 * @package SpeculationPilot
 */

return array(
\t'dependencies' => array(),
\t'version'      => '${ version }',
);
`;

	const debugAsset = `<?php
/**
 * Frontend debug asset metadata.
 *
 * @package SpeculationPilot
 */

return array(
\t'dependencies' => array(),
\t'version'      => '${ version }',
);
`;

	fs.writeFileSync( path.join( root, 'build/admin/index.asset.php' ), adminAsset );
	fs.writeFileSync( path.join( root, 'build/frontend/measurement.asset.php' ), frontendAsset );
	fs.writeFileSync( path.join( root, 'build/frontend/admin-bar-debugger.asset.php' ), debugAsset );
}

function build() {
	files.forEach( copyFile );
	writeAssetFiles();
}

build();

if ( process.argv.includes( '--watch' ) ) {
	console.log( 'Watching source assets...' );
	files.forEach( ( item ) => {
		fs.watch( path.join( root, item.from ), { persistent: true }, build );
	} );
}

