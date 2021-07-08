/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';

// Handled as an external dependency: see '/webpack.config.js:83'
import {
	registerPaymentMethod,
	registerExpressPaymentMethod,
	// eslint-disable-next-line import/no-unresolved
} from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_CARD } from '../constants.js';
import { getConfig } from 'utils/checkout';
import WCPayAPI from './../api';
import WCPayFields from './fields.js';
import request from './request.js';
import enqueueFraudScripts from 'fraud-scripts';
import paymentRequestPaymentMethod from '../../payment-request/blocks';
import confirmCardPayment from './confirm-card-payment.js';

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

const IntentConfirmationHandler = ( {
	stripe,
	elements,
	eventRegistration: { onCheckoutAfterProcessingWithSuccess },
	emitResponse,
} ) => {
	// Once the server has completed payment processing, confirm the intent of necessary.
	useEffect(
		() =>
			onCheckoutAfterProcessingWithSuccess(
				( { processingResponse: { paymentDetails } } ) =>
					confirmCardPayment( api, paymentDetails, emitResponse )
			),
		// not sure if we need to disable this, but kept it as-is to ensure nothing breaks. Please consider passing all the deps.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ elements, stripe, api ]
	);
	return <></>;
};

registerPaymentMethod( {
	name: PAYMENT_METHOD_NAME_CARD,
	content: <WCPayFields api={ api } />,
	edit: <WCPayFields api={ api } />,
	savedTokenComponent: <IntentConfirmationHandler api={ api } />,
	canMakePayment: () => !! api.getStripe(),
	paymentMethodId: PAYMENT_METHOD_NAME_CARD,
	label: __( 'Credit Card', 'woocommerce-payments' ),
	ariaLabel: __( 'Credit Card', 'woocommerce-payments' ),
	supports: {
		showSavedCards: getConfig( 'isSavedCardsEnabled' ) ?? false,
		showSaveOption: getConfig( 'isSavedCardsEnabled' ) ?? false,
		features: getConfig( 'features' ),
	},
} );

registerExpressPaymentMethod( paymentRequestPaymentMethod( api ) );

window.addEventListener( 'load', () => {
	enqueueFraudScripts( getConfig( 'fraudServices' ) );
} );
