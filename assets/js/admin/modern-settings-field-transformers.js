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
 * section to throw on render. This file provides a row-per-option Edit
 * using `@wordpress/components` — a ToggleControl per option stacked in a
 * labelled list, matching the per-method row style the existing
 * WooPayments React admin uses in its "Express checkouts" and "Payment
 * methods" sections. Accompanying styles live in
 * `assets/css/admin/modern-settings-field-transformers.css`.
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

	const { createElement } = wp.element;
	const { BaseControl, ToggleControl } = wp.components;

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
	 * Factory that returns an Edit component bound to a specific field's options.
	 *
	 * DataForm re-uses the same Edit across renders for a given field, so the
	 * options list is captured in the closure rather than re-parsed on every
	 * keystroke.
	 *
	 * @param {Array<{label: string, value: string}>}             options   Parsed options.
	 * @param {{id: string, label: string, description?: string}} baseField Partially-normalized
	 *                                                                      DataForm field
	 *                                                                      shape.
	 * @return {Function} React component for the field's Edit.
	 */
	function makeMultiselectEdit( options, baseField ) {
		return function MultiselectEdit( props ) {
			const current = readArrayValue( props.data, props.field );

			function toggle( optionValue, checked ) {
				let next;
				if ( checked ) {
					next =
						current.indexOf( optionValue ) === -1
							? current.concat( [ optionValue ] )
							: current;
				} else {
					next = current.filter( function ( v ) {
						return v !== optionValue;
					} );
				}

				const update = {};
				update[ props.field.id ] = next;
				props.onChange( update );
			}

			const rows = options.map( function ( option ) {
				const checked = current.indexOf( option.value ) !== -1;
				return createElement(
					'div',
					{
						key: option.value,
						className: 'wcpay-modern-settings-multiselect__row',
					},
					createElement(
						'div',
						{
							className:
								'wcpay-modern-settings-multiselect__row-label',
						},
						option.label
					),
					createElement( ToggleControl, {
						checked,
						onChange: function ( nextChecked ) {
							toggle( option.value, nextChecked );
						},
						'aria-label': option.label,
						__nextHasNoMarginBottom: true,
					} )
				);
			} );

			return createElement(
				BaseControl,
				{
					label: props.hideLabelFromVision
						? undefined
						: baseField.label,
					help: baseField.description,
					__nextHasNoMarginBottom: true,
					className: 'wcpay-modern-settings-multiselect',
				},
				createElement(
					'div',
					{ className: 'wcpay-modern-settings-multiselect__rows' },
					rows
				)
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
