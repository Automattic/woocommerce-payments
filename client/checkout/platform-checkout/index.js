/* global jQuery */
/**
 * External dependencies
 */
import ReactDOM from 'react-dom';

/**
 * External dependencies
 */
import CheckoutPageSaveUser from 'wcpay/components/platform-checkout/save-user/checkout-page-save-user';

const renderSaveUserSection = () => {
	const saveUserSection = document.getElementsByClassName(
		'platform-checkout-save-new-user-container'
	)?.[ 0 ];

	if ( saveUserSection ) {
		return;
	}

	const blocksCheckout = document.getElementsByClassName(
		'wc-block-checkout'
	);

	if ( blocksCheckout.length ) {
		const checkoutPageSaveUserContainer = document.createElement(
			'fieldset'
		);
		checkoutPageSaveUserContainer.className =
			'wc-block-checkout__payment-method wp-block-woocommerce-checkout-remember-block ' +
			'wc-block-components-checkout-step wc-block-components-checkout-step--with-step-number';
		checkoutPageSaveUserContainer.id = 'remember-me';

		const paymentOptions = document.getElementsByClassName(
			'wp-block-woocommerce-checkout-payment-block'
		)?.[ 0 ];

		if ( paymentOptions ) {
			// Render right after the payment options block, as a sibling element.
			paymentOptions.parentNode.insertBefore(
				checkoutPageSaveUserContainer,
				paymentOptions.nextSibling
			);

			ReactDOM.render(
				<CheckoutPageSaveUser isBlocksCheckout={ true } />,
				checkoutPageSaveUserContainer
			);
		}
	} else {
		const checkoutPageSaveUserContainer = document.createElement( 'div' );
		checkoutPageSaveUserContainer.className =
			'platform-checkout-save-new-user-container';

		const placeOrderButton = document.getElementsByClassName(
			'form-row place-order'
		)?.[ 0 ];
		const buttonParent = placeOrderButton?.parentNode;

		if ( placeOrderButton && buttonParent ) {
			buttonParent.insertBefore(
				checkoutPageSaveUserContainer,
				placeOrderButton
			);

			ReactDOM.render(
				<CheckoutPageSaveUser isBlocksCheckout={ false } />,
				checkoutPageSaveUserContainer
			);
		}
	}
};

window.addEventListener( 'load', () => {
	renderSaveUserSection();
} );

// mount component again if parent fragment if re-rendered after ajax request by woocommerce core
// https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/legacy/js/frontend/checkout.js#L372
jQuery( function ( $ ) {
	$( document ).ajaxComplete( function () {
		renderSaveUserSection();
	} );
} );
