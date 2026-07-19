/**
 * Speculation Pilot — Admin Bar Live Debugger JavaScript.
 */
( function () {
	'use strict';

	function initDebugger() {
		const config = window.SpeculationPilotDebugger;

		if ( ! config ) {
			return;
		}

		updateActivationStatus();
		updateLinkCandidates( config );
	}

	/**
	 * Detects whether the current page was activated from a prerender or prefetch.
	 */
	function updateActivationStatus() {
		const statusEl = document.getElementById( 'sp-debug-status' );
		if ( ! statusEl ) {
			return;
		}

		const navEntries = performance.getEntriesByType( 'navigation' );
		const navEntry = navEntries.length > 0 ? navEntries[0] : null;

		let statusText = 'ℹ️ Status: Standard Navigation';

		if ( document.prerendered || ( navEntry && navEntry.activationStart && navEntry.activationStart > 0 ) ) {
			const ms = navEntry && navEntry.activationStart ? Math.round( navEntry.activationStart ) : 0;
			statusText = `⚡ Status: Prerendered (Activated in ${ ms }ms)`;
		} else if ( navEntry && navEntry.deliveryType === 'navigational-prefetch' ) {
			statusText = '🚀 Status: Prefetched Navigation';
		} else if ( document.referrer && window.location.origin === new URL( document.referrer ).origin ) {
			statusText = 'ℹ️ Status: Internal Navigation (Admin Session)';
		}

		statusEl.textContent = statusText;
	}

	/**
	 * Scans internal links on the page and checks eligibility against exclusion rules.
	 *
	 * @param {Object} config Debugger configuration.
	 */
	function updateLinkCandidates( config ) {
		const linksEl = document.getElementById( 'sp-debug-links' );
		if ( ! linksEl ) {
			return;
		}

		const allLinks = Array.from( document.querySelectorAll( 'a[href]' ) );
		const homeOrigin = config.homeUrl ? new URL( config.homeUrl ).origin : window.location.origin;

		let internalCount = 0;
		let eligibleCount = 0;
		let excludedCount = 0;

		allLinks.forEach( ( link ) => {
			try {
				const url = new URL( link.href, window.location.href );

				// Only consider same-origin internal links.
				if ( url.origin !== homeOrigin ) {
					return;
				}

				// Skip anchor links, javascript:, or mailto:
				if ( url.pathname === window.location.pathname && url.hash && url.search === window.location.search ) {
					return;
				}

				internalCount++;

				const isExcluded = config.exclusions.some( ( rule ) => isPathExcluded( url.pathname + url.search, rule ) );

				if ( isExcluded ) {
					excludedCount++;
					link.setAttribute( 'data-sp-debug', 'excluded' );
				} else {
					eligibleCount++;
					link.setAttribute( 'data-sp-debug', 'eligible' );
				}
			} catch ( e ) {
				// Ignore invalid URLs.
			}
		} );

		linksEl.textContent = `DOM Candidates: ${ eligibleCount } eligible / ${ excludedCount } excluded (${ internalCount } total)`;
	}

	/**
	 * Checks if a path matches an exclusion rule pattern.
	 *
	 * @param {string} path Path to check.
	 * @param {string} rule Rule string.
	 * @return {boolean} True if excluded.
	 */
	function isPathExcluded( path, rule ) {
		rule = rule.trim();
		if ( ! rule ) {
			return false;
		}

		if ( path === rule ) {
			return true;
		}

		if ( rule.includes( '*' ) ) {
			const regexStr = '^' + rule.replace( /[.+^${}()|[\]\\]/g, '\\$&' ).replace( /\*/g, '.*' ) + '$';
			const regex = new RegExp( regexStr, 'i' );
			return regex.test( path );
		}

		return false;
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initDebugger );
	} else {
		initDebugger();
	}
} )();
