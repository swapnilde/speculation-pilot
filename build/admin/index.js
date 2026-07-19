( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.apiFetch || ! config ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useCallback = wp.element.useCallback;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;
	var Button = wp.components.Button;
	var CheckboxControl = wp.components.CheckboxControl;
	var Notice = wp.components.Notice;
	var SelectControl = wp.components.SelectControl;
	var Spinner = wp.components.Spinner;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;

	apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );

	var tabs = [
		{ key: 'overview', label: __( 'Overview', 'speculation-pilot' ) },
		{ key: 'rules', label: __( 'Rules', 'speculation-pilot' ) },
		{ key: 'exclusions', label: __( 'Exclusions', 'speculation-pilot' ) },
		{ key: 'measurements', label: __( 'Measurements', 'speculation-pilot' ) },
		{ key: 'diagnostics', label: __( 'Diagnostics', 'speculation-pilot' ) },
		{ key: 'settings', label: __( 'Settings', 'speculation-pilot' ) },
	];

	var presetCopy = {
		safe: {
			title: __( 'Safe Boost', 'speculation-pilot' ),
			description: __( 'Conservative prefetch with the broadest safety exclusions. Best first setting for production sites.', 'speculation-pilot' ),
			mode: 'prefetch',
			eagerness: 'conservative',
		},
		balanced: {
			title: __( 'Balanced', 'speculation-pilot' ),
			description: __( 'Moderate prefetch with standard commerce and account safeguards. Good for content-heavy sites.', 'speculation-pilot' ),
			mode: 'prefetch',
			eagerness: 'moderate',
		},
		aggressive_lab: {
			title: __( 'Aggressive Lab', 'speculation-pilot' ),
			description: __( 'Prerender with warnings and guarded exclusions. Use for controlled testing before broad rollout.', 'speculation-pilot' ),
			mode: 'prerender',
			eagerness: 'moderate',
		},
	};

	function request( path, options ) {
		return apiFetch(
			Object.assign(
				{
					path: '/speculation-pilot/v1/' + path,
				},
				options || {}
			)
		);
	}

	function Status( props ) {
		var status = props.status || 'info';
		return el(
			'span',
			{ className: 'speculation-pilot__status speculation-pilot__status--' + status },
			status
		);
	}

	function Metric( props ) {
		return el(
			'div',
			{ className: 'speculation-pilot__metric' },
			el( 'p', { className: 'speculation-pilot__metric-label' }, props.label ),
			el( 'p', { className: 'speculation-pilot__metric-value' }, props.value ),
			props.note ? el( 'p', { className: 'speculation-pilot__metric-note' }, props.note ) : null
		);
	}

	function ProBadge() {
		return el(
			'a',
			{
				className: 'speculation-pilot__pro-badge',
				href: config.upgradeUrl || 'https://speculationpilot.com/pricing/',
				target: '_blank',
				rel: 'noopener noreferrer',
				title: __( 'Requires Speculation Pilot Pro', 'speculation-pilot' ),
			},
			'PRO'
		);
	}

	function ProGate( props ) {
		var isPro = config.isPro;
		if ( isPro ) {
			return el( Fragment, null, props.children );
		}

		return el(
			'div',
			{ className: 'speculation-pilot__pro-gate' },
			el( 'div', { className: 'speculation-pilot__pro-gate-content is-locked' }, props.children ),
			el(
				'div',
				{ className: 'speculation-pilot__pro-gate-overlay' },
				el( 'p', null, '🔒 ', __( 'Unlock with Speculation Pilot Pro', 'speculation-pilot' ) ),
				el(
					Button,
					{
						variant: 'primary',
						href: config.upgradeUrl || 'https://speculationpilot.com/pricing/',
						target: '_blank',
					},
					__( 'Upgrade →', 'speculation-pilot' )
				)
			)
		);
	}

	function UpgradeBanner() {
		var dismissed = useState( false );
		var isDismissed = dismissed[ 0 ];
		var setDismissed = dismissed[ 1 ];

		if ( config.isPro || isDismissed ) {
			return null;
		}

		return el(
			'div',
			{ className: 'speculation-pilot__upgrade-banner' },
			el( 'p', null, '🚀 ', __( 'Unlock WooCommerce presets, 365-day history, advanced reports, and more with Pro.', 'speculation-pilot' ) ),
			el(
				'div',
				{ className: 'speculation-pilot__upgrade-banner-actions' },
				el(
					Button,
					{
						variant: 'secondary',
						href: config.upgradeUrl || 'https://speculationpilot.com/pricing/',
						target: '_blank',
					},
					__( 'See Pro →', 'speculation-pilot' )
				),
				el(
					Button,
					{
						variant: 'tertiary',
						onClick: function () {
							setDismissed( true );
						},
					},
					'✕'
				)
			)
		);
	}

	function App() {
		var initial = {
			enabled: true,
			mode: 'prefetch',
			eagerness: 'conservative',
			preset: 'safe',
			exclusions: [],
			integrations: {
				woocommerce: true,
				edd: false,
				membership: false,
				lms: false,
				multilingual: false,
				cache: false,
			},
			measurement_enabled: false,
			retention_days: 7,
			cleanup_on_uninstall: false,
			prerender_warning_seen: false,
		};

		var state = useState( 'overview' );
		var activeTab = state[ 0 ];
		var setActiveTab = state[ 1 ];

		var settingsState = useState( initial );
		var settings = settingsState[ 0 ];
		var setSettings = settingsState[ 1 ];

		var dataState = useState( {
			exclusions: [],
			exclusionNotes: {},
			diagnostics: null,
			report: null,
		} );
		var data = dataState[ 0 ];
		var setData = dataState[ 1 ];

		var loadingState = useState( true );
		var loading = loadingState[ 0 ];
		var setLoading = loadingState[ 1 ];

		var savingState = useState( false );
		var saving = savingState[ 0 ];
		var setSaving = savingState[ 1 ];

		var noticeState = useState( null );
		var notice = noticeState[ 0 ];
		var setNotice = noticeState[ 1 ];

		var loadAll = useCallback( function () {
			setLoading( true );
			Promise.all( [
				request( 'settings' ),
				request( 'diagnostics' ),
				request( 'report' ),
			] )
				.then( function ( responses ) {
					setSettings( responses[ 0 ].settings );
					setData( {
						exclusions: responses[ 0 ].exclusions || [],
						exclusionNotes: responses[ 0 ].exclusionNotes || {},
						diagnostics: responses[ 1 ],
						report: responses[ 2 ],
					} );
				} )
				.catch( function ( error ) {
					setNotice( {
						status: 'error',
						message: error && error.message ? error.message : __( 'Unable to load Speculation Pilot data.', 'speculation-pilot' ),
					} );
				} )
				.finally( function () {
					setLoading( false );
				} );
		}, [] );

		useEffect( function () {
			loadAll();
		}, [ loadAll ] );

		function updateSetting( key, value ) {
			setSettings(
				Object.assign( {}, settings, {
					[ key ]: value,
					preset: [ 'mode', 'eagerness' ].indexOf( key ) !== -1 ? 'custom' : settings.preset,
				} )
			);
		}

		function updateIntegration( key, value ) {
			setSettings(
				Object.assign( {}, settings, {
					integrations: Object.assign( {}, settings.integrations, {
						[ key ]: value,
					} ),
				} )
			);
		}

		function applyPreset( key ) {
			var preset = presetCopy[ key ];
			if ( ! preset ) {
				return;
			}

			setSettings(
				Object.assign( {}, settings, {
					preset: key,
					mode: preset.mode,
					eagerness: preset.eagerness,
					prerender_warning_seen: settings.prerender_warning_seen,
				} )
			);
		}

		function saveSettings() {
			setSaving( true );
			setNotice( null );
			request( 'settings', {
				method: 'POST',
				data: settings,
			} )
				.then( function ( response ) {
					setSettings( response.settings );
					return Promise.all( [ request( 'diagnostics' ), request( 'report' ) ] ).then( function ( responses ) {
						setData( {
							exclusions: response.exclusions || [],
							exclusionNotes: response.exclusionNotes || {},
							diagnostics: responses[ 0 ],
							report: responses[ 1 ],
						} );
					} );
				} )
				.then( function () {
					setNotice( {
						status: 'success',
						message: __( 'Settings saved.', 'speculation-pilot' ),
					} );
				} )
				.catch( function ( error ) {
					setNotice( {
						status: 'error',
						message: error && error.message ? error.message : __( 'Unable to save settings.', 'speculation-pilot' ),
					} );
				} )
				.finally( function () {
					setSaving( false );
				} );
		}

		function clearMeasurements() {
			if ( ! window.confirm( __( 'Clear all local Speculation Pilot measurement rows?', 'speculation-pilot' ) ) ) {
				return;
			}

			setSaving( true );
			request( 'report/clear', {
				method: 'POST',
			} )
				.then( function ( response ) {
					setData(
						Object.assign( {}, data, {
							report: response.report || {},
						} )
					);
					setNotice( {
						status: 'success',
						message: __( 'Local measurements cleared.', 'speculation-pilot' ),
					} );
				} )
				.catch( function ( error ) {
					setNotice( {
						status: 'error',
						message: error && error.message ? error.message : __( 'Unable to clear measurements.', 'speculation-pilot' ),
					} );
				} )
				.finally( function () {
					setSaving( false );
				} );
		}

		var effectiveConfig = data.diagnostics && data.diagnostics.effectiveConfig ? data.diagnostics.effectiveConfig : null;
		var report = data.report || {};

		return el(
			'div',
			{ className: 'speculation-pilot' },
			el(
				'div',
				{ className: 'speculation-pilot__header' },
				el(
					'div',
					null,
					el( 'p', { className: 'speculation-pilot__eyebrow' }, __( 'WordPress speculative loading control', 'speculation-pilot' ) ),
					el( 'h1', null, __( 'Speculation Pilot', 'speculation-pilot' ) ),
					el(
						'p',
						{ className: 'speculation-pilot__subtitle' },
						__( 'Safely tune prefetch and prerender behavior, protect risky WordPress routes, and measure local navigation timing without sending data to a SaaS.', 'speculation-pilot' )
					)
				),
				el(
					'div',
					{ className: 'speculation-pilot__header-actions' },
					el(
						Button,
						{
							variant: 'secondary',
							onClick: loadAll,
							disabled: loading || saving,
						},
						__( 'Refresh', 'speculation-pilot' )
					),
					el(
						Button,
						{
							variant: 'primary',
							onClick: saveSettings,
							isBusy: saving,
							disabled: loading || saving,
						},
						__( 'Save settings', 'speculation-pilot' )
					)
				)
			),
			notice
				? el(
						Notice,
						{
							status: notice.status,
							isDismissible: true,
							onRemove: function () {
								setNotice( null );
							},
						},
						notice.message
				  )
				: null,
			loading
				? el(
						'div',
						{ className: 'speculation-pilot__section' },
						el( Spinner, null ),
						' ',
						__( 'Loading plugin data...', 'speculation-pilot' )
				  )
				: el(
						Fragment,
						null,
						el( UpgradeBanner ),
						el(
							'div',
							{ className: 'speculation-pilot__tabs' },
							tabs.map( function ( tab ) {
								return el(
									'button',
									{
										key: tab.key,
										type: 'button',
										className: 'speculation-pilot__tab' + ( activeTab === tab.key ? ' is-active' : '' ),
										onClick: function () {
											setActiveTab( tab.key );
										},
									},
									tab.label
								);
							} )
						),
						activeTab === 'overview'
							? el( Overview, {
									settings: settings,
									diagnostics: data.diagnostics,
									report: report,
									effectiveConfig: effectiveConfig,
									exclusions: data.exclusions,
							  } )
							: null,
						activeTab === 'rules'
							? el( Rules, {
									settings: settings,
									updateSetting: updateSetting,
									applyPreset: applyPreset,
							  } )
							: null,
						activeTab === 'exclusions'
							? el( Exclusions, {
									settings: settings,
									setSettings: setSettings,
									updateIntegration: updateIntegration,
									exclusions: data.exclusions,
									groups: data.exclusionNotes,
							  } )
							: null,
						activeTab === 'measurements'
							? el( Measurements, {
									settings: settings,
									updateSetting: updateSetting,
									report: report,
									onClear: clearMeasurements,
							  } )
							: null,
						activeTab === 'diagnostics'
							? el( Diagnostics, {
									diagnostics: data.diagnostics,
							  } )
							: null,
						activeTab === 'settings'
							? el( SettingsPanel, {
									settings: settings,
									updateSetting: updateSetting,
							  } )
							: null
				  )
		);
	}

	function Overview( props ) {
		var diagnostics = props.diagnostics || {};
		var report = props.report || {};
		var effectiveConfig = props.effectiveConfig;

		return el(
			Fragment,
			null,
			el(
				'div',
				{ className: 'speculation-pilot__grid' },
				el( Metric, {
					label: __( 'Current mode', 'speculation-pilot' ),
					value: effectiveConfig ? effectiveConfig.mode : __( 'Disabled', 'speculation-pilot' ),
					note: effectiveConfig ? effectiveConfig.eagerness : __( 'Core rules are not being modified.', 'speculation-pilot' ),
				} ),
				el( Metric, {
					label: __( 'Diagnostics', 'speculation-pilot' ),
					value: diagnostics.status || 'unknown',
					note: __( 'Open Diagnostics for detailed compatibility notes.', 'speculation-pilot' ),
				} ),
				el( Metric, {
					label: __( 'Measurements', 'speculation-pilot' ),
					value: report.sampleCount || 0,
					note: report.enabled ? __( 'Local samples retained on this site.', 'speculation-pilot' ) : __( 'Measurement is currently off.', 'speculation-pilot' ),
				} ),
				el( Metric, {
					label: __( 'Plan', 'speculation-pilot' ),
					value: config.isPro ? __( 'Pro ✓', 'speculation-pilot' ) : __( 'Free', 'speculation-pilot' ),
					note: config.isPro ? __( 'All features unlocked.', 'speculation-pilot' ) : __( 'Upgrade for advanced reports.', 'speculation-pilot' ),
				} )
			),
			el(
				'div',
				{ className: 'speculation-pilot__panel-grid' },
				el(
					'div',
					{ className: 'speculation-pilot__section' },
					el( 'h2', null, __( 'Active exclusions', 'speculation-pilot' ) ),
					props.exclusions && props.exclusions.length
						? el(
								'ul',
								{ className: 'speculation-pilot__list' },
								props.exclusions.slice( 0, 18 ).map( function ( path ) {
									return el(
										'li',
										{ key: path },
										el( 'code', { className: 'speculation-pilot__code' }, path )
									);
								} )
						  )
						: el( 'div', { className: 'speculation-pilot__empty' }, __( 'No active exclusions yet.', 'speculation-pilot' ) )
				),
				el(
					'div',
					{ className: 'speculation-pilot__section' },
					el( 'h2', null, __( 'Timing snapshot', 'speculation-pilot' ) ),
					el( 'p', null, __( 'p50 duration', 'speculation-pilot' ) + ': ', formatMs( report.p50Duration ) ),
					el( 'p', null, __( 'p75 duration', 'speculation-pilot' ) + ': ', formatMs( report.p75Duration ) ),
					el( 'p', null, __( 'p75 TTFB', 'speculation-pilot' ) + ': ', formatMs( report.p75Ttfb ) )
				)
			)
		);
	}

	function Rules( props ) {
		var settings = props.settings;

		return el(
			'div',
			{ className: 'speculation-pilot__section' },
			el( 'h2', null, __( 'Rules', 'speculation-pilot' ) ),
			el(
				'div',
				{ className: 'speculation-pilot__preset-grid' },
				Object.keys( presetCopy ).map( function ( key ) {
					var preset = presetCopy[ key ];
					return el(
						'button',
						{
							key: key,
							type: 'button',
							className: 'speculation-pilot__preset' + ( settings.preset === key ? ' is-active' : '' ),
							onClick: function () {
								props.applyPreset( key );
							},
						},
						el( 'strong', null, preset.title ),
						el( 'span', null, preset.description )
					);
				} )
			),
			settings.mode === 'prerender'
				? el(
						Notice,
						{
							status: 'warning',
							isDismissible: false,
						},
						__( 'Prerender can execute JavaScript before the visitor commits to a navigation. Keep checkout, cart, account, and action URLs excluded.', 'speculation-pilot' )
				  )
				: null,
			el(
				'div',
				{ className: 'speculation-pilot__form-grid' },
				el( ToggleControl, {
					label: __( 'Enable Speculation Pilot', 'speculation-pilot' ),
					checked: !! settings.enabled,
					onChange: function ( value ) {
						props.updateSetting( 'enabled', value );
					},
				} ),
				el( SelectControl, {
					label: __( 'Mode', 'speculation-pilot' ),
					value: settings.mode,
					options: [
						{ label: __( 'Core default', 'speculation-pilot' ), value: 'core_default' },
						{ label: __( 'Disabled', 'speculation-pilot' ), value: 'disabled' },
						{ label: __( 'Prefetch', 'speculation-pilot' ), value: 'prefetch' },
						{ label: __( 'Prerender', 'speculation-pilot' ), value: 'prerender' },
					],
					onChange: function ( value ) {
						props.updateSetting( 'mode', value );
					},
				} ),
				el( SelectControl, {
					label: __( 'Eagerness', 'speculation-pilot' ),
					value: settings.eagerness,
					options: [
						{ label: __( 'Core default', 'speculation-pilot' ), value: 'core_default' },
						{ label: __( 'Conservative', 'speculation-pilot' ), value: 'conservative' },
						{ label: __( 'Moderate', 'speculation-pilot' ), value: 'moderate' },
						{ label: __( 'Eager', 'speculation-pilot' ), value: 'eager' },
					],
					onChange: function ( value ) {
						props.updateSetting( 'eagerness', value );
					},
				} )
			)
		);
	}

	function Exclusions( props ) {
		var settings = props.settings;
		var newPathState = useState( '' );
		var newPath = newPathState[ 0 ];
		var setNewPath = newPathState[ 1 ];
		var isPro = config.isPro;
		var maxExclusions = config.limits ? config.limits.freeMaxExclusions : 5;
		var atLimit = ! isPro && settings.exclusions.length >= maxExclusions;

		var suggestionsState = useState( [] );
		var suggestions = suggestionsState[ 0 ];
		var setSuggestions = suggestionsState[ 1 ];

		useEffect( function () {
			request( 'routes/suggestions' ).then( function ( res ) {
				if ( res && res.suggestions ) {
					setSuggestions( res.suggestions );
				}
			} ).catch( function () {} );
		}, [] );

		function addPath() {
			var path = newPath.trim();
			if ( ! path || atLimit ) {
				return;
			}
			if ( path.charAt( 0 ) !== '/' ) {
				path = '/' + path;
			}
			props.setSettings(
				Object.assign( {}, settings, {
					exclusions: settings.exclusions.concat( [ path ] ).filter( unique ),
					preset: 'custom',
				} )
			);
			setNewPath( '' );
		}

		function removePath( path ) {
			props.setSettings(
				Object.assign( {}, settings, {
					exclusions: settings.exclusions.filter( function ( item ) {
						return item !== path;
					} ),
					preset: 'custom',
				} )
			);
		}

		return el(
			Fragment,
			null,
			el(
				'div',
				{ className: 'speculation-pilot__section' },
				el( 'h2', null, __( 'Integration presets', 'speculation-pilot' ), ! isPro ? el( ProBadge ) : null ),
				! isPro ? el( 'p', null, __( 'Integration presets require Speculation Pilot Pro.', 'speculation-pilot' ) ) : null,
				el(
					'div',
					{ className: 'speculation-pilot__form-grid' },
					el( 'div', { className: isPro ? '' : 'speculation-pilot__integration-locked' }, el( CheckboxControl, {
						label: 'WooCommerce',
						checked: !! settings.integrations.woocommerce,
						onChange: function ( value ) {
							props.updateIntegration( 'woocommerce', value );
						},
						disabled: ! isPro,
					} ) ),
					el( 'div', { className: isPro ? '' : 'speculation-pilot__integration-locked' }, el( CheckboxControl, {
						label: 'Easy Digital Downloads',
						checked: !! settings.integrations.edd,
						onChange: function ( value ) {
							props.updateIntegration( 'edd', value );
						},
						disabled: ! isPro,
					} ) ),
					el( 'div', { className: isPro ? '' : 'speculation-pilot__integration-locked' }, el( CheckboxControl, {
						label: __( 'Membership plugins', 'speculation-pilot' ),
						checked: !! settings.integrations.membership,
						onChange: function ( value ) {
							props.updateIntegration( 'membership', value );
						},
						disabled: ! isPro,
					} ) ),
					el( 'div', { className: isPro ? '' : 'speculation-pilot__integration-locked' }, el( CheckboxControl, {
						label: __( 'LMS plugins', 'speculation-pilot' ),
						checked: !! settings.integrations.lms,
						onChange: function ( value ) {
							props.updateIntegration( 'lms', value );
						},
						disabled: ! isPro,
					} ) ),
					el( 'div', { className: isPro ? '' : 'speculation-pilot__integration-locked' }, el( CheckboxControl, {
						label: __( 'Multilingual plugins', 'speculation-pilot' ),
						checked: !! settings.integrations.multilingual,
						onChange: function ( value ) {
							props.updateIntegration( 'multilingual', value );
						},
						disabled: ! isPro,
					} ) ),
					el( 'div', { className: isPro ? '' : 'speculation-pilot__integration-locked' }, el( CheckboxControl, {
						label: __( 'Cache and optimization plugins', 'speculation-pilot' ),
						checked: !! settings.integrations.cache,
						onChange: function ( value ) {
							props.updateIntegration( 'cache', value );
						},
						disabled: ! isPro,
					} ) )
				)
			),
			el(
				'div',
				{ className: 'speculation-pilot__section' },
				el( 'h2', null, __( 'Manual exclusions', 'speculation-pilot' ) ),
				el(
					'div',
					{ className: 'speculation-pilot__toolbar' },
					el( TextControl, {
						label: __( 'Path pattern', 'speculation-pilot' ),
						hideLabelFromVision: true,
						placeholder: '/checkout/*',
						value: newPath,
						onChange: setNewPath,
						disabled: atLimit,
					} ),
					el(
						Button,
						{
							variant: 'secondary',
							onClick: addPath,
							disabled: atLimit,
						},
						__( 'Add path', 'speculation-pilot' )
					)
				),
				suggestions.length
					? el(
							'div',
							{ className: 'speculation-pilot__suggestions' },
							el( 'span', { className: 'speculation-pilot__suggestions-label' }, __( 'Quick suggestions: ', 'speculation-pilot' ) ),
							suggestions.map( function ( item ) {
								var isAdded = settings.exclusions.indexOf( item.path ) !== -1;
								return el(
									Button,
									{
										key: item.path,
										variant: 'tertiary',
										isSmall: true,
										className: 'speculation-pilot__suggestion-pill' + ( isAdded ? ' is-added' : '' ),
										disabled: atLimit || isAdded,
										onClick: function () {
											if ( isAdded || atLimit ) {
												return;
											}
											props.setSettings(
												Object.assign( {}, settings, {
													exclusions: settings.exclusions.concat( [ item.path ] ).filter( unique ),
													preset: 'custom',
												} )
											);
										},
									},
									( isAdded ? '✓ ' : '+ ' ) + item.label + ' (' + item.path + ')'
								);
							} )
					  )
					: null,
				! isPro
					? el(
							'div',
							{ className: 'speculation-pilot__exclusion-counter' },
							el( 'strong', null, settings.exclusions.length + ' / ' + maxExclusions ),
							' ',
							__( 'manual exclusions used.', 'speculation-pilot' ),
							atLimit ? el( Fragment, null, ' ', __( 'Unlimited with Pro.', 'speculation-pilot' ), ' ', el( ProBadge ) ) : null
					  )
					: null,
				settings.exclusions.length
					? el(
							'ul',
							{ className: 'speculation-pilot__list' },
							settings.exclusions.map( function ( path ) {
								return el(
									'li',
									{ key: path },
									el( 'code', { className: 'speculation-pilot__code' }, path ),
									el(
										Button,
										{
											variant: 'link',
											isDestructive: true,
											onClick: function () {
												removePath( path );
											},
										},
										__( 'Remove', 'speculation-pilot' )
									)
								);
							} )
					  )
					: el( 'div', { className: 'speculation-pilot__empty' }, __( 'No manual exclusions have been added.', 'speculation-pilot' ) )
			),
			el(
				'div',
				{ className: 'speculation-pilot__section' },
				el( 'h2', null, __( 'Effective exclusion preview', 'speculation-pilot' ) ),
				props.exclusions && props.exclusions.length
					? el(
							'ul',
							{ className: 'speculation-pilot__list' },
							props.exclusions.map( function ( path ) {
								return el(
									'li',
									{ key: path },
									el( 'code', { className: 'speculation-pilot__code' }, path )
								);
							} )
					  )
					: el( 'div', { className: 'speculation-pilot__empty' }, __( 'Save settings to refresh the preview.', 'speculation-pilot' ) )
			)
		);
	}

	function Measurements( props ) {
		var report = props.report || {};
		var isPro = config.isPro;
		var maxRetention = isPro ? 365 : ( config.limits ? config.limits.freeRetentionDays : 7 );
		var totalPrerenders = ( report.topPaths || [] ).reduce( function ( total, row ) {
			return total + ( Number( row.prerenders ) || 0 );
		}, 0 );

		return el(
			Fragment,
			null,
			el(
				'div',
				{ className: 'speculation-pilot__section' },
				el( 'h2', null, __( 'Measurements', 'speculation-pilot' ) ),
				el( ToggleControl, {
					label: __( 'Enable privacy-safe local measurement', 'speculation-pilot' ),
					checked: !! props.settings.measurement_enabled,
					onChange: function ( value ) {
						props.updateSetting( 'measurement_enabled', value );
					},
				} ),
				el( TextControl, {
					label: __( 'Retention days', 'speculation-pilot' ) + ( ! isPro ? ' (' + __( 'up to 365 with Pro', 'speculation-pilot' ) + ')' : '' ),
					type: 'number',
					min: 1,
					max: maxRetention,
					value: props.settings.retention_days,
					onChange: function ( value ) {
						props.updateSetting( 'retention_days', parseInt( value, 10 ) || 7 );
					},
				} ),
				el(
					'div',
					{ className: 'speculation-pilot__toolbar' },
					el(
						Button,
						{
							variant: 'secondary',
							onClick: function () {
								downloadReportCsv( report );
							},
							disabled: ! isPro || ! report.topPaths || ! report.topPaths.length,
						},
						__( 'Export CSV', 'speculation-pilot' ),
						! isPro ? el( ProBadge ) : null
					),
					el(
						Button,
						{
							variant: 'secondary',
							onClick: function () {
								downloadPdf();
							},
							disabled: ! isPro || ! report.sampleCount,
						},
						__( 'Download PDF', 'speculation-pilot' ),
						! isPro ? el( ProBadge ) : null
					),
					el(
						Button,
						{
							variant: 'secondary',
							isDestructive: true,
							onClick: props.onClear,
							disabled: ! report.sampleCount,
						},
						__( 'Clear measurements', 'speculation-pilot' )
					)
				),
				el( 'p', null, report.privacySummary || __( 'Only local paths and timing numbers are stored.', 'speculation-pilot' ) )
			),
			el(
				'div',
				{ className: 'speculation-pilot__grid' },
				el( Metric, { label: __( 'Samples', 'speculation-pilot' ), value: report.sampleCount || 0 } ),
				el( Metric, { label: __( 'p50 duration', 'speculation-pilot' ), value: formatMs( report.p50Duration ) } ),
				el( Metric, { label: __( 'p75 duration', 'speculation-pilot' ), value: formatMs( report.p75Duration ) } ),
				el( Metric, { label: __( 'p75 TTFB', 'speculation-pilot' ), value: formatMs( report.p75Ttfb ) } ),
				el( Metric, { label: __( 'Prerender hits', 'speculation-pilot' ), value: totalPrerenders } ),
				el( Metric, { label: __( 'Retention', 'speculation-pilot' ), value: ( report.retentionDays || props.settings.retention_days ) + ' days' } )
			),
			el(
				'div',
				{ className: 'speculation-pilot__panel-grid' },
				el(
					'div',
					{ className: 'speculation-pilot__section' },
					el( 'h2', null, __( 'Daily p75 duration', 'speculation-pilot' ), ! isPro ? el( ProBadge ) : null ),
					el( ProGate, null,
						report.dailySeries && report.dailySeries.length
							? el( BarChart, {
									rows: report.dailySeries,
									labelKey: 'date',
									valueKey: 'p75Duration',
									secondaryKey: 'samples',
									valueFormatter: formatMs,
							  } )
							: el( 'div', { className: 'speculation-pilot__empty' }, __( 'No daily trend yet.', 'speculation-pilot' ) )
					)
				),
				el(
					'div',
					{ className: 'speculation-pilot__section' },
					el( 'h2', null, __( 'Mode breakdown', 'speculation-pilot' ), ! isPro ? el( ProBadge ) : null ),
					el( ProGate, null,
						report.modeBreakdown && report.modeBreakdown.length
							? el( BarChart, {
									rows: report.modeBreakdown,
									labelKey: 'mode',
									valueKey: 'samples',
									secondaryKey: 'p75Duration',
									valueFormatter: function ( value ) {
										return value + ' ' + __( 'samples', 'speculation-pilot' );
									},
									secondaryFormatter: formatMs,
							  } )
							: el( 'div', { className: 'speculation-pilot__empty' }, __( 'No mode data yet.', 'speculation-pilot' ) )
					)
				)
			),
			el(
				'div',
				{ className: 'speculation-pilot__section' },
				el( 'h2', null, __( 'Path groups', 'speculation-pilot' ), ! isPro ? el( ProBadge ) : null ),
				el( ProGate, null,
					report.pathGroups && report.pathGroups.length
						? el(
								'table',
								{ className: 'speculation-pilot__table' },
								el(
									'thead',
									null,
									el(
										'tr',
										null,
										el( 'th', null, __( 'Group', 'speculation-pilot' ) ),
										el( 'th', null, __( 'Samples', 'speculation-pilot' ) ),
										el( 'th', null, __( 'p75 duration', 'speculation-pilot' ) ),
										el( 'th', null, __( 'Prerenders', 'speculation-pilot' ) )
									)
								),
								el(
									'tbody',
									null,
									report.pathGroups.map( function ( row ) {
										return el(
											'tr',
											{ key: row.group },
											el( 'td', null, el( 'code', { className: 'speculation-pilot__code' }, row.group ) ),
											el( 'td', null, row.samples ),
											el( 'td', null, formatMs( row.p75Duration ) ),
											el( 'td', null, row.prerenders )
										);
									} )
								)
						  )
						: el( 'div', { className: 'speculation-pilot__empty' }, __( 'No grouped path data yet.', 'speculation-pilot' ) )
				)
			),
			el(
				'div',
				{ className: 'speculation-pilot__section' },
				el( 'h2', null, __( 'Top paths', 'speculation-pilot' ) ),
				report.topPaths && report.topPaths.length
					? el(
							'table',
							{ className: 'speculation-pilot__table' },
							el(
								'thead',
								null,
								el(
									'tr',
									null,
									el( 'th', null, __( 'Path', 'speculation-pilot' ) ),
									el( 'th', null, __( 'Samples', 'speculation-pilot' ) ),
									el( 'th', null, __( 'p75 duration', 'speculation-pilot' ) ),
									el( 'th', null, __( 'Prerenders', 'speculation-pilot' ) )
								)
							),
							el(
								'tbody',
								null,
								report.topPaths.map( function ( row ) {
									return el(
										'tr',
										{ key: row.path },
										el( 'td', null, el( 'code', { className: 'speculation-pilot__code' }, row.path ) ),
										el( 'td', null, row.samples ),
										el( 'td', null, formatMs( row.p75Duration ) ),
										el( 'td', null, row.prerenders )
									);
								} )
							)
					  )
					: el( 'div', { className: 'speculation-pilot__empty' }, __( 'No measurements yet.', 'speculation-pilot' ) )
			)
		);
	}

	function BarChart( props ) {
		var values = ( props.rows || [] ).map( function ( row ) {
			return Number( row[ props.valueKey ] ) || 0;
		} );
		var max = Math.max.apply( Math, values.concat( [ 1 ] ) );

		return el(
			'div',
			{ className: 'speculation-pilot__bars' },
			props.rows.map( function ( row ) {
				var value = Number( row[ props.valueKey ] ) || 0;
				var width = Math.max( 4, Math.round( ( value / max ) * 100 ) );
				var secondary = props.secondaryKey ? row[ props.secondaryKey ] : null;
				var secondaryText = secondary !== null && typeof secondary !== 'undefined'
					? ( props.secondaryFormatter ? props.secondaryFormatter( secondary ) : secondary )
					: null;

				return el(
					'div',
					{ className: 'speculation-pilot__bar-row', key: row[ props.labelKey ] },
					el( 'div', { className: 'speculation-pilot__bar-label' }, row[ props.labelKey ] ),
					el(
						'div',
						{ className: 'speculation-pilot__bar-track' },
						el( 'div', {
							className: 'speculation-pilot__bar-fill',
							style: { width: width + '%' },
						} )
					),
					el( 'div', { className: 'speculation-pilot__bar-value' }, props.valueFormatter ? props.valueFormatter( value ) : value ),
					secondaryText ? el( 'div', { className: 'speculation-pilot__bar-secondary' }, secondaryText ) : null
				);
			} )
		);
	}

	function Diagnostics( props ) {
		var diagnostics = props.diagnostics || { items: [] };

		return el(
			Fragment,
			null,
			el(
				'div',
				{ className: 'speculation-pilot__section' },
				el( 'h2', null, __( 'Diagnostics', 'speculation-pilot' ) ),
				el( 'p', null, __( 'Overall status', 'speculation-pilot' ), ' ', el( Status, { status: diagnostics.status || 'info' } ) ),
				el(
					'ul',
					{ className: 'speculation-pilot__list' },
					( diagnostics.items || [] ).map( function ( item ) {
						return el(
							'li',
							{ key: item.key },
							el(
								'div',
								null,
								el( 'strong', null, item.label ),
								el( 'p', null, item.message )
							),
							el( Status, { status: item.status } )
						);
					} )
				)
			),
			el(
				'div',
				{ className: 'speculation-pilot__section' },
				el( 'h2', null, __( 'Link opt-out selectors', 'speculation-pilot' ) ),
				diagnostics.selectors && diagnostics.selectors.length
					? el(
							'ul',
							{ className: 'speculation-pilot__list' },
							diagnostics.selectors.map( function ( selector ) {
								return el(
									'li',
									{ key: selector },
									el( 'code', { className: 'speculation-pilot__code' }, selector )
								);
							} )
					  )
					: el( 'div', { className: 'speculation-pilot__empty' }, __( 'No selector preview is available.', 'speculation-pilot' ) )
			),
			config.isPro ? el( SiteAudit ) : el( ProGate, null, el( SiteAudit ) )
		);
	}

	function SettingsPanel( props ) {
		var isPro = config.isPro;
		var proActive = config.proPluginActive;

		var licenseKeyState = useState( '' );
		var licenseKey = licenseKeyState[ 0 ];
		var setLicenseKey = licenseKeyState[ 1 ];

		var licenseBusyState = useState( false );
		var licenseBusy = licenseBusyState[ 0 ];
		var setLicenseBusy = licenseBusyState[ 1 ];

		var licenseInfoState = useState( config.license || null );
		var licenseInfo = licenseInfoState[ 0 ];
		var setLicenseInfo = licenseInfoState[ 1 ];

		var licenseNoticeState = useState( null );
		var licenseNotice = licenseNoticeState[ 0 ];
		var setLicenseNotice = licenseNoticeState[ 1 ];

		function activateLicense() {
			if ( ! licenseKey.trim() ) {
				return;
			}
			setLicenseBusy( true );
			setLicenseNotice( null );
			request( 'license/activate', { method: 'POST', data: { key: licenseKey.trim() } } )
				.then( function ( response ) {
					setLicenseInfo( response.license || null );
					setLicenseNotice( {
						status: response.success ? 'success' : 'error',
						message: response.message,
					} );
					if ( response.success ) {
						setLicenseKey( '' );
						// Reload page to pick up newly-unlocked features.
						window.location.reload();
					}
				} )
				.catch( function ( error ) {
					setLicenseNotice( {
						status: 'error',
						message: error && error.message ? error.message : __( 'Activation failed.', 'speculation-pilot' ),
					} );
				} )
				.finally( function () {
					setLicenseBusy( false );
				} );
		}

		function deactivateLicense() {
			if ( ! window.confirm( __( 'Deactivate and remove the license key from this site?', 'speculation-pilot' ) ) ) {
				return;
			}
			setLicenseBusy( true );
			setLicenseNotice( null );
			request( 'license/deactivate', { method: 'POST' } )
				.then( function ( response ) {
					setLicenseInfo( response.license || null );
					setLicenseNotice( {
						status: response.success ? 'success' : 'error',
						message: response.message,
					} );
					// Reload to re-gate features.
					window.location.reload();
				} )
				.catch( function ( error ) {
					setLicenseNotice( {
						status: 'error',
						message: error && error.message ? error.message : __( 'Deactivation failed.', 'speculation-pilot' ),
					} );
				} )
				.finally( function () {
					setLicenseBusy( false );
				} );
		}

		function recheckLicense() {
			setLicenseBusy( true );
			setLicenseNotice( null );
			request( 'license/check', { method: 'POST' } )
				.then( function ( response ) {
					setLicenseInfo( response.license || null );
					setLicenseNotice( {
						status: response.success ? 'success' : 'warning',
						message: response.message,
					} );
				} )
				.catch( function ( error ) {
					setLicenseNotice( {
						status: 'error',
						message: error && error.message ? error.message : __( 'Check failed.', 'speculation-pilot' ),
					} );
				} )
				.finally( function () {
					setLicenseBusy( false );
				} );
		}

		var hasKey = licenseInfo && licenseInfo.hasKey;

		return el(
			Fragment,
			null,
			el(
				'div',
				{ className: 'speculation-pilot__section' },
				el( 'h2', null, __( 'Settings', 'speculation-pilot' ) ),
				el( ToggleControl, {
					label: __( 'Delete plugin data on uninstall', 'speculation-pilot' ),
					checked: !! props.settings.cleanup_on_uninstall,
					onChange: function ( value ) {
						props.updateSetting( 'cleanup_on_uninstall', value );
					},
				} ),
				el( ToggleControl, {
					label: __( 'I understand prerender safety tradeoffs', 'speculation-pilot' ),
					checked: !! props.settings.prerender_warning_seen,
					onChange: function ( value ) {
						props.updateSetting( 'prerender_warning_seen', value );
					},
				} )
			),
			config.isPro ? el( EmailSettings ) : el( ProGate, null, el( EmailSettings ) ),
			el(
				'div',
				{ className: 'speculation-pilot__section' },
				el( 'h2', null, __( 'License', 'speculation-pilot' ) ),
				licenseNotice
					? el(
							Notice,
							{
								status: licenseNotice.status,
								isDismissible: true,
								onRemove: function () {
									setLicenseNotice( null );
								},
							},
							licenseNotice.message
					  )
					: null,
				proActive
					? el(
							Fragment,
							null,
							isPro
								? el(
										Fragment,
										null,
										el( 'p', null, '✅ ', __( 'Pro license is active. All features are unlocked.', 'speculation-pilot' ) ),
										hasKey
											? el(
													'div',
													{ className: 'speculation-pilot__license-panel' },
													el( 'p', null, __( 'License key:', 'speculation-pilot' ), ' ', el( 'code', { className: 'speculation-pilot__code' }, licenseInfo.maskedKey ) ),
													licenseInfo.expires && licenseInfo.expires !== 'lifetime'
														? el( 'p', null, __( 'Expires:', 'speculation-pilot' ), ' ', licenseInfo.expires )
														: licenseInfo.expires === 'lifetime'
															? el( 'p', null, __( 'Expires:', 'speculation-pilot' ), ' ', __( 'Lifetime', 'speculation-pilot' ) )
															: null,
													licenseInfo.customerEmail
														? el( 'p', null, __( 'Customer:', 'speculation-pilot' ), ' ', licenseInfo.customerEmail )
														: null,
													el(
														'div',
														{ className: 'speculation-pilot__toolbar' },
														el(
															Button,
															{
																variant: 'secondary',
																onClick: recheckLicense,
																disabled: licenseBusy,
																isBusy: licenseBusy,
															},
															__( 'Re-check', 'speculation-pilot' )
														),
														el(
															Button,
															{
																variant: 'secondary',
																isDestructive: true,
																onClick: deactivateLicense,
																disabled: licenseBusy,
															},
															__( 'Deactivate', 'speculation-pilot' )
														)
													)
											  )
											: null
								  )
								: el(
										'div',
										{ className: 'speculation-pilot__license-panel' },
										hasKey
											? el(
													Fragment,
													null,
													el( 'p', null, '⚠️ ', __( 'License key is present but not valid.', 'speculation-pilot' ), ' ', el( 'code', { className: 'speculation-pilot__code' }, licenseInfo.maskedKey ), ' — ', __( 'Status:', 'speculation-pilot' ), ' ', licenseInfo.status ),
													el(
														'div',
														{ className: 'speculation-pilot__toolbar' },
														el(
															Button,
															{
																variant: 'secondary',
																onClick: recheckLicense,
																disabled: licenseBusy,
																isBusy: licenseBusy,
															},
															__( 'Re-check', 'speculation-pilot' )
														),
														el(
															Button,
															{
																variant: 'secondary',
																isDestructive: true,
																onClick: deactivateLicense,
																disabled: licenseBusy,
															},
															__( 'Remove key', 'speculation-pilot' )
														)
													)
											  )
											: null,
										el( 'p', null, __( 'Enter your license key to unlock all Pro features.', 'speculation-pilot' ) ),
										el(
											'div',
											{ className: 'speculation-pilot__toolbar' },
											el( TextControl, {
												label: __( 'License key', 'speculation-pilot' ),
												hideLabelFromVision: true,
												placeholder: 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
												value: licenseKey,
												onChange: setLicenseKey,
												disabled: licenseBusy,
											} ),
											el(
												Button,
												{
													variant: 'primary',
													onClick: activateLicense,
													disabled: licenseBusy || ! licenseKey.trim(),
													isBusy: licenseBusy,
												},
												__( 'Activate', 'speculation-pilot' )
											)
										)
								  )
					  )
					: el(
							'div',
							{ className: 'speculation-pilot__license-panel' },
							el( 'p', null, __( 'You are using the free version of Speculation Pilot.', 'speculation-pilot' ) ),
							el( 'p', null, __( 'Upgrade to Pro for WooCommerce presets, 365-day history, advanced reports, PDF exports, and more.', 'speculation-pilot' ) ),
							el(
								Button,
								{
									variant: 'primary',
									href: config.upgradeUrl || 'https://speculationpilot.com/pricing/',
									target: '_blank',
								},
								__( 'Get Speculation Pilot Pro →', 'speculation-pilot' )
							)
					  )
			)
		);
	}

	function downloadPdf() {
		request( 'pro/report/pdf' )
			.then( function ( response ) {
				if ( response.success && response.url ) {
					var link = document.createElement( 'a' );
					link.href = response.url;
					link.download = response.filename || 'speculation-pilot-report.pdf';
					document.body.appendChild( link );
					link.click();
					document.body.removeChild( link );
				} else {
					window.alert( response.message || __( 'PDF generation failed.', 'speculation-pilot' ) );
				}
			} )
			.catch( function ( error ) {
				window.alert( error && error.message ? error.message : __( 'PDF generation failed.', 'speculation-pilot' ) );
			} );
	}

	function SiteAudit() {
		var auditState = useState( null );
		var audit = auditState[ 0 ];
		var setAudit = auditState[ 1 ];

		var loadingState = useState( false );
		var auditLoading = loadingState[ 0 ];
		var setAuditLoading = loadingState[ 1 ];

		function runAudit() {
			setAuditLoading( true );
			request( 'pro/audit' )
				.then( function ( response ) {
					setAudit( response );
				} )
				.catch( function () {
					setAudit( null );
				} )
				.finally( function () {
					setAuditLoading( false );
				} );
		}

		var severityColors = { OK: '#00a32a', INFO: '#2271b1', WARNING: '#dba617', ERROR: '#d63638' };

		return el(
			'div',
			{ className: 'speculation-pilot__section' },
			el( 'h2', null, __( 'Site Audit', 'speculation-pilot' ), ' ', el( ProBadge ) ),
			el( 'p', null, __( 'Run a comprehensive site audit to check your environment, configuration, exclusions, and content volume.', 'speculation-pilot' ) ),
			el(
				Button,
				{
					variant: 'secondary',
					onClick: runAudit,
					isBusy: auditLoading,
					disabled: auditLoading,
				},
				auditLoading ? __( 'Running audit...', 'speculation-pilot' ) : __( 'Run site audit', 'speculation-pilot' )
			),
			audit
				? el(
						Fragment,
						null,
						el( 'p', { style: { marginTop: '12px' } },
							__( 'Overall:', 'speculation-pilot' ), ' ',
							el( 'strong', { style: { color: severityColors[ audit.status ] || '#1d2327' } }, audit.status )
						),
						el(
							'table',
							{ className: 'speculation-pilot__table', style: { marginTop: '12px' } },
							el( 'thead', null, el( 'tr', null,
								el( 'th', null, __( 'Check', 'speculation-pilot' ) ),
								el( 'th', null, __( 'Status', 'speculation-pilot' ) ),
								el( 'th', null, __( 'Details', 'speculation-pilot' ) )
							) ),
							el( 'tbody', null,
								( audit.checks || [] ).map( function ( check ) {
									return el( 'tr', { key: check.key },
										el( 'td', null, el( 'strong', null, check.title ) ),
										el( 'td', null, el( 'span', { style: { color: severityColors[ check.severity ] || '#1d2327', fontWeight: 600 } }, check.severity ) ),
										el( 'td', null, check.details )
									);
								} )
							)
						)
				  )
				: null
		);
	}

	function EmailSettings() {
		var emailState = useState( { email_enabled: false, email_recipients: '', email_frequency: 'weekly', email_branding: true } );
		var email = emailState[ 0 ];
		var setEmail = emailState[ 1 ];

		var loadedState = useState( false );
		var loaded = loadedState[ 0 ];
		var setLoaded = loadedState[ 1 ];

		var busyState = useState( false );
		var busy = busyState[ 0 ];
		var setBusy = busyState[ 1 ];

		var noticeState = useState( null );
		var emailNotice = noticeState[ 0 ];
		var setEmailNotice = noticeState[ 1 ];

		useEffect( function () {
			request( 'pro/email/settings' )
				.then( function ( response ) {
					setEmail( response );
					setLoaded( true );
				} )
				.catch( function () {
					setLoaded( true );
				} );
		}, [] );

		function saveEmail() {
			setBusy( true );
			setEmailNotice( null );
			request( 'pro/email/settings', { method: 'POST', data: email } )
				.then( function ( response ) {
					setEmail( response );
					setEmailNotice( { status: 'success', message: __( 'Email settings saved.', 'speculation-pilot' ) } );
				} )
				.catch( function ( error ) {
					setEmailNotice( { status: 'error', message: error && error.message ? error.message : __( 'Failed to save.', 'speculation-pilot' ) } );
				} )
				.finally( function () {
					setBusy( false );
				} );
		}

		function sendTest() {
			setBusy( true );
			setEmailNotice( null );
			request( 'pro/email/test', { method: 'POST' } )
				.then( function ( response ) {
					setEmailNotice( { status: response.success ? 'success' : 'error', message: response.message } );
				} )
				.catch( function ( error ) {
					setEmailNotice( { status: 'error', message: error && error.message ? error.message : __( 'Send failed.', 'speculation-pilot' ) } );
				} )
				.finally( function () {
					setBusy( false );
				} );
		}

		if ( ! loaded ) {
			return el( 'div', { className: 'speculation-pilot__section' }, el( Spinner ), ' ', __( 'Loading email settings...', 'speculation-pilot' ) );
		}

		return el(
			'div',
			{ className: 'speculation-pilot__section' },
			el( 'h2', null, __( 'Email Reports', 'speculation-pilot' ), ' ', el( ProBadge ) ),
			emailNotice
				? el( Notice, { status: emailNotice.status, isDismissible: true, onRemove: function () { setEmailNotice( null ); } }, emailNotice.message )
				: null,
			el( ToggleControl, {
				label: __( 'Enable scheduled email reports', 'speculation-pilot' ),
				checked: !! email.email_enabled,
				onChange: function ( val ) { setEmail( Object.assign( {}, email, { email_enabled: val } ) ); },
			} ),
			el( TextControl, {
				label: __( 'Recipients (comma-separated)', 'speculation-pilot' ),
				value: email.email_recipients || '',
				placeholder: 'admin@example.com',
				onChange: function ( val ) { setEmail( Object.assign( {}, email, { email_recipients: val } ) ); },
			} ),
			el( SelectControl, {
				label: __( 'Frequency', 'speculation-pilot' ),
				value: email.email_frequency || 'weekly',
				options: [
					{ label: __( 'Weekly', 'speculation-pilot' ), value: 'weekly' },
					{ label: __( 'Monthly', 'speculation-pilot' ), value: 'monthly' },
				],
				onChange: function ( val ) { setEmail( Object.assign( {}, email, { email_frequency: val } ) ); },
			} ),
			el( ToggleControl, {
				label: __( 'Include "Powered by Speculation Pilot Pro" branding', 'speculation-pilot' ),
				checked: !! email.email_branding,
				onChange: function ( val ) { setEmail( Object.assign( {}, email, { email_branding: val } ) ); },
			} ),
			el(
				'div',
				{ className: 'speculation-pilot__toolbar' },
				el( Button, { variant: 'primary', onClick: saveEmail, isBusy: busy, disabled: busy }, __( 'Save email settings', 'speculation-pilot' ) ),
				el( Button, { variant: 'secondary', onClick: sendTest, disabled: busy }, __( 'Send test email', 'speculation-pilot' ) )
			)
		);
	}

	function formatMs( value ) {
		if ( value === null || typeof value === 'undefined' ) {
			return 'n/a';
		}
		return Math.round( Number( value ) ) + ' ms';
	}

	function unique( value, index, array ) {
		return array.indexOf( value ) === index;
	}

	function downloadReportCsv( report ) {
		var rows = [ [ 'path', 'samples', 'p75_duration_ms', 'prerenders', 'last_sample' ] ];
		( report.topPaths || [] ).forEach( function ( row ) {
			rows.push( [
				row.path,
				row.samples,
				row.p75Duration || '',
				row.prerenders || 0,
				row.lastSample || '',
			] );
		} );

		var csv = rows
			.map( function ( row ) {
				return row
					.map( function ( cell ) {
						return '"' + String( cell ).replace( /"/g, '""' ) + '"';
					} )
					.join( ',' );
			} )
			.join( '\n' );
		var blob = new Blob( [ csv ], { type: 'text/csv;charset=utf-8' } );
		var url = window.URL.createObjectURL( blob );
		var link = document.createElement( 'a' );
		link.href = url;
		link.download = 'speculation-pilot-report.csv';
		document.body.appendChild( link );
		link.click();
		document.body.removeChild( link );
		window.URL.revokeObjectURL( url );
	}

	var root = document.getElementById( 'speculation-pilot-admin-root' );
	if ( root ) {
		if ( wp.element.createRoot ) {
			wp.element.createRoot( root ).render( el( App ) );
		} else {
			wp.element.render( el( App ), root );
		}
	}
} )( window.wp, window.SpeculationPilotAdmin );
