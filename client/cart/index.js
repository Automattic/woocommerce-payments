/**
 * Internal dependencies
 */
import { recordUserEvent } from 'tracks';
import { getConfig } from 'wcpay/utils/checkout';
import WooPayDirectCheckout from 'wcpay/checkout/woopay/direct-checkout/woopay-direct-checkout';

const recordProceedToCheckoutButtonClick = () => {
	recordUserEvent( 'wcpay_proceed_to_checkout_button_click', {
		woopay_direct_checkout: Boolean(
			getConfig( 'isWooPayDirectCheckoutEnabled' )
		),
	} );
};

const addProceedToCheckoutTracking = () => {
	Object.values( WooPayDirectCheckout.redirectElements ).forEach(
		( className ) => {
			const proceedButton = document.querySelector( className );

			if ( ! proceedButton ) {
				return;
			}

			proceedButton.addEventListener(
				'click',
				recordProceedToCheckoutButtonClick
			);
		}
	);
};

/**
 * We need to register a MutationObserver in the classic checkout because if the
 * user updates something in the cart page, the entire "Cart totals" section is
 * re-rendered and the event listener is lost. For this reason, we need to register
 * the event listener again, and just for that particular "Proceed to checkout" button.
 *
 * @return {void}
 */
const registerClassicCartCollateralsObserver = () => {
	const cartCollateralsNode = document.querySelector( '.cart-collaterals' );

	if ( ! cartCollateralsNode ) {
		return;
	}

	const observer = new MutationObserver( () => {
		const proceedButton = document.querySelector(
			WooPayDirectCheckout.redirectElements.CLASSIC_CART_PROCEED_BUTTON
		);

		if ( ! proceedButton ) {
			return;
		}

		proceedButton.addEventListener(
			'click',
			recordProceedToCheckoutButtonClick
		);
	} );

	observer.observe( cartCollateralsNode, { childList: true, subtree: true } );
};

window.addEventListener( 'load', () => {
	addProceedToCheckoutTracking();
	registerClassicCartCollateralsObserver();
} );
