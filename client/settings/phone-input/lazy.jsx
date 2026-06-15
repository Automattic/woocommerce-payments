/** @format */

/**
 * External dependencies
 */
import React, { lazy, Suspense } from 'react';
import { __ } from '@wordpress/i18n';

const PhoneNumberInput = lazy( () =>
	import( /* webpackChunkName: "wcpay-phone-input" */ './' )
);

// Mirrors the real input's markup so swapping in the loaded component causes no layout shift.
const PhoneInputPlaceholder = ( { id, isBlocksCheckout } ) => (
	<div className={ isBlocksCheckout ? 'wc-block-components-text-input' : '' }>
		<input
			type="tel"
			id={ id }
			className="phone-input input-text"
			placeholder={ __( 'Mobile number', 'woocommerce-payments' ) }
			disabled
		/>
	</div>
);

const LazyPhoneNumberInput = ( props ) => (
	<Suspense fallback={ <PhoneInputPlaceholder { ...props } /> }>
		<PhoneNumberInput { ...props } />
	</Suspense>
);

export default LazyPhoneNumberInput;
