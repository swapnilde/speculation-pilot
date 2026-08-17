( function ( window, document, config ) {
	'use strict';

	if ( ! config || ! config.endpoint || ! window.performance ) {
		return;
	}

	function localPath( value ) {
		try {
			const url = new URL( value || '/', window.location.origin );
			if ( url.origin !== window.location.origin ) {
				return '';
			}
			return url.pathname || '/';
		} catch ( error ) {
			return '/';
		}
	}

	function navigationEntry() {
		const entries = window.performance.getEntriesByType
			? window.performance.getEntriesByType( 'navigation' )
			: [];
		return entries && entries.length ? entries[ 0 ] : null;
	}

	function send() {
		const entry = navigationEntry();
		if ( ! entry ) {
			return;
		}

		const payload = {
			currentPath: localPath( window.location.href ),
			previousPath: document.referrer
				? localPath( document.referrer )
				: '',
			navigationType: entry.type || 'navigate',
			duration: Math.max( 0, entry.duration || 0 ),
			ttfb: Math.max(
				0,
				( entry.responseStart || 0 ) - ( entry.requestStart || 0 )
			),
			domInteractive: Math.max( 0, entry.domInteractive || 0 ),
			loadComplete: Math.max(
				0,
				entry.loadEventEnd || entry.duration || 0
			),
			activationStart: Math.max( 0, entry.activationStart || 0 ),
			wasPrerender: !! (
				entry.activationStart && entry.activationStart > 0
			),
			mode: config.mode || '',
			eagerness: config.eagerness || '',
		};

		const body = JSON.stringify( payload );

		if ( window.navigator && window.navigator.sendBeacon ) {
			const blob = new Blob( [ body ], { type: 'application/json' } );
			if ( window.navigator.sendBeacon( config.endpoint, blob ) ) {
				return;
			}
		}

		if ( window.fetch ) {
			window
				.fetch( config.endpoint, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					body,
					credentials: 'omit',
					keepalive: true,
				} )
				.catch( function () {} );
		}
	}

	if ( document.readyState === 'complete' ) {
		window.setTimeout( send, 0 );
	} else {
		window.addEventListener( 'load', function () {
			window.setTimeout( send, 0 );
		} );
	}
} )( window, document, window.SpeculationPilotMeasurement );
