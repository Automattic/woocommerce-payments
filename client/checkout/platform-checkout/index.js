/**
 * External dependencies
 */
import ReactDOM from 'react-dom';

/**
 * External dependencies
 */
import CheckoutPageSaveUser from 'wcpay/components/platform-checkout/save-user/checkout-page-save-user';

window.addEventListener( 'load', () => {
	const placeOrderButton = document.getElementsByClassName(
		'form-row place-order'
	)?.[ 0 ];
	const buttonParent = placeOrderButton?.parentNode;
	const checkoutPageSaveUserContainer = document.createElement( 'div' );

	if ( placeOrderButton && buttonParent ) {
		buttonParent.insertBefore(
			checkoutPageSaveUserContainer,
			placeOrderButton
		);

		ReactDOM.render(
			<CheckoutPageSaveUser />,
			checkoutPageSaveUserContainer
		);
	}
} );
