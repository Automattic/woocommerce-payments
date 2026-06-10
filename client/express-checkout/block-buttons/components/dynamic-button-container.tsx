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
import { useSelect } from '@wordpress/data';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import {
	getExpressCheckoutData,
	getPaymentMethodsOverride,
	shouldUseConfirmationTokens,
} from '../../utils';
import type { AvailablePaymentMethods } from '@stripe/stripe-js';
import { transformPrice } from '../../transformers/wc-to-stripe';
import { WC_STORE_CART } from 'wcpay/checkout/constants';
import { ExpressPaymentSession } from 'wcpay/express-checkout/session/express-payment-session';
import { createBlocksMetaSink } from 'wcpay/express-checkout/session/sinks';
import type WCPayAPI from 'wcpay/checkout/api';

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

interface DynamicButtonProps {
	session: ExpressPaymentSession;
	expressPaymentMethod: keyof AvailablePaymentMethods;
	gatewayId: string;
	validate: () => Promise< { hasError: boolean } >;
	onSubmit: () => void;
	eventRegistration: DynamicButtonContainerProps[ 'eventRegistration' ];
	emitResponse: DynamicButtonContainerProps[ 'emitResponse' ];
}

/**
 * Inner component that has access to Stripe and Elements via hooks.
 * Renders the ExpressCheckoutElement and handles payment setup.
 */
const DynamicButton = ( {
	session,
	expressPaymentMethod,
	gatewayId,
	validate,
	onSubmit,
	eventRegistration: { onPaymentSetup },
	emitResponse: { responseTypes },
}: DynamicButtonProps ) => {
	const stripe = useStripe();
	const elements = useElements();

	const handleClick = useCallback(
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		async ( event: any ) => {
			const { hasError } = await validate();
			if ( hasError ) {
				return;
			}

			event.resolve( session.buildClickResolution() );
		},
		[ validate, session ]
	);

	const handleConfirm = useCallback( () => {
		onSubmit();
	}, [ onSubmit ] );

	useEffect( () => {
		const sink = createBlocksMetaSink( { gatewayId, responseTypes } );

		const unsubscribe = onPaymentSetup( async () => {
			try {
				const result = await session.confirm( stripe!, elements! );
				return sink.success( result );
			} catch ( e ) {
				return sink.error( ( e as Error ).message );
			}
		} );

		return unsubscribe;
	}, [
		session,
		stripe,
		elements,
		onPaymentSetup,
		responseTypes,
		gatewayId,
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
	const {
		api,
		billing,
		expressPaymentMethod,
		expressPaymentType,
		stripePaymentMethodType,
		gatewayId,
		isEditor,
	} = props;

	const stripePromise = useMemo( () => {
		return api.loadStripeForExpressCheckout();
	}, [ api ] );

	const cartData = useSelect(
		( selectCart ) => ( selectCart( WC_STORE_CART ) as any )?.getCartData(),
		[]
	);

	// Apply filter to allow modifications (e.g., for trial subscriptions with $0 initial payment)
	const amount = applyFilters(
		'wcpay.express-checkout.total-amount',
		transformPrice( billing.cartTotal.value, {
			currency_minor_unit: billing.currency.minorUnit ?? 0,
		} ),
		cartData
	) as number;

	const session = useMemo(
		() =>
			new ExpressPaymentSession( {
				method: expressPaymentMethod,
				expressPaymentType,
				stripePaymentMethodType,
				amount,
				currency: billing.currency.code,
				useConfirmationTokens: shouldUseConfirmationTokens(),
				isManualCapture:
					getExpressCheckoutData( 'is_manual_capture' ) ?? false,
				cartData,
				storeName: getExpressCheckoutData( 'store_name' ) as
					| string
					| null,
				needsPayerPhone:
					getExpressCheckoutData( 'checkout' )?.needs_payer_phone ??
					false,
			} ),
		[
			expressPaymentMethod,
			expressPaymentType,
			stripePaymentMethodType,
			amount,
			billing.currency.code,
			cartData,
		]
	);

	const elementsOptions = useMemo(
		() => session.getElementsOptions(),
		[ session ]
	);

	if ( isEditor ) {
		return null;
	}

	return (
		<Elements stripe={ stripePromise } options={ elementsOptions }>
			<DynamicButton
				session={ session }
				expressPaymentMethod={ expressPaymentMethod }
				gatewayId={ gatewayId }
				validate={ props.validate }
				onSubmit={ props.onSubmit }
				eventRegistration={ props.eventRegistration }
				emitResponse={ props.emitResponse }
			/>
		</Elements>
	);
};

export default DynamicButtonContainer;
