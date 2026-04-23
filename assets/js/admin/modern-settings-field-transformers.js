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
 * section to throw on render. This file provides a `CheckboxControl`-based
 * Edit using `@wordpress/components`, which keeps multiselect fields
 * renderable today and drops out cleanly if/when `DataForm.Fields.extend()`
 * ships upstream.
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

	const createElement = wp.element.createElement;
	const Fragment = wp.element.Fragment;
	const BaseControl = wp.components.BaseControl;
	const CheckboxControl = wp.components.CheckboxControl;

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
	 * DataForm re-uses the same Edit across renders for a given field, so binding
	 * the options list at transform time avoids parsing on every keystroke.
	 *
	 * @param {Array<{label: string, value: string}>}             options
	 * @param {{id: string, label: string, description?: string}} baseField
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

			const checkboxes = options.map( function ( option ) {
				return createElement( CheckboxControl, {
					key: option.value,
					label: option.label,
					checked: current.indexOf( option.value ) !== -1,
					onChange: function ( checked ) {
						toggle( option.value, checked );
					},
					__nextHasNoMarginBottom: true,
				} );
			} );

			return createElement(
				BaseControl,
				{
					label: props.hideLabelFromVision
						? undefined
						: baseField.label,
					help: baseField.description,
					__nextHasNoMarginBottom: true,
				},
				createElement( Fragment, null, checkboxes )
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
