/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import showErrorCheckout from 'wcpay/checkout/utils/show-error-checkout';

export const shouldSavePaymentPaymentMethod = () => {
	return (
		document.querySelector( '#wc-woocommerce_payments-new-payment-method' )
			?.checked ?? false
	);
};

const cleanupURL = () => {
	// Cleanup the URL.
	// https://stackoverflow.com/a/5298684
	history.replaceState(
		'',
		document.title,
		window.location.pathname + window.location.search
	);
};

export const showAuthenticationModalIfRequired = ( api ) => {
	const paymentMethodId = document.querySelector( '#wcpay-payment-method' )
		?.value;

	const confirmationRequest = api.confirmIntent(
		window.location.href,
		shouldSavePaymentPaymentMethod() ? paymentMethodId : null
	);

	// Boolean `true` means that there is nothing to confirm.
	if ( confirmationRequest === true ) {
		return Promise.resolve();
	}

	cleanupURL();

	return confirmationRequest
		.then( ( redirectUrl ) => {
			window.location = redirectUrl;
		} )
		.catch( ( error ) => {
			let errorMessage = error.message;

			// If this is a generic error, we probably don't want to display the error message to the user,
			// so display a generic message instead.
			if ( error instanceof Error ) {
				errorMessage = getConfig( 'genericErrorMessage' );
			}

			showErrorCheckout( errorMessage );
		} );
};
