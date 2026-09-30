/** @format */

/**
 * External dependencies
 */
import React, { Component, lazy, Suspense, useEffect } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
// Loaded from the eager wrapper rather than the lazy component so the form
// layout (`.save-details-form`, `.iti`, `.phone-input`) is styled before the
// component's JS chunk arrives — only the heavy `intl-tel-input` JS is deferred.
import './style.scss';

const PhoneNumberInput = lazy( () =>
	import( /* webpackChunkName: "wcpay-phone-input" */ './' )
);

// Degraded validity check for the fallback field: a digit-bearing number passes;
// the authoritative check stays on the server. Kept at module scope so it's a
// stable reference for the effect below.
const isPlainNumberValid = ( phone ) => phone.replace( /\D/g, '' ).length >= 6;

// Mirrors the real input's markup so swapping in the loaded component causes no
// layout shift. The `.iti` wrapper matches the one intl-tel-input injects around
// the input on mount, so the placeholder reserves its `margin-top` and the field
// doesn't jump once the component's chunk loads.
const PhoneInputPlaceholder = ( { id, inputProps = {}, isBlocksCheckout } ) => (
	<div className={ isBlocksCheckout ? 'wc-block-components-text-input' : '' }>
		<div className="iti">
			<input
				type="tel"
				id={ id }
				className="phone-input input-text"
				placeholder={ __( 'Mobile number', 'woocommerce-payments' ) }
				aria-label={
					inputProps.ariaLabel ||
					__( 'Mobile phone number', 'woocommerce-payments' )
				}
				name={ inputProps.name }
				disabled
			/>
		</div>
	</div>
);

// Rendered when the component's chunk fails to load. Without it, the blocks
// checkout can get stuck: the save-my-info phone validation only clears once the
// field reports a valid number, so a field that never mounts leaves "Place Order"
// blocked behind a hidden error. This degrades to a plain phone field — no
// country dropdown or libphonenumber formatting — wired to the same callbacks so
// the shopper can still enter a number and complete checkout. Passthrough props
// (e.g. the `onClick` that marks the field as touched) are forwarded so it behaves
// like the real input.
const PlainPhoneInput = ( {
	value = '',
	id,
	onValueChange = () => {},
	onValidationChange = () => {},
	// Destructured out so they aren't spread onto the DOM input.
	onCountryDropdownClick,
	inputProps = {},
	isBlocksCheckout,
	...rest
} ) => {
	// Validate any prefilled value (e.g. ticking "save my info" seeds the number
	// from the billing field) so a valid number clears the checkout-blocking error
	// without the shopper having to retype it.
	useEffect( () => {
		if ( value ) {
			onValidationChange( isPlainNumberValid( value ) );
		}
	}, [ value, onValidationChange ] );

	return (
		<div
			className={
				isBlocksCheckout ? 'wc-block-components-text-input' : ''
			}
		>
			<input
				type="tel"
				id={ id }
				value={ value }
				className="phone-input input-text"
				placeholder={ __( 'Mobile number', 'woocommerce-payments' ) }
				aria-label={
					inputProps.ariaLabel ||
					__( 'Mobile phone number', 'woocommerce-payments' )
				}
				name={ inputProps.name }
				onChange={ ( event ) => onValueChange( event.target.value ) }
				{ ...rest }
			/>
		</div>
	);
};

class ChunkErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { hasError: false };
	}

	static getDerivedStateFromError() {
		return { hasError: true };
	}

	render() {
		if ( this.state.hasError ) {
			return this.props.fallback;
		}

		return this.props.children;
	}
}

const LazyPhoneNumberInput = ( props ) => (
	<ChunkErrorBoundary fallback={ <PlainPhoneInput { ...props } /> }>
		<Suspense fallback={ <PhoneInputPlaceholder { ...props } /> }>
			<PhoneNumberInput { ...props } />
		</Suspense>
	</ChunkErrorBoundary>
);

export default LazyPhoneNumberInput;
