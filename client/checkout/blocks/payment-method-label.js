/**
 * Internal dependencies
 */
import { PaymentMethodsLogos } from './payment-methods-logos';
import Visa from 'assets/images/payment-method-icons/visa.svg?asset';
import Mastercard from 'assets/images/payment-method-icons/mastercard.svg?asset';
import Amex from 'assets/images/payment-method-icons/amex.svg?asset';
import Discover from 'assets/images/payment-method-icons/discover.svg?asset';
import { useStripeForUPE } from 'wcpay/hooks/use-stripe-async';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { __ } from '@wordpress/i18n';
import './style.scss';
import { useEffect, useState, useRef } from '@wordpress/element';
import { getAppearance } from 'wcpay/checkout/upe-styles';

const paymentMethods = [
	{
		name: 'visa',
		component: Visa,
	},
	{
		name: 'mastercard',
		component: Mastercard,
	},
	{
		name: 'amex',
		component: Amex,
	},
	{
		name: 'discover',
		component: Discover,
	},
	// TODO: Missing Diners Club
	// TODO: What other card payment methods should be here?
];
const breakpointConfigs = [
	{ breakpoint: 550, maxElements: 2 },
	{ breakpoint: 330, maxElements: 1 },
];

export default ( { api, title, iconLight, iconDark, upeName } ) => {
	const containerRef = useRef( null );
	const isTestMode = getUPEConfig( 'testMode' );
	const [ appearance, setAppearance ] = useState(
		getUPEConfig( 'wcBlocksUPEAppearance' )
	);

	const [ upeAppearanceTheme, setUpeAppearanceTheme ] = useState(
		getUPEConfig( 'wcBlocksUPEAppearanceTheme' )
	);

	useEffect( () => {
		async function generateUPEAppearance() {
			if ( ! containerRef.current ) {
				return;
			}
			// Generate UPE input styles.
			let upeAppearance = getAppearance(
				'blocks_checkout',
				false,
				containerRef.current.ownerDocument
			);
			upeAppearance = await api.saveUPEAppearance(
				upeAppearance,
				'blocks_checkout'
			);
			setAppearance( upeAppearance );
			setUpeAppearanceTheme( upeAppearance.theme );
		}

		if ( ! appearance ) {
			generateUPEAppearance();
		}
	}, [ api, appearance ] );

	const stripe = useStripeForUPE( api, upeName );

	if ( ! stripe ) {
		return null;
	}

	return (
		<div ref={ containerRef } className="payment-method-label">
			<span className="payment-method-label__label">{ title }</span>
			{ isTestMode && (
				<span className="test-mode badge">
					{ __( 'Test Mode', 'woocommerce-payments' ) }
				</span>
			) }
			{ upeName === 'card' ? (
				<PaymentMethodsLogos
					maxElements={ 4 }
					paymentMethods={ paymentMethods }
					breakpointConfigs={ breakpointConfigs }
				/>
			) : (
				<img
					className="payment-methods--logos"
					src={
						upeAppearanceTheme === 'night' ? iconDark : iconLight
					}
					alt={ title }
				/>
			) }
		</div>
	);
};
