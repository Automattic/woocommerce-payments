/** @format */

/**
 * External dependencies
 */
import React, { Component, lazy, Suspense } from 'react';
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

// Mirrors the real input's markup so swapping in the loaded component causes no
// layout shift. The `.iti` wrapper matches the one intl-tel-input injects around
// the input on mount, so the placeholder reserves its `margin-top` and the field
// doesn't jump once the component's chunk loads.
const PhoneInputPlaceholder = ( { id, isBlocksCheckout } ) => (
	<div className={ isBlocksCheckout ? 'wc-block-components-text-input' : '' }>
		<div className="iti">
			<input
				type="tel"
				id={ id }
				className="phone-input input-text"
				placeholder={ __( 'Mobile number', 'woocommerce-payments' ) }
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
// the shopper can still enter a number and complete checkout. The server
// validates the value.
const PlainPhoneInput = ( {
	value = '',
	id,
	onValueChange = () => {},
	onValidationChange = () => {},
	inputProps = {},
	isBlocksCheckout,
} ) => {
	const handleChange = ( event ) => {
		const phone = event.target.value;
		onValueChange( phone );
		// Mirror the enhanced input's "hidden until typed" behaviour: only report
		// validity once the shopper interacts, treating a digit-bearing number as
		// valid and leaving stricter checks to the server.
		onValidationChange( phone.replace( /\D/g, '' ).length >= 6 );
	};

	return (
		<div
			className={
				isBlocksCheckout ? 'wc-block-components-text-input' : ''
			}
		>
			<input
				type="tel"
				id={ id }
				defaultValue={ value }
				className="phone-input input-text"
				placeholder={ __( 'Mobile number', 'woocommerce-payments' ) }
				aria-label={
					inputProps.ariaLabel ||
					__( 'Mobile phone number', 'woocommerce-payments' )
				}
				name={ inputProps.name }
				onChange={ handleChange }
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
