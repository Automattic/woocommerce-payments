/**
 * External dependencies
 */
import { debounce } from 'lodash';
import { recordUserEvent } from 'tracks';

/**
 * Check if shopper tracking is enabled via wcpaySettings.
 * This respects server-side filters and opt-out settings.
 *
 * @return {boolean} True if tracking is enabled.
 */
const isTrackingEnabled = () => {
	// If wcpaySettings is not available, assume tracking is enabled (backward compatibility).
	if ( typeof window.wcpaySettings === 'undefined' ) {
		return true;
	}

	// Check if tracking has been explicitly disabled.
	// This would be set by the server based on wcpay_enable_shopper_tracking filter
	// and woocommerce_allow_tracking option.
	return window.wcpaySettings.enableShopperTracking !== false;
};

// Track the button click event.
export const trackExpressCheckoutButtonClick = ( paymentMethod, source ) => {
	if ( ! isTrackingEnabled() ) {
		return;
	}

	const expressPaymentTypeEvents = {
		google_pay: 'gpay_button_click',
		apple_pay: 'applepay_button_click',
	};

	const event = expressPaymentTypeEvents[ paymentMethod ];
	if ( ! event ) return;

	recordUserEvent( event, { source } );
};

const debouncedTrackApplePayButtonLoad = debounce( ( { source } ) => {
	if ( isTrackingEnabled() ) {
		recordUserEvent( 'applepay_button_load', { source } );
	}
}, 1000 );

const debouncedTrackGooglePayButtonLoad = debounce( ( { source } ) => {
	if ( isTrackingEnabled() ) {
		recordUserEvent( 'gpay_button_load', { source } );
	}
}, 1000 );

// Track the button load event.
export const trackExpressCheckoutButtonLoad = ( {
	paymentMethods,
	source,
} ) => {
	if ( ! isTrackingEnabled() ) {
		return;
	}

	const expressPaymentTypeEvents = {
		googlePay: debouncedTrackGooglePayButtonLoad,
		applePay: debouncedTrackApplePayButtonLoad,
	};

	for ( const paymentMethod of paymentMethods ) {
		const debouncedTrackFunction =
			expressPaymentTypeEvents[ paymentMethod ];
		if ( ! debouncedTrackFunction ) continue;

		debouncedTrackFunction( { source } );
	}
};
