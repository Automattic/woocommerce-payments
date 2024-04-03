/**
 * Internal dependencies
 */
import {
	Elements,
	PaymentMethodMessagingElement,
} from '@stripe/react-stripe-js';

export default ( {
	api,
	upeConfig,
	upeName,
	stripeAppearance,
	upeAppearanceTheme,
} ) => {
	return (
		<>
			<span>
				{ upeConfig.title }
				{ upeName !== 'card' && (
					<>
						<Elements
							stripe={ api.getStripeForUPE( upeName ) }
							options={ {
								appearance: stripeAppearance ?? {},
							} }
						>
							<PaymentMethodMessagingElement
								options={ {
									amount:
										parseInt(
											wp.data
												.select( 'wc/store/cart' )
												.getCartData().totals
												.total_price,
											10
										) || 0,
									currency:
										wp.data
											.select( 'wc/store/cart' )
											.getCartData().totals
											.currency_code || 'USD',
									paymentMethodTypes: [ upeName ],
									countryCode:
										wp.data
											.select( 'wc/store/cart' )
											.getCartData().billingAddress
											.country ||
										window.wcBlocksCheckoutData
											.storeCountry, // Customer's country or base country of the store.
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
