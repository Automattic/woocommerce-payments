/**
 * External dependencies
 */
import { lazy, Suspense } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getConfig } from '../../../utils/checkout';

export const PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT =
	'woocommerce_payments_woopay_express_checkout';

// Only the button code loads on demand, keeping it out of the checkout bundle on stores without WooPay.
// The method itself must register synchronously: WC Blocks checks express methods on first render and
// again only on cart changes, so a method registered after an async import may never appear.
const LazyButtonContainer = lazy(
	() =>
		import(
			/* webpackChunkName: "woopay-express-checkout-button" */ './woopay-express-checkout-button-container'
		)
);
const LazyButtonPreview = lazy( () =>
	import(
		/* webpackChunkName: "woopay-express-checkout-button" */ './woopay-express-checkout-button'
	).then( ( { WoopayExpressCheckoutButton } ) => ( {
		default: WoopayExpressCheckoutButton,
	} ) )
);

const WooPayExpressCheckoutButtonContainer = ( props ) => (
	<Suspense fallback={ null }>
		<LazyButtonContainer { ...props } />
	</Suspense>
);

const WooPayExpressCheckoutButtonPreview = ( props ) => (
	<Suspense fallback={ null }>
		<LazyButtonPreview { ...props } />
	</Suspense>
);

const wooPayExpressCheckoutPaymentMethod = () => ( {
	name: PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT,
	title: 'WooPayments - WooPay',
	description: __(
		'A one-click, high-converting, secure checkout built for Woo — themed to your brand.',
		'woocommerce-payments'
	),
	gatewayId: 'woocommerce_payments',
	content: <WooPayExpressCheckoutButtonContainer />,
	edit: (
		<WooPayExpressCheckoutButtonPreview
			buttonSettings={ getConfig( 'woopayButton' ) }
			isPreview={ true }
			emailSelector="#email"
		/>
	),
	canMakePayment: () => typeof wcpayConfig !== 'undefined',
	paymentMethodId: PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT,
	supports: {
		features: getConfig( 'features' ),
		style: [ 'height', 'borderRadius' ],
	},
} );

export default wooPayExpressCheckoutPaymentMethod;
