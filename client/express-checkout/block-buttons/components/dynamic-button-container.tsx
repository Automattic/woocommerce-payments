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
	getStripeElementsMode,
	shouldUseConfirmationTokens,
	createPaymentCredential,
	buildStripeElementsOptions,
} from '../../utils';
import type { AvailablePaymentMethods } from '@stripe/stripe-js';
import { transformCartDataForDisplayItems } from '../../transformers/wc-to-stripe';
import { validateElements } from 'wcpay/checkout/utils/validate-elements';
import { WC_STORE_CART } from 'wcpay/checkout/constants';
import type WCPayAPI from 'wcpay/checkout/api';

declare global {
	interface Window {
		wcpayFraudPreventionToken?: string;
	}
}

interface CartTotalItem {
	key: string;
	label: string;
	value: number;
	valueWithTax: number;
}

interface DynamicButtonContainerProps {
	expressPaymentMethod: keyof AvailablePaymentMethods;
	expressPaymentType: string;
	stripePaymentMethodType: string;
	gatewayId: string;
	api: WCPayAPI;
	validate: () => Promise< { hasError: boolean } >;
	onSubmit: () => void;
	billing: {
		cartTotal: { value: number };
		cartTotalItems: CartTotalItem[];
		currency: { code: string; minorUnit: number };
	};
	shippingData: { needsShipping: boolean };
	eventRegistration: {
		onPaymentSetup: ( callback: () => Promise< unknown > ) => () => void;
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
	expressPaymentType,
	stripePaymentMethodType,
	gatewayId,
	validate,
	onSubmit,
	eventRegistration: { onPaymentSetup },
	emitResponse: { responseTypes },
}: Omit< DynamicButtonContainerProps, 'api' | 'isEditor' > ) => {
	const stripe = useStripe();
	const elements = useElements();

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
						'wcpay-express-payment-method-types': JSON.stringify( [
							stripePaymentMethodType,
						] ),
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
		stripePaymentMethodType,
	] );

	const expressCheckoutOptions = useMemo(
		() => ( {
			...getPaymentMethodsOverride( expressPaymentMethod ),
		} ),
		[ expressPaymentMethod ]
	);

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
	const { api, billing, stripePaymentMethodType, isEditor } = props;

	const useConfirmationTokens = shouldUseConfirmationTokens();

	const stripePromise = useMemo( () => {
		return api.loadStripeForExpressCheckout();
	}, [ api ] );

	const paymentMethodTypes = useMemo( () => [ stripePaymentMethodType ], [
		stripePaymentMethodType,
	] );

	const elementsOptions = useMemo(
		() =>
			buildStripeElementsOptions( {
				amount: billing.cartTotal.value,
				currency: billing.currency.code,
				useConfirmationTokens,
				paymentMethodTypes,
				mode: getStripeElementsMode(),
			} ),
		[
			billing.cartTotal.value,
			billing.currency.code,
			useConfirmationTokens,
			paymentMethodTypes,
		]
	);

	if ( isEditor ) {
		return null;
	}

	return (
		<Elements stripe={ stripePromise } options={ elementsOptions }>
			<DynamicButton { ...props } />
		</Elements>
	);
};

export default DynamicButtonContainer;
