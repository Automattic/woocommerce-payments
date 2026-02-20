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
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	getExpressCheckoutData,
	getPaymentMethodsOverride,
	shouldUseConfirmationTokens,
	createPaymentCredential,
	buildStripeElementsOptions,
} from '../../utils';
import {
	EXPRESS_PAYMENT_METHODS,
	ExpressPaymentMethodKey,
} from '../../constants';
import { transformCartDataForDisplayItems } from '../../transformers/wc-to-stripe';
import { validateElements } from 'wcpay/checkout/utils/validate-elements';
import { WC_STORE_CART } from 'wcpay/checkout/constants';

declare global {
	interface Window {
		wcpayFraudPreventionToken?: string;
	}
}

interface DynamicButtonContainerProps {
	expressPaymentMethod: ExpressPaymentMethodKey;
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

	const config = EXPRESS_PAYMENT_METHODS[ expressPaymentMethod ];
	const { expressPaymentType, paymentMethodTypes, gatewayId } = config;

	const useConfirmationTokens = shouldUseConfirmationTokens();

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

			let credential;
			try {
				credential = await createPaymentCredential(
					stripe!,
					elements!,
					useConfirmationTokens
				);
			} catch {
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

			const credentialKey =
				credential.type === 'confirmation_token'
					? 'wcpay-confirmation-token'
					: 'wcpay-payment-method';

			return {
				type: responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						payment_method: gatewayId,
						[ credentialKey ]: credential.id,
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

	const config = EXPRESS_PAYMENT_METHODS[ expressPaymentMethod ];
	const useConfirmationTokens = shouldUseConfirmationTokens();

	const stripePromise = useMemo( () => {
		return api.loadStripeForExpressCheckout();
	}, [ api ] );

	if ( isEditor ) {
		return null;
	}

	const elementsOptions = buildStripeElementsOptions( {
		amount: billing.cartTotal.value,
		currency: billing.currency.code,
		useConfirmationTokens,
		paymentMethodTypes: config.paymentMethodTypes,
	} );

	return (
		<Elements stripe={ stripePromise } options={ elementsOptions }>
			<DynamicButton { ...props } />
		</Elements>
	);
};

export default DynamicButtonContainer;
