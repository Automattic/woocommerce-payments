/**
 * Internal dependencies
 */
import {
	Elements,
	PaymentMethodMessagingElement,
} from '@stripe/react-stripe-js';
import { normalizeCurrencyToMinorUnit } from '../utils';

export default ( {
	api,
	upeConfig,
	upeName,
	stripeAppearance,
	upeAppearanceTheme,
} ) => {
	const cartData = wp.data.select( 'wc/store/cart' ).getCartData();
	const bnplMethods = [ 'affirm', 'afterpay_clearpay', 'klarna' ];

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

	return (
		<>
			<span>
				{ upeConfig.title }
				{ bnplMethods.includes( upeName ) &&
					( upeConfig.countries.length === 0 ||
						upeConfig.countries.includes( currentCountry ) ) &&
					amount > 0 &&
					currentCountry && (
						<>
							<Elements
								stripe={ api.getStripeForUPE( upeName ) }
								options={ {
									appearance: stripeAppearance ?? {},
								} }
							>
								<PaymentMethodMessagingElement
									options={ {
										amount: amount || 0,
										currency:
											cartData.totals.currency_code ||
											'USD',
										paymentMethodTypes: [ upeName ],
										countryCode: currentCountry,
										displayType: 'promotional_text',
									} }
								/>
							</Elements>
						</>
					) }
				<img
					src={
						upeAppearanceTheme === 'night'
							? upeConfig.darkIcon
							: upeConfig.icon
					}
					alt={ upeConfig.title }
				/>
			</span>
		</>
	);
};
