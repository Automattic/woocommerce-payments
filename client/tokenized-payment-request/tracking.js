/**
 * External dependencies
 */
import { debounce } from 'lodash';
import { recordUserEvent } from 'tracks';

let paymentRequestBranding;

// Track the payment request button click event.
export const trackPaymentRequestButtonClick = ( source ) => {
	const paymentRequestTypeEvents = {
		google_pay: 'gpay_button_click',
		apple_pay: 'applepay_button_click',
	};

	const event = paymentRequestTypeEvents[ paymentRequestBranding ];
	if ( ! event ) return;

	recordUserEvent( event, { source } );
};

// Track the payment request button load event.
export const trackPaymentRequestButtonLoad = debounce( ( source ) => {
	const paymentRequestTypeEvents = {
		google_pay: 'gpay_button_load',
		apple_pay: 'applepay_button_load',
	};

	const event = paymentRequestTypeEvents[ paymentRequestBranding ];
	if ( ! event ) return;

	recordUserEvent( event, { source } );
}, 1000 );

export const setPaymentRequestBranding = ( branding ) =>
	( paymentRequestBranding = branding );
