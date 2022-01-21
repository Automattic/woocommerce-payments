/**
 * External dependencies
 */
import ReactDOM from 'react-dom';

/**
 * External dependencies
 */
import CheckoutPageSaveUser from 'wcpay/components/platform-checkout/save-user/checkout-page-save-user';
import OrderSuccessPageSaveUser from 'wcpay/components/platform-checkout/save-user/order-success-page-save-user';

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

const OrderSuccessPageSaveUserContainer = document.getElementById(
	'order-page-save-user'
);

if ( OrderSuccessPageSaveUserContainer ) {
	ReactDOM.render(
		<OrderSuccessPageSaveUser />,
		OrderSuccessPageSaveUserContainer
	);
}
