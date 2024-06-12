/**
 * Internal dependencies
 */
import { renderBNPLCartMessaging } from './product-details';
import { getUPEConfig } from 'wcpay/utils/checkout';

const { registerPlugin } = window.wp.plugins;

const paymentMethods = getUPEConfig( 'paymentMethodsConfig' );

const BNPL_PAYMENT_METHODS = {
	AFFIRM: 'affirm',
	AFTERPAY: 'afterpay_clearpay',
	KLARNA: 'klarna',
};

const bnplPaymentMethods = Object.values( BNPL_PAYMENT_METHODS ).filter(
	( method ) => method in paymentMethods
);

if ( bnplPaymentMethods.length ) {
	// Register BNPL site messaging on the cart block.
	registerPlugin( 'bnpl-site-messaging', {
		render: renderBNPLCartMessaging,
		scope: 'woocommerce-checkout',
	} );
}
