#!/usr/bin/env node
/**
 * Validate readme.txt and version sync across plugin files.
 * Usage: node tools/check-readme.js
 */

const fs = require('fs');
const path = require('path');

const pluginDir = path.resolve(__dirname, '..');
const readmePath = path.join(pluginDir, 'readme.txt');
const mainFilePath = path.join(pluginDir, 'speculation-pilot.php');
const packageJsonPath = path.join(pluginDir, 'package.json');

let errors = 0;

console.log('🔍 Checking WordPress readme.txt...\n');

// 1. readme.txt must exist
if (!fs.existsSync(readmePath)) {
	console.error('❌ readme.txt not found!');
	process.exit(1);
}
console.log('  ✅ readme.txt exists');

const readmeRaw = fs.readFileSync(readmePath, 'utf8').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
const mainFileRaw = fs.existsSync(mainFilePath) ? fs.readFileSync(mainFilePath, 'utf8').replace(/\r\n/g, '\n').replace(/\r/g, '\n') : '';
const packageJsonRaw = fs.existsSync(packageJsonPath) ? fs.readFileSync(packageJsonPath, 'utf8') : '';

// 2. Required headers
const requiredHeaders = [
	'Contributors',
	'Tags',
	'Requires at least',
	'Tested up to',
	'Requires PHP',
	'Stable tag',
	'License',
	'License URI'
];

for (const header of requiredHeaders) {
	const regex = new RegExp(`^${header}:`, 'im');
	if (!regex.test(readmeRaw)) {
		console.log(`  ❌ Missing header: ${header}`);
		errors++;
	}
}
console.log('  ✅ All required headers present');

// 3. Required sections
const requiredSections = ['Description', 'Installation', 'Changelog'];
for (const section of requiredSections) {
	const regex = new RegExp(`==\\s*${section}\\s*==`, 'i');
	if (!regex.test(readmeRaw)) {
		console.log(`  ❌ Missing section: == ${section} ==`);
		errors++;
	}
}
console.log('  ✅ All required sections present');

// 4. Short description length <= 150 chars
const readmeLines = readmeRaw.split('\n');
let shortDesc = '';
let inHeader = true;
for (let i = 1; i < readmeLines.length; i++) {
	const line = readmeLines[i].trim();
	if (inHeader) {
		if (line === '') {
			inHeader = false;
		}
	} else if (line !== '' && !line.startsWith('==')) {
		shortDesc = line;
		break;
	}
}

if (shortDesc.length > 150) {
	console.log(`  ❌ Short description is ${shortDesc.length} chars (max 150)`);
	errors++;
} else {
	console.log(`  ✅ Short description length OK (${shortDesc.length}/150)`);
}

// 5. Tags count <= 12
const tagsMatch = readmeRaw.match(/^Tags:\s*(.*)$/im);
if (tagsMatch) {
	const tagCount = tagsMatch[1].split(',').map((t) => t.trim()).filter(Boolean).length;
	if (tagCount > 12) {
		console.log(`  ❌ Too many tags: ${tagCount} (max 12)`);
		errors++;
	} else {
		console.log(`  ✅ Tag count OK (${tagCount}/12)`);
	}
}

// 6. Version sync
const stableTagMatch = readmeRaw.match(/^Stable tag:\s*(.*)$/im);
const pluginVerMatch = mainFileRaw.match(/Version:\s*(.*)$/im);
const constVerMatch = mainFileRaw.match(/define\(\s*['"]SPECULATION_PILOT_VERSION['"]\s*,\s*['"]([^'"]+)['"]\s*\)/i);
let pkgVer = '';
try {
	pkgVer = JSON.parse(packageJsonRaw).version || '';
} catch (e) {}

const readmeVer = stableTagMatch ? stableTagMatch[1].trim() : '';
const pluginVer = pluginVerMatch ? pluginVerMatch[1].trim() : '';
const constVer = constVerMatch ? constVerMatch[1].trim() : '';

console.log('\n📦 Version sync check:');
console.log(`  readme.txt (Stable tag):  ${readmeVer}`);
console.log(`  speculation-pilot.php header: ${pluginVer}`);
console.log(`  speculation-pilot.php const:  ${constVer}`);
console.log(`  package.json:             ${pkgVer}`);

if (readmeVer !== pluginVer) {
	console.log(`  ❌ Stable tag (${readmeVer}) ≠ plugin header (${pluginVer})`);
	errors++;
}
if (readmeVer !== pkgVer) {
	console.log(`  ❌ Stable tag (${readmeVer}) ≠ package.json (${pkgVer})`);
	errors++;
}
if (readmeVer !== constVer) {
	console.log(`  ❌ Stable tag (${readmeVer}) ≠ SPECULATION_PILOT_VERSION (${constVer})`);
	errors++;
}
if (readmeVer === pluginVer && readmeVer === pkgVer && readmeVer === constVer) {
	console.log(`  ✅ All versions match: ${readmeVer}`);
}

// 7. WordPress version sync
console.log('\n📋 WordPress version sync:');
const readmeWpMatch = readmeRaw.match(/^Requires at least:\s*(.*)$/im);
const pluginWpMatch = mainFileRaw.match(/Requires at least:\s*(.*)$/im);
const readmeWp = readmeWpMatch ? readmeWpMatch[1].trim() : '';
const pluginWp = pluginWpMatch ? pluginWpMatch[1].trim() : '';

console.log(`  readme.txt:          ${readmeWp}`);
console.log(`  plugin header:       ${pluginWp}`);
if (readmeWp !== pluginWp) {
	console.log('  ❌ WP minimum version mismatch');
	errors++;
} else {
	console.log('  ✅ WP minimum versions match');
}

const readmePhpMatch = readmeRaw.match(/^Requires PHP:\s*(.*)$/im);
const pluginPhpMatch = mainFileRaw.match(/Requires PHP:\s*(.*)$/im);
const readmePhp = readmePhpMatch ? readmePhpMatch[1].trim() : '';
const pluginPhp = pluginPhpMatch ? pluginPhpMatch[1].trim() : '';

console.log(`  PHP min (readme):    ${readmePhp}`);
console.log(`  PHP min (header):    ${pluginPhp}`);
if (readmePhp !== pluginPhp) {
	console.log('  ❌ PHP minimum version mismatch');
	errors++;
} else {
	console.log('  ✅ PHP minimum versions match');
}

// Summary
console.log('');
if (errors > 0) {
	console.log(`❌ ${errors} issue(s) found. Please fix before release.`);
	process.exit(1);
} else {
	console.log('✅ All readme checks passed!');
	process.exit(0);
}
