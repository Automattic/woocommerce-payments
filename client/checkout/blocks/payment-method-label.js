/**
 * Internal dependencies
 */
import {
	Elements,
	PaymentMethodMessagingElement,
} from '@stripe/react-stripe-js';
import { PaymentMethodsLogos } from './payment-methods-logos';
import Visa from 'assets/images/payment-method-icons/visa.svg?asset';
import Mastercard from 'assets/images/payment-method-icons/mastercard.svg?asset';
import Amex from 'assets/images/payment-method-icons/amex.svg?asset';
import Discover from 'assets/images/payment-method-icons/discover.svg?asset';
import { normalizeCurrencyToMinorUnit } from '../utils';
import { useStripeForUPE } from 'wcpay/hooks/use-stripe-async';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { __ } from '@wordpress/i18n';
import './style.scss';
import { useEffect, useState, useRef } from '@wordpress/element';
import { getAppearance, getFontRulesFromPage } from 'wcpay/checkout/upe-styles';

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

const bnplMethods = [ 'affirm', 'afterpay_clearpay', 'klarna' ];
const PaymentMethodMessageWrapper = ( {
	upeName,
	countries,
	currentCountry,
	amount,
	appearance,
	children,
} ) => {
	if ( ! bnplMethods.includes( upeName ) ) {
		return null;
	}

	if ( amount <= 0 ) {
		return null;
	}

	if ( ! currentCountry ) {
		return null;
	}

	if ( ! appearance ) {
		return null;
	}

	if ( countries.length !== 0 && ! countries.includes( currentCountry ) ) {
		return null;
	}

	return (
		<div className="payment-method-label__pmme-container">{ children }</div>
	);
};

export default ( { api, title, countries, iconLight, iconDark, upeName } ) => {
	const containerRef = useRef( null );
	const cartData = wp.data.select( 'wc/store/cart' ).getCartData();
	const isTestMode = getUPEConfig( 'testMode' );
	const [ appearance, setAppearance ] = useState(
		getUPEConfig( 'wcBlocksUPEAppearance' )
	);

	const [ upeAppearanceTheme, setUpeAppearanceTheme ] = useState(
		getUPEConfig( 'wcBlocksUPEAppearanceTheme' )
	);

	const [ fontRules, setFontRules ] = useState( [] );

	// Stripe expects the amount to be sent as the minor unit of 2 digits.
	const amount = parseInt(
		normalizeCurrencyToMinorUnit(
			cartData.totals.total_price,
			cartData.totals.currency_minor_unit
		),
		10
	);

	// Customer's country or base country of the store.
	const currentCountry =
		cartData.billingAddress.country ||
		window.wcBlocksCheckoutData?.storeCountry ||
		'US';

	useEffect( () => {
		async function generateUPEAppearance() {
			if ( ! containerRef.current ) {
				return;
			}
			setFontRules(
				getFontRulesFromPage( containerRef.current.ownerDocument )
			);
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
		<>
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
							upeAppearanceTheme === 'night'
								? iconDark
								: iconLight
						}
						alt={ title }
					/>
				) }
			</div>
			<PaymentMethodMessageWrapper
				upeName={ upeName }
				countries={ countries }
				amount={ amount }
				currentCountry={ currentCountry }
				appearance={ appearance }
			>
				<Elements
					stripe={ stripe }
					options={ {
						appearance: appearance,
						fonts: fontRules,
					} }
				>
					<PaymentMethodMessagingElement
						options={ {
							amount: amount || 0,
							currency: cartData.totals.currency_code || 'USD',
							paymentMethodTypes: [ upeName ],
							countryCode: currentCountry,
							displayType: 'promotional_text',
						} }
					/>
				</Elements>
			</PaymentMethodMessageWrapper>
		</>
	);
};
