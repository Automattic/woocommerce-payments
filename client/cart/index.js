/**
 * Internal dependencies
 */
import { recordUserEvent } from 'tracks';
import { getConfig } from 'wcpay/utils/checkout';
import WooPayDirectCheckout from 'wcpay/checkout/woopay/direct-checkout/woopay-direct-checkout';

const addProceedToCheckoutTracking = () => {
	Object.values( WooPayDirectCheckout.redirectElements ).forEach(
		( className ) => {
			const proceedButton = document.querySelector( className );

			if ( ! proceedButton ) {
				return;
			}

			const isWooPayEnabled = WooPayDirectCheckout.isWooPayEnabled();
			const isWoopayDirectCheckoutEnabled = Boolean(
				getConfig( 'isWooPayDirectCheckoutEnabled' )
			);
			proceedButton.addEventListener( 'click', () => {
				recordUserEvent( 'wcpay_proceed_to_checkout_button_click', {
					woopay_direct_checkout:
						isWooPayEnabled && isWoopayDirectCheckoutEnabled,
				} );
			} );
		}
	);
};

window.addEventListener( 'load', () => {
	addProceedToCheckoutTracking();
} );
