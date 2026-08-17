#!/usr/bin/env node
/**
 * Convert WordPress readme.txt to GitHub-flavored README.md.
 * Usage: node tools/readme-txt-to-md.js
 */

const fs = require('fs');
const path = require('path');

const pluginDir = path.resolve(__dirname, '..');
const readmeTxtPath = path.join(pluginDir, 'readme.txt');
const readmeMdPath = path.join(pluginDir, 'README.md');

if (!fs.existsSync(readmeTxtPath)) {
	console.error('❌ readme.txt not found at: ' + readmeTxtPath);
	process.exit(1);
}

const rawContent = fs.readFileSync(readmeTxtPath, 'utf8').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
const lines = rawContent.split('\n');

// 1. Extract Plugin Name (=== Plugin Name ===)
let pluginName = 'Plugin Name';
const nameMatch = lines[0].match(/^===\s*(.*?)\s*===$/);
if (nameMatch) {
	pluginName = nameMatch[1];
}

// 2. Parse Header Fields
const headers = {};
let headerEndIndex = 0;

for (let i = 1; i < lines.length; i++) {
	const line = lines[i].trim();
	if (line.startsWith('== ')) {
		headerEndIndex = i;
		break;
	}
	const headerMatch = line.match(/^([^:]+):\s*(.*)$/);
	if (headerMatch) {
		headers[headerMatch[1].trim().toLowerCase()] = headerMatch[2].trim();
	}
}

const contributors = headers['contributors'] || '';
const donateLink = headers['donate link'] || '';
const tags = headers['tags'] || '';
const requiresAtLeast = headers['requires at least'] || '6.8';
const testedUpTo = headers['tested up to'] || '6.8';
const requiresPhp = headers['requires php'] || '7.4';
const stableTag = headers['stable tag'] || '0.1.0';
const license = headers['license'] || 'GPLv2 or later';
const licenseUri = headers['license uri'] || 'https://www.gnu.org/licenses/gpl-2.0.html';

// 3. Extract Short Description
let shortDesc = '';
let inHeaderBlock = true;
for (let i = 1; i < (headerEndIndex || lines.length); i++) {
	const line = lines[i].trim();
	if (inHeaderBlock) {
		if (line === '') {
			inHeaderBlock = false;
		}
	} else if (line !== '' && !line.startsWith('==')) {
		shortDesc = line;
		break;
	}
}

// 4. Build Output Markdown
const out = [];

out.push(`# ${pluginName}`);
out.push('');

// Badges
const badges = [];
if (stableTag) {
	badges.push(`![Version](https://img.shields.io/badge/version-${encodeURIComponent(stableTag)}-blue.svg)`);
}
if (requiresAtLeast) {
	badges.push(`![WordPress](https://img.shields.io/badge/WordPress-${encodeURIComponent(requiresAtLeast + '+')}-21759b.svg)`);
}
if (testedUpTo) {
	badges.push(`![Tested up to](https://img.shields.io/badge/tested%20up%20to-WP%20${encodeURIComponent(testedUpTo)}-21759b.svg)`);
}
if (requiresPhp) {
	badges.push(`![PHP](https://img.shields.io/badge/PHP-${encodeURIComponent(requiresPhp + '+')}-777bb4.svg)`);
}
if (license) {
	badges.push(`![License](https://img.shields.io/badge/license-${encodeURIComponent(license)}-green.svg)`);
}
if (donateLink) {
	badges.push(`[![Donate](https://img.shields.io/badge/Donate-PayPal-00457C.svg)](${donateLink})`);
}

out.push(badges.join(' '));
out.push('');

if (contributors) {
	const contributorLinks = contributors.split(',').map((c) => {
		const handle = c.trim();
		return `[@${handle}](https://profiles.wordpress.org/${handle}/)`;
	}).join(', ');
	out.push(`**Contributors:** ${contributorLinks}`);
	out.push('');
}

if (shortDesc) {
	out.push(`> ${shortDesc}`);
	out.push('');
}

// 5. Parse Sections and Body
const bodyLines = lines.slice(headerEndIndex);
let inCodeBlock = false;

for (let i = 0; i < bodyLines.length; i++) {
	const line = bodyLines[i];
	const trimmed = line.trim();

	// Check for sections: == Section == -> ## Section
	const sectionMatch = trimmed.match(/^==\s*(.*?)\s*==$/);
	if (sectionMatch) {
		out.push(`## ${sectionMatch[1]}`);
		out.push('');
		continue;
	}

	// Check for subsections: = Subsection = -> ### Subsection
	const subsectionMatch = trimmed.match(/^=\s*(.*?)\s*=+$/);
	if (subsectionMatch) {
		out.push(`### ${subsectionMatch[1]}`);
		out.push('');
		continue;
	}

	out.push(line);
}

// 6. Clean up consecutive empty lines
const cleaned = [];
let prevEmpty = false;

for (const l of out) {
	const isEmpty = l.trim() === '';
	if (isEmpty && prevEmpty) {
		continue;
	}
	cleaned.push(l);
	prevEmpty = isEmpty;
}

const finalMarkdown = cleaned.join('\n').trim() + '\n';
fs.writeFileSync(readmeMdPath, finalMarkdown, 'utf8');

const lineCount = finalMarkdown.split('\n').length;
console.log(`✅ README.md generated from readme.txt (${lineCount} lines)`);
