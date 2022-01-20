/**
 * External dependencies
 */
import ReactDOM from 'react-dom';

/**
 * External dependencies
 */
import CheckoutPageSaveUser from 'wcpay/components/platform-checkout/checkout-page-save-user';

const checkoutPageSaveUserContainer = document.getElementById(
	'checkout-page-save-user-container'
);

if ( checkoutPageSaveUserContainer ) {
	ReactDOM.render( <CheckoutPageSaveUser />, checkoutPageSaveUserContainer );
}
