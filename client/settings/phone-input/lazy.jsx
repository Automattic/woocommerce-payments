/** @format */

/**
 * External dependencies
 */
import React, { lazy, Suspense } from 'react';
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

const LazyPhoneNumberInput = ( props ) => (
	<Suspense fallback={ <PhoneInputPlaceholder { ...props } /> }>
		<PhoneNumberInput { ...props } />
	</Suspense>
);

export default LazyPhoneNumberInput;
