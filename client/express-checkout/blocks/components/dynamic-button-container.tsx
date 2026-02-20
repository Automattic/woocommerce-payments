/**
 * External dependencies
 */
import React, { useCallback, useEffect, useMemo } from 'react';
import {
	Elements,
	ExpressCheckoutElement,
	useStripe,
	useElements,
} from '@stripe/react-stripe-js';
import type { StripeElementLocale } from '@stripe/stripe-js';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	getExpressCheckoutData,
	getExpressCheckoutButtonAppearance,
	getPaymentMethodsOverride,
} from '../../utils';
import { transformCartDataForDisplayItems } from '../../transformers/wc-to-stripe';
import { validateElements } from 'wcpay/checkout/utils/validate-elements';
import { WC_STORE_CART } from 'wcpay/checkout/constants';

declare global {
	interface Window {
		wcpayFraudPreventionToken?: string;
	}
}

const paymentMethodConfig = {
	applePay: {
		expressPaymentType: 'apple_pay',
		paymentMethodTypes: [ 'card' ] as string[],
		gatewayId: 'woocommerce_payments_apple_pay',
	},
	googlePay: {
		expressPaymentType: 'google_pay',
		paymentMethodTypes: [ 'card' ] as string[],
		gatewayId: 'woocommerce_payments_google_pay',
	},
	amazonPay: {
		expressPaymentType: 'amazon_pay',
		paymentMethodTypes: [ 'amazon_pay' ] as string[],
		gatewayId: 'woocommerce_payments_amazon_pay',
	},
};

interface DynamicButtonContainerProps {
	expressPaymentMethod: 'applePay' | 'googlePay' | 'amazonPay';
	api: any;
	validate: () => Promise< { hasError: boolean } >;
	onSubmit: () => void;
	billing: {
		cartTotal: { value: number };
		cartTotalItems: any[];
		currency: { code: string; minorUnit: number };
	};
	shippingData: { needsShipping: boolean };
	eventRegistration: {
		onPaymentSetup: ( callback: () => Promise< any > ) => () => void;
	};
	emitResponse: {
		// eslint-disable-next-line @typescript-eslint/naming-convention
		responseTypes: { SUCCESS: string; ERROR: string; FAIL: string };
	};
	isEditor?: boolean;
}

/**
 * Inner component that has access to Stripe and Elements via hooks.
 * Renders the ExpressCheckoutElement and handles payment setup.
 */
const DynamicButton = ( {
	expressPaymentMethod,
	validate,
	onSubmit,
	eventRegistration: { onPaymentSetup },
	emitResponse: { responseTypes },
}: Omit< DynamicButtonContainerProps, 'api' | 'isEditor' > ) => {
	const stripe = useStripe();
	const elements = useElements();

	const config = paymentMethodConfig[ expressPaymentMethod ];
	const { expressPaymentType, paymentMethodTypes, gatewayId } = config;

	const useConfirmationTokens =
		expressPaymentMethod === 'amazonPay' ||
		( getExpressCheckoutData( 'flags' )?.isEceUsingConfirmationTokens ??
			true );

	const handleClick = useCallback(
		async ( event: any ) => {
			const { hasError } = await validate();
			if ( hasError ) {
				return;
			}

			const cartData = ( select( WC_STORE_CART ) as any )?.getCartData();
			const lineItems = transformCartDataForDisplayItems( cartData );

			event.resolve( {
				business: {
					name: ( getExpressCheckoutData as any )( 'store_name' ),
				},
				lineItems,
				emailRequired: true,
				phoneNumberRequired:
					getExpressCheckoutData( 'checkout' )?.needs_payer_phone ??
					false,
			} );
		},
		[ validate ]
	);

	const handleConfirm = useCallback( () => {
		onSubmit();
	}, [ onSubmit ] );

	useEffect( () => {
		const unsubscribe = onPaymentSetup( async () => {
			try {
				await validateElements( elements! );
			} catch ( e ) {
				return {
					type: responseTypes.ERROR,
					message: ( e as Error ).message,
				};
			}

			if ( useConfirmationTokens ) {
				const {
					confirmationToken,
					error,
				} = await stripe!.createConfirmationToken( {
					elements: elements!,
				} );

				if ( error ) {
					return {
						type: responseTypes.SUCCESS,
						meta: {
							paymentMethodData: {
								payment_method: gatewayId,
								'wcpay-payment-method': 'error',
							},
						},
					};
				}

				return {
					type: responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							payment_method: gatewayId,
							'wcpay-confirmation-token': confirmationToken!.id,
							express_payment_type: expressPaymentType,
							'wcpay-express-payment-method-types': JSON.stringify(
								paymentMethodTypes
							),
							'wcpay-fraud-prevention-token':
								window.wcpayFraudPreventionToken ?? '',
						},
					},
				};
			}

			const {
				paymentMethod,
				error,
			} = await stripe!.createPaymentMethod( { elements: elements! } );

			if ( error ) {
				return {
					type: responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							payment_method: gatewayId,
							'wcpay-payment-method': 'error',
						},
					},
				};
			}

			return {
				type: responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						payment_method: gatewayId,
						'wcpay-payment-method': paymentMethod!.id,
						express_payment_type: expressPaymentType,
						'wcpay-express-payment-method-types': JSON.stringify(
							paymentMethodTypes
						),
						'wcpay-fraud-prevention-token':
							window.wcpayFraudPreventionToken ?? '',
					},
				},
			};
		} );

		return unsubscribe;
	}, [
		stripe,
		elements,
		onPaymentSetup,
		useConfirmationTokens,
		responseTypes,
		gatewayId,
		expressPaymentType,
		paymentMethodTypes,
	] );

	const expressCheckoutOptions = {
		...getPaymentMethodsOverride( expressPaymentMethod ),
	};

	return (
		<ExpressCheckoutElement
			options={ expressCheckoutOptions }
			onClick={ handleClick }
			onConfirm={ handleConfirm }
		/>
	);
};

/**
 * Outer component that sets up the Stripe Elements provider
 * and renders the DynamicButton inside it.
 */
const DynamicButtonContainer = ( props: DynamicButtonContainerProps ) => {
	const { api, billing, expressPaymentMethod, isEditor } = props;

	const config = paymentMethodConfig[ expressPaymentMethod ];

	const useConfirmationTokens =
		expressPaymentMethod === 'amazonPay' ||
		( getExpressCheckoutData( 'flags' )?.isEceUsingConfirmationTokens ??
			true );

	const stripePromise = useMemo( () => {
		return api.loadStripeForExpressCheckout();
	}, [ api ] );

	if ( isEditor ) {
		return null;
	}

	const elementsOptions = {
		mode: 'payment' as const,
		amount: billing.cartTotal.value || 1,
		currency: billing.currency.code.toLowerCase(),
		...( useConfirmationTokens
			? { paymentMethodTypes: config.paymentMethodTypes }
			: { paymentMethodCreation: 'manual' as const } ),
		appearance: getExpressCheckoutButtonAppearance( undefined ),
		locale: ( getExpressCheckoutData( 'stripe' )?.locale ??
			'en' ) as StripeElementLocale,
	};

	return (
		<Elements stripe={ stripePromise } options={ elementsOptions }>
			<DynamicButton { ...props } />
		</Elements>
	);
};

export default DynamicButtonContainer;
