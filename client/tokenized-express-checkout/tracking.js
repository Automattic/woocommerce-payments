/**
 * External dependencies
 */
import { debounce } from 'lodash';
import { recordUserEvent } from 'tracks';

// Track the button click event.
export const trackExpressCheckoutButtonClick = ( paymentMethod, source ) => {
	const expressPaymentTypeEvents = {
		google_pay: 'gpay_button_click',
		apple_pay: 'applepay_button_click',
	};

	const event = expressPaymentTypeEvents[ paymentMethod ];
	if ( ! event ) return;

	recordUserEvent( event, { source } );
};

const debouncedTrackApplePayButtonLoad = debounce( ( { source } ) => {
	recordUserEvent( 'applepay_button_load', { source } );
}, 1000 );

const debouncedTrackGooglePayButtonLoad = debounce( ( { source } ) => {
	recordUserEvent( 'gpay_button_load', { source } );
}, 1000 );

// Track the button load event.
export const trackExpressCheckoutButtonLoad = ( {
	paymentMethods,
	source,
} ) => {
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
