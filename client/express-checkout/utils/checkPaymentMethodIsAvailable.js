/**
 * External dependencies
 */
import ReactDOM from 'react-dom';
import { ExpressCheckoutElement, Elements } from '@stripe/react-stripe-js';
import { memoize } from 'lodash';

/**
 * Internal dependencies
 */
import { isLinkEnabled } from 'wcpay/checkout/utils/upe';
import request from 'wcpay/checkout/utils/request';
import WCPayAPI from 'wcpay/checkout/api';
import { getUPEConfig } from 'wcpay/utils/checkout';

export const checkPaymentMethodIsAvailable = memoize(
	( paymentMethod, cart, resolve ) => {
		const root = ReactDOM.createRoot(
			document.getElementById(
				`express-checkout-check-availability-container-${ paymentMethod }`
			)
		);

		const api = new WCPayAPI(
			{
				publishableKey: getUPEConfig( 'publishableKey' ),
				accountId: getUPEConfig( 'accountId' ),
				forceNetworkSavedCards: getUPEConfig(
					'forceNetworkSavedCards'
				),
				locale: getUPEConfig( 'locale' ),
				isStripeLinkEnabled: isLinkEnabled(
					getUPEConfig( 'paymentMethodsConfig' )
				),
			},
			request
		);

		root.render(
			<Elements
				stripe={ api.loadStripe() }
				options={ {
					mode: 'payment',
					paymentMethodCreation: 'manual',
					amount: Number( cart.cartTotals.total_price ),
					currency: cart.cartTotals.currency_code.toLowerCase(),
				} }
			>
				<ExpressCheckoutElement
					onLoadError={ () => resolve( false ) }
					options={ {
						paymentMethods: {
							amazonPay: 'never',
							applePay:
								paymentMethod === 'applePay'
									? 'always'
									: 'never',
							googlePay:
								paymentMethod === 'googlePay'
									? 'always'
									: 'never',
							link: 'never',
							paypal: 'never',
						},
					} }
					onReady={ ( event ) => {
						let canMakePayment = false;
						if ( event.availablePaymentMethods ) {
							canMakePayment =
								event.availablePaymentMethods[ paymentMethod ];
						}
						resolve( canMakePayment );
						root.unmount();
					} }
				/>
			</Elements>
		);
	}
);
