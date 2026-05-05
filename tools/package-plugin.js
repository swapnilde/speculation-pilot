#!/usr/bin/env node
/* eslint-disable no-console */

const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

const root = path.resolve( __dirname, '..' );
const pkg = require( path.join( root, 'package.json' ) );
const slug = 'speculation-pilot';
const tmpRoot = path.join( '/private/tmp', `${ slug }-package-${ pkg.version }` );
const packageRoot = path.join( tmpRoot, slug );
const dist = path.join( root, 'dist' );
const zipPath = path.join( dist, `${ slug }-${ pkg.version }.zip` );
const ignore = new Set(
	fs
		.readFileSync( path.join( root, '.distignore' ), 'utf8' )
		.split( /\r?\n/ )
		.map( ( line ) => line.trim() )
		.filter( Boolean )
);

ignore.add( 'dist' );
ignore.add( '.git' );

function shouldIgnore( relative ) {
	const parts = relative.split( path.sep );

	return parts.some( ( part ) => ignore.has( part ) ) || ignore.has( relative );
}

function copyRecursive( from, to, relative = '' ) {
	if ( shouldIgnore( relative ) ) {
		return;
	}

	const stat = fs.statSync( from );

	if ( stat.isDirectory() ) {
		fs.mkdirSync( to, { recursive: true } );
		fs.readdirSync( from ).forEach( ( child ) => {
			copyRecursive( path.join( from, child ), path.join( to, child ), path.join( relative, child ) );
		} );
		return;
	}

	fs.mkdirSync( path.dirname( to ), { recursive: true } );
	fs.copyFileSync( from, to );
}

fs.rmSync( tmpRoot, { recursive: true, force: true } );
fs.mkdirSync( packageRoot, { recursive: true } );
fs.mkdirSync( dist, { recursive: true } );
copyRecursive( root, packageRoot );

if ( fs.existsSync( zipPath ) ) {
	fs.rmSync( zipPath );
}

execFileSync( 'zip', [ '-r', zipPath, slug ], {
	cwd: tmpRoot,
	stdio: 'inherit',
} );

console.log( `Created ${ zipPath }` );

