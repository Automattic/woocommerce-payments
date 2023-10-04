/* global jQuery */

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT } from '../../constants';
import { WoopayExpressCheckoutButton } from './woopay-express-checkout-button';
import { getConfig } from '../../../utils/checkout';
import WCPayAPI from '../../api';
import request from '../../utils/request';
import ReactDOM from 'react-dom';

const Container = () => {
	return <span id="woopay-express-checkout-container" />;
};

const wooPayExpressCheckoutPaymentMethod = () => ( {
	name: PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT,
	content: <Container />,
	edit: (
		<WoopayExpressCheckoutButton
			buttonSettings={ getConfig( 'woopayButton' ) }
			isPreview={ true }
			emailSelector="#email"
		/>
	),
	canMakePayment: () => typeof wcpayConfig !== 'undefined',
	paymentMethodId: PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT,
	supports: {
		features: getConfig( 'features' ),
	},
} );

const oldWoopayContainers = [];

const renderWooPayExpressCheckoutButton = ( listenForCartChanges = {} ) => {
	// Create an API object, which will be used throughout the checkout.
	const api = new WCPayAPI(
		{
			publishableKey: getConfig( 'publishableKey' ),
			accountId: getConfig( 'accountId' ),
			forceNetworkSavedCards: getConfig( 'forceNetworkSavedCards' ),
			locale: getConfig( 'locale' ),
		},
		request
	);

	const woopayContainer = document.getElementById(
		'woopay-express-checkout-container'
	);

	if ( woopayContainer ) {
		while ( oldWoopayContainers.length > 0 ) {
			// Ensure previous buttons are unmounted and cleaned up.
			const oldWoopayContainer = oldWoopayContainers.pop();
			ReactDOM.unmountComponentAtNode( oldWoopayContainer );
		}

		oldWoopayContainers.push( woopayContainer );

		ReactDOM.render(
			<WoopayExpressCheckoutButton
				listenForCartChanges={ listenForCartChanges }
				buttonSettings={ getConfig( 'woopayButton' ) }
				api={ api }
				isProductPage={
					!! woopayContainer.getAttribute( 'data-product_page' )
				}
				emailSelector="#billing_email"
			/>,
			woopayContainer
		);
	}
};

let listenForCartChanges = null;
const renderWooPayExpressCheckoutButtonWithCallbacks = () => {
	renderWooPayExpressCheckoutButton( listenForCartChanges );
};

jQuery( ( $ ) => {
	listenForCartChanges = {
		start: () => {
			$( document.body ).on(
				'updated_cart_totals',
				renderWooPayExpressCheckoutButtonWithCallbacks
			);
		},
		stop: () => {
			$( document.body ).off(
				'updated_cart_totals',
				renderWooPayExpressCheckoutButtonWithCallbacks
			);
		},
	};

	listenForCartChanges.start();
} );

window.addEventListener(
	'load',
	renderWooPayExpressCheckoutButtonWithCallbacks
);

export default wooPayExpressCheckoutPaymentMethod;
