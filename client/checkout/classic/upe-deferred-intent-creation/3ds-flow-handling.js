/**
 * Internal dependencies
 */
import WCPayAPI from 'wcpay/checkout/api';
import { isWCPayChosen } from 'wcpay/checkout/utils/upe';
import { getUPEConfig, getConfig } from 'wcpay/utils/checkout';
import apiRequest from '../../utils/request';
import showErrorCheckout from 'wcpay/checkout/utils/show-error-checkout';

const api = new WCPayAPI(
	{
		publishableKey: getUPEConfig( 'publishableKey' ),
		accountId: getUPEConfig( 'accountId' ),
		forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
		locale: getUPEConfig( 'locale' ),
	},
	apiRequest
);

const getPaymentMethodId = () => {
	// console.log('3');
	return isWCPayChosen()
		? document.querySelector( '#wcpay-payment-method-sepa' )?.value ?? ''
		: document.querySelector( '#wcpay-payment-method' )?.value ?? '';
};

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

export const showAuthenticationModalIfRequired = () => {
	const url = window.location.href;
	const paymentMethodId = getPaymentMethodId();

	const confirmation = api.confirmIntent(
		url,
		shouldSavePaymentPaymentMethod() ? paymentMethodId : null
	);

	// Boolean `true` means that there is nothing to confirm.
	if ( true === confirmation ) {
		return;
	}

	const { request } = confirmation;
	cleanupURL();

	request
		.then( ( redirectUrl ) => {
			window.location = redirectUrl;
		} )
		.catch( ( error ) => {
			document
				.querySelector( 'form.checkout' )
				.classList.remove( 'processing' );

			const elements = document.getElementsByClassName( 'blockUI' );
			Array.from( elements ).forEach( ( element ) => {
				element.parentNode.removeChild( element );
			} );

			let errorMessage = error.message;

			// If this is a generic error, we probably don't want to display the error message to the user,
			// so display a generic message instead.
			if ( error instanceof Error ) {
				errorMessage = getConfig( 'genericErrorMessage' );
			}

			showErrorCheckout( errorMessage );
		} );
};

window.addEventListener( 'hashchange', () => {
	if ( window.location.hash.startsWith( '#wcpay-confirm-' ) ) {
		showAuthenticationModalIfRequired();
	}
} );
