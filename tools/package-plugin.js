#!/usr/bin/env node
/* eslint-disable no-console */

const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { execSync } = require( 'child_process' );

const root = path.resolve( __dirname, '..' );
const pkg = require( path.join( root, 'package.json' ) );
const slug = 'speculation-pilot';
const tmpRoot = path.join( os.tmpdir(), `${ slug }-package-${ pkg.version }` );
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
ignore.add( '.tmp-verify' );

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

console.log( `📦 Packaging ${ slug } v${ pkg.version }...` );

fs.rmSync( tmpRoot, { recursive: true, force: true } );
fs.mkdirSync( packageRoot, { recursive: true } );
fs.mkdirSync( dist, { recursive: true } );
copyRecursive( root, packageRoot );

if ( fs.existsSync( zipPath ) ) {
	fs.rmSync( zipPath );
}

// Cross-platform zip creation using tar -a (built into modern Windows/Linux/macOS)
// with fallbacks for zip or powershell
let packaged = false;

// Attempt 1: tar -a (standard on Windows 10/11, macOS, Linux)
try {
	execSync( `tar -a -c -f "${ zipPath }" -C "${ tmpRoot }" "${ slug }"`, {
		stdio: 'inherit',
	} );
	packaged = true;
} catch ( e ) {
	// Fall back
}

// Attempt 2: zip CLI command
if ( ! packaged ) {
	try {
		execSync( `zip -r "${ zipPath }" "${ slug }"`, {
			cwd: tmpRoot,
			stdio: 'inherit',
		} );
		packaged = true;
	} catch ( e ) {
		// Fall back
	}
}

// Attempt 3: PowerShell .NET ZipFile
if ( ! packaged && process.platform === 'win32' ) {
	try {
		execSync(
			`powershell.exe -NoProfile -Command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::CreateFromDirectory('${ packageRoot }', '${ zipPath }')"`,
			{ stdio: 'inherit' }
		);
		packaged = true;
	} catch ( e ) {
		// Failed
	}
}

if ( packaged && fs.existsSync( zipPath ) ) {
	console.log( `✅ Created ${ zipPath } (${ ( fs.statSync( zipPath ).size / 1024 ).toFixed( 1 ) } KB)` );
} else {
	console.error( '❌ Failed to create zip package' );
	process.exit( 1 );
}

// Clean up temp folder
try {
	fs.rmSync( tmpRoot, { recursive: true, force: true } );
} catch ( e ) {}
