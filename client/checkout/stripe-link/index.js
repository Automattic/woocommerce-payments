/**
 * Internal dependencies
 */
import { dispatchChangeEventFor } from '../utils/upe';

export const switchToNewPaymentTokenElement = () => {
	const newPaymentTokenElement = document.getElementById(
		'wc-woocommerce_payments-payment-token-new'
	);
	if ( newPaymentTokenElement && ! newPaymentTokenElement.checked ) {
		newPaymentTokenElement.checked = true;
		dispatchChangeEventFor( newPaymentTokenElement );
	}
};

export const removeLinkButton = () => {
	const stripeLinkButton = document.querySelector(
		'.wcpay-stripelink-modal-trigger'
	);
	if ( stripeLinkButton ) {
		stripeLinkButton.remove();
	}
};

const transformStripeLinkAddress = ( address ) => {
	// when clicking "use another address" or "use another payment method", the returned value for shipping/billing might be `null`.
	if ( ! address ) return null;

	const [ firstName, lastName ] = address.name.split( / (.*)/s, 2 );
	return {
		first_name: firstName || '',
		last_name: lastName || '',
		address_1: address.address.line1 || '',
		address_2: address.address.line2 || '',
		city: address.address.city || '',
		country: address.address.country || '',
		postcode: address.address.postal_code || '',
		state: address.address.state || '',
		// missing fields from Stripe autofill: phone, company
	};
};

const enableStripeLinkPaymentMethod = ( options ) => {
	const emailField = document.getElementById( options.emailId );

	if ( ! emailField ) {
		return;
	}

	// https://stripe.com/docs/payments/link/autofill-modal
	const linkAutofill = options.api
		.getStripe()
		.linkAutofillModal( options.elements );

	emailField.addEventListener( 'keyup', ( event ) => {
		linkAutofill.launch( { email: event.target.value } );
	} );

	options.onButtonShow( linkAutofill );

	linkAutofill.on( 'autofill', ( event ) => {
		const { billingAddress, shippingAddress } = event.value;
		options.onAutofill(
			transformStripeLinkAddress( billingAddress ),
			transformStripeLinkAddress( shippingAddress )
		);
		switchToNewPaymentTokenElement();
	} );
};

export default enableStripeLinkPaymentMethod;
