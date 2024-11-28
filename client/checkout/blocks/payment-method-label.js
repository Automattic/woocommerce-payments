/**
 * Internal dependencies
 */
import {
	Elements,
	PaymentMethodMessagingElement,
} from '@stripe/react-stripe-js';
import { normalizeCurrencyToMinorUnit } from '../utils';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { __ } from '@wordpress/i18n';
import './style.scss';
import { useEffect, useState } from '@wordpress/element';
import { getAppearance } from 'wcpay/checkout/upe-styles';

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

export default ( {
	api,
	title,
	countries,
	iconLight,
	iconDark,
	upeName,
	upeAppearanceTheme,
} ) => {
	const cartData = wp.data.select( 'wc/store/cart' ).getCartData();
	const isTestMode = getUPEConfig( 'testMode' );
	const [ appearance, setAppearance ] = useState(
		getUPEConfig( 'wcBlocksUPEAppearance' )
	);

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
			// Generate UPE input styles.
			let upeAppearance = getAppearance( 'blocks_checkout', false );
			upeAppearance = await api.saveUPEAppearance(
				upeAppearance,
				'blocks_checkout'
			);
			setAppearance( upeAppearance );
		}

		if ( ! appearance ) {
			generateUPEAppearance();
		}
	}, [ api, appearance ] );

	return (
		<>
			<div className="payment-method-label">
				<span className="payment-method-label__label">{ title }</span>
				{ isTestMode && (
					<span className="test-mode badge">
						{ __( 'Test Mode', 'woocommerce-payments' ) }
					</span>
				) }
				<img
					className="payment-methods--logos"
					src={
						upeAppearanceTheme === 'night' ? iconDark : iconLight
					}
					alt={ title }
				/>
			</div>
			<PaymentMethodMessageWrapper
				upeName={ upeName }
				countries={ countries }
				amount={ amount }
				currentCountry={ currentCountry }
				appearance={ appearance }
			>
				<Elements
					stripe={ api.getStripeForUPE( upeName ) }
					options={ {
						appearance: appearance,
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
