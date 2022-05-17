/**
 * Internal dependencies
 */
import { getConfig } from 'utils/checkout';
import {useAccountBusinessSupportAddress} from "wcpay/data";


const enableStripeLinkPaymentMethod = ( options ) => {
console.log('testtestetes');
	const api = options.api;
// 	const upePaymentIntentData = getConfig( 'upePaymentIntentData' );
// console.log(upePaymentIntentData);
// 	if (upePaymentIntentData === null) {
// 		return;
// 	}
// 	const clientSecret = upePaymentIntentData.split( '-' )[ 2 ];
	const elements = api.getStripe().elements( options.clientSecret );




	const linkAutofill = api.getStripe().linkAutofillModal( elements );
	document.getElementById( options.emailId ).addEventListener( 'keyup', ( event ) => {
		linkAutofill.launch( { email: event.target.value } );
	} );
	console.log(document.getElementById( options.emailId ));

	// Handle StripeLink button click.
	// $( '.wcpay-stripelink-modal-trigger' ).on( 'click', ( event ) => {
	// 	event.preventDefault();
	//
	// 	// Trigger modal.
	// 	linkAutofill.launch( { email: $( '#billing_email' ).val() } );
	// } );

	linkAutofill.on( 'autofill', ( event ) => {

		const { billingAddress, shippingAddress } = event.value;

		function fillWith (options, address, nodeId, key ) {
			document.getElementById( nodeId ).value =
				address.address[ key ];
console.log(options);
			// options.setAccountBusinessSupportAddress( {
			// 	...options.accountBusinessSupportAddress,
			// 	[ key ]: address.address[ key ],
			// } );
			// setAccountBusinessSupportAddress( {
			// 	...accountBusinessSupportAddress,
			// 	[ key ]: address.address[ key ],
			// } );
		};

		if (options.complete_shipping) {
			fillWith( options, shippingAddress, options.shipping_fields.address_1, 'line1' );
			// fillWith( shippingAddress, options.shipping_fields.address_2, 'line2' );
			// fillWith( shippingAddress, options.shipping_fields.city, 'city' );
			// fillWith( shippingAddress, options.shipping_fields.state, 'state' );
			// fillWith( shippingAddress, options.shipping_fields.postal_code, 'postal_code' );
			// fillWith( shippingAddress, options.shipping_fields.country, 'country' );
		}

		if (options.complete_billing) {
			// fillWith( billingAddress, options.shipping_fields.address_1, 'line1' );
			// fillWith( billingAddress, options.shipping_fields.address_2, 'line2' );
			// fillWith( billingAddress, options.shipping_fields.city, 'city' );
			// fillWith( billingAddress, options.shipping_fields.state, 'state' );
			// fillWith( billingAddress, options.shipping_fields.postal_code, 'postal_code' );
			// fillWith( billingAddress, options.shipping_fields.country, 'country' );
		}
	} );

	return (<></>);
}

export default enableStripeLinkPaymentMethod;
