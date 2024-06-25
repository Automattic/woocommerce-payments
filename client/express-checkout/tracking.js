/**
 * External dependencies
 */
import { debounce } from 'lodash';
import { recordUserEvent } from 'tracks';

let expressCheckoutBranding;

// Track the button click event.
export const trackExpressCheckoutButtonClick = ( source ) => {
	const expressPaymentTypeEvents = {
		google_pay: 'gpay_button_click',
		apple_pay: 'applepay_button_click',
	};

	const event = expressPaymentTypeEvents[ expressCheckoutBranding ];
	if ( ! event ) return;

	recordUserEvent( event, { source } );
};

// Track the button load event.
export const trackExpressCheckoutButtonLoad = debounce( ( source ) => {
	const expressPaymentTypeEvents = {
		google_pay: 'gpay_button_load',
		apple_pay: 'applepay_button_load',
	};

	const event = expressPaymentTypeEvents[ expressCheckoutBranding ];
	if ( ! event ) return;

	recordUserEvent( event, { source } );
}, 1000 );

export const setExpressCheckoutBranding = ( branding ) =>
	( expressCheckoutBranding = branding );
