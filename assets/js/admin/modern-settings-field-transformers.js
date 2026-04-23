/**
 * Custom field-type transformers for the modernised settings SDK.
 *
 * Registered via `window.wcReactSettings.registerFieldTypeTransformer` — the
 * runtime JS extension point documented in
 * docs/extensions/settings-and-config/registering-custom-field-types.md.
 *
 * Currently handled: `multiselect`. The SDK's native baseFieldTransformer
 * maps multiselect onto `DataForm` `type: 'array'`, which has no built-in
 * Edit component in the WC 10.8-dev DataViews bundle, causing the whole
 * section to throw on render. This file provides a `FormTokenField`-based
 * Edit using `@wordpress/components` — a token/pill control that mirrors
 * the legacy `wc-enhanced-select` (Select2) experience the WooPayments
 * gateway used in its form_fields entries before the modern SDK existed.
 *
 * Drops out cleanly if/when `DataForm.Fields.extend()` ships upstream.
 *
 * @package
 */
( function ( wp ) {
	'use strict';

	if (
		! window.wcReactSettings ||
		typeof window.wcReactSettings.registerFieldTypeTransformer !==
			'function' ||
		! wp ||
		! wp.element ||
		! wp.components
	) {
		return;
	}

	const { createElement, useMemo } = wp.element;
	const { BaseControl, FormTokenField } = wp.components;

	/**
	 * Normalize options into `[ { label, value }, ... ]` shape.
	 *
	 * Handles the three shapes `ReactSettingsSchema::normalize_options()` can emit:
	 *   - `{ value: label }` (preserved-key object form from the default normalizer)
	 *   - `[ { label, value } ]` (list-of-objects form from interface contributors)
	 *   - `[ label, label ]` (legacy indexed array)
	 *
	 * @param {*} options Raw options from the payload.
	 * @return {Array<{label: string, value: string}>} Normalized options.
	 */
	function parseOptions( options ) {
		if ( ! options ) {
			return [];
		}
		if ( Array.isArray( options ) ) {
			return options
				.map( function ( option ) {
					if ( option && typeof option === 'object' ) {
						return {
							label: String(
								option.label != null
									? option.label
									: option.value
							),
							value: String(
								option.value != null ? option.value : ''
							),
						};
					}
					return { label: String( option ), value: String( option ) };
				} )
				.filter( function ( option ) {
					return option.value !== '';
				} );
		}
		return Object.keys( options ).map( function ( key ) {
			return { label: String( options[ key ] ), value: String( key ) };
		} );
	}

	/**
	 * Read the field's current value from the form-wide data bag as a string array.
	 *
	 * @param {Object} data  Form-wide values object from DataForm.
	 * @param {Object} field DataForm field shape.
	 * @return {string[]} Current selection as string values.
	 */
	function readArrayValue( data, field ) {
		const raw = data && field ? data[ field.id ] : undefined;
		if ( Array.isArray( raw ) ) {
			return raw.map( String );
		}
		if ( raw == null || raw === '' ) {
			return [];
		}
		return [ String( raw ) ];
	}

	/**
	 * Build a value↔label index for an options list.
	 *
	 * Labels can collide across different values in theory, but none of the
	 * multiselect fields on the WooPayments gateway settings do — for label
	 * collisions we'd need a disambiguator, which is out of PoC scope.
	 *
	 * @param {Array<{label: string, value: string}>} options Parsed options.
	 * @return {{ valueToLabel: Object<string, string>, labelToValue: Object<string, string>, labels: string[] }}
	 *   Bidirectional maps plus the ordered label list for FormTokenField suggestions.
	 */
	function buildIndex( options ) {
		const valueToLabel = {};
		const labelToValue = {};
		const labels = [];
		options.forEach( function ( option ) {
			valueToLabel[ option.value ] = option.label;
			labelToValue[ option.label ] = option.value;
			labels.push( option.label );
		} );
		return { valueToLabel, labelToValue, labels };
	}

	/**
	 * Factory that returns an Edit component bound to a specific field's options.
	 *
	 * DataForm re-uses the same Edit across renders for a given field, so binding
	 * the options list and index at transform time avoids re-indexing on every
	 * keystroke.
	 *
	 * @param {Array<{label: string, value: string}>}             options   Parsed options.
	 * @param {{id: string, label: string, description?: string}} baseField Partially-normalized
	 *                                                                      DataForm field
	 *                                                                      shape.
	 * @return {Function} React component for the field's Edit.
	 */
	function makeMultiselectEdit( options, baseField ) {
		const index = buildIndex( options );

		return function MultiselectEdit( props ) {
			const currentValues = readArrayValue( props.data, props.field );

			// FormTokenField works with labels as its tokens. Map stored values
			// to their labels for display; filter out any stale values whose
			// options have been removed since the value was last persisted.
			const selectedLabels = useMemo(
				function () {
					return currentValues
						.map( function ( value ) {
							return index.valueToLabel[ value ];
						} )
						.filter( Boolean );
				},
				// eslint-disable-next-line react-hooks/exhaustive-deps
				[ currentValues.join( '|' ) ]
			);

			function handleChange( nextLabels ) {
				// FormTokenField may return strings that are NOT in the
				// suggestion list when a user types a free-form value. We
				// only persist known values — unknown tokens are dropped.
				const nextValues = nextLabels
					.map( function ( label ) {
						return index.labelToValue[ String( label ) ];
					} )
					.filter( function ( value ) {
						return typeof value === 'string';
					} );

				const update = {};
				update[ props.field.id ] = nextValues;
				props.onChange( update );
			}

			return createElement(
				BaseControl,
				{
					label: props.hideLabelFromVision
						? undefined
						: baseField.label,
					help: baseField.description,
					__nextHasNoMarginBottom: true,
				},
				createElement( FormTokenField, {
					value: selectedLabels,
					suggestions: index.labels,
					onChange: handleChange,
					// Keep the control focused on the known option set — no
					// free-form values sneak in.
					__experimentalExpandOnFocus: true,
					__experimentalAutoSelectFirstMatch: true,
					__nextHasNoMarginBottom: true,
					// Visually hide FormTokenField's own label; BaseControl
					// already renders the outer one so the DataForm layout
					// stays consistent with native fields.
					label: '',
				} )
			);
		};
	}

	window.wcReactSettings.registerFieldTypeTransformer(
		'multiselect',
		function ( setting, baseField ) {
			const options = parseOptions( setting.options );
			if ( options.length === 0 ) {
				// Fall through to the SDK's default handling — nothing useful we can render.
				return baseField;
			}

			return Object.assign( {}, baseField, {
				// `text` keeps DataForm from looking up its broken `array` built-in;
				// the Edit below takes over rendering entirely.
				type: 'text',
				elements: options,
				Edit: makeMultiselectEdit( options, baseField ),
			} );
		}
	);
} )( window.wp );
