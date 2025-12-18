/**
 * External dependencies
 */
import React, { useEffect, useMemo, useRef } from 'react';
import {
	Elements,
	ExpressCheckoutElement,
	useElements,
} from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import {
	displayLoginConfirmation,
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutData,
	normalizeLineItems,
} from '../../utils';
import { transformPrice } from '../../transformers/wc-to-stripe';
import '../express-checkout-element.scss';
import {
	onCancelHandler,
	onClickHandler,
	onReadyHandler,
} from 'wcpay/express-checkout/event-handlers';
import * as stripeJs from '@stripe/stripe-js';
import {
	useFingerprint,
	usePaymentCompleteHandler,
	usePaymentFailHandler,
} from 'wcpay/checkout/blocks/hooks';
import { validateElements } from 'wcpay/checkout/classic/payment-processing';
import { PAYMENT_METHOD_ERROR, WC_STORE_CART } from 'wcpay/checkout/constants';
import { useSelect } from '@wordpress/data';
import { useCallback } from '@wordpress/element';
import { StripeElements } from '@stripe/stripe-js';

// @ts-expect-error this is a TODO
const getBillingDetails = ( billingData ) => {
	return {
		name: `${ billingData.first_name } ${ billingData.last_name }`.trim(),
		email: billingData.email,
		phone: billingData.phone,
		address: {
			city: billingData.city,
			country: billingData.country,

			line1: billingData.address_1,
			line2: billingData.address_2,
			postal_code: billingData.postcode,
			state: billingData.state,
		},
	};
};

const getPaymentMethodsOverride = ( enabledPaymentMethod: string ) => {
	const allDisabled = {
		applePay: 'never',
		googlePay: 'never',
		amazonPay: 'never',
		link: 'never',
		paypal: 'never',
		klarna: 'never',
	};

	const enabledParam = [ 'applePay', 'googlePay' ].includes(
		enabledPaymentMethod
	)
		? 'always'
		: 'auto';

	return {
		paymentMethods: {
			...allDisabled,
			[ enabledPaymentMethod ]: enabledParam,
		},
	};
};

// @ts-expect-error don't care right now
const GetElementsRef = ( { setElements } ) => {
	const elements = useElements();
	useEffect( () => {
		setElements( elements );
	}, [ elements, setElements ] );
	return null;
};

interface ExpressCheckoutProps {
	expressPaymentMethod: string;
	api: any;
	isPreview?: boolean;
	billing: any;
	buttonAttributes: any;
	validate: () => Promise< { hasError: boolean } >;
	onSubmit: () => void;
}

const ExpressCheckoutContainer = ( {
	expressPaymentMethod,
	api,
	isPreview,
	billing,
	buttonAttributes,
	validate,
	onSubmit,
	// @ts-expect-error this is a TODO
	activePaymentMethod,
	// @ts-expect-error this is a TODO
	emitResponse,
	// @ts-expect-error this is a TODO
	eventRegistration: { onPaymentSetup, onCheckoutSuccess, onCheckoutFail },
}: ExpressCheckoutProps ) => {
	const stripePromise = useMemo( () => {
		return api.loadStripeForExpressCheckout();
	}, [ api ] );

	const customerData = useSelect( ( select ) =>
		select( WC_STORE_CART ).getCustomerData()
	);

	const expressPaymentType = useRef( '' );
	const elementsRef = useRef< StripeElements >( null );

	// @ts-expect-error this is a TODO
	const billingData = customerData.billingAddress || customerData.billingData;

	const [ fingerprint, fingerprintErrorMessage ] = useFingerprint();

	useEffect(
		() =>
			onPaymentSetup( () => {
				async function handlePaymentProcessing() {
					if (
						'woocommerce_payments_google_pay' !==
						activePaymentMethod
					) {
						return;
					}

					if ( fingerprintErrorMessage ) {
						return {
							type: 'error',
							message: fingerprintErrorMessage,
						};
					}

					try {
						await validateElements( elementsRef.current );
					} catch ( e ) {
						return {
							type: 'error',
							// @ts-expect-error not unknown
							message: e.message,
						};
					}

					const stripeForUPE = await api.getStripeForUPE(
						'google_pay'
					);

					const {
						paymentMethod,
						error,
					} = await stripeForUPE.createPaymentMethod( {
						elements: elementsRef.current,
						params: {
							billing_details: getBillingDetails( billingData ),
						},
					} );

					if ( error ) {
						return {
							// We return a `success` type even when there's an error since we want the checkout request to go
							// through, so we can have this attempt recorded in an Order.
							type: 'success',
							meta: {
								paymentMethodData: {
									payment_method: 'card',
									'wcpay-payment-method': PAYMENT_METHOD_ERROR,
									'wcpay-payment-method-error-code':
										error.code,
									'wcpay-payment-method-error-decline-code':
										error.decline_code,
									'wcpay-payment-method-error-message':
										error.message,
									'wcpay-payment-method-error-type':
										error.type,
									'wcpay-fraud-prevention-token':
										window.wcpayFraudPreventionToken ?? '',
									'wcpay-fingerprint': fingerprint,
								},
							},
						};
					}

					return {
						type: 'success',
						meta: {
							paymentMethodData: {
								payment_method: 'card',
								'wcpay-payment-method': paymentMethod.id,
								'wcpay-fraud-prevention-token':
									window.wcpayFraudPreventionToken ?? '',
								'wcpay-fingerprint': fingerprint,
							},
						},
					};
				}
				return handlePaymentProcessing();
			} ),
		[
			activePaymentMethod,
			api,
			fingerprint,
			fingerprintErrorMessage,
			onPaymentSetup,
			billingData,
		]
	);

	usePaymentCompleteHandler( api, onCheckoutSuccess, emitResponse, false );
	usePaymentFailHandler( onCheckoutFail, emitResponse );

	const handleClick = async (
		event: stripeJs.StripeExpressCheckoutElementClickEvent
	) => {
		// If login is required for checkout, display the redirect confirmation dialog.
		if ( getExpressCheckoutData( 'login_confirmation' ) ) {
			displayLoginConfirmation( event.expressPaymentType );
			return;
		}

		const validationResult = await validate();

		if ( validationResult.hasError ) {
			return; // WooCommerce automatically displays validation errors
		}

		const lineItems = normalizeLineItems( billing.cartTotalItems ).map(
			( item ) => ( {
				...item,
				// ensuring that the amount is transformed to the correct format expected by Stripe.
				amount: transformPrice( item.amount, {
					currency_minor_unit: billing.currency.minorUnit ?? 0,
				} ),
			} )
		);
		const lineItemsTotals = lineItems.reduce(
			( acc, lineItem ) => acc + lineItem.amount,
			0
		);

		const cartTotals = transformPrice( billing.cartTotal.value, {
			currency_minor_unit: billing.currency.minorUnit ?? 0,
		} );

		onClickHandler( event );

		event.resolve( {
			// if the transformed cart total is less than the total of `lineItems`, Stripe throws an error
			// it can sometimes happen that the total is _slightly_ less, due to rounding errors on individual items/taxes/shipping
			// (or with the `woocommerce_tax_round_at_subtotal` setting).
			// if that happens, let's just not return any of the line items.
			// This way, just the total amount will be displayed to the customer.
			lineItems: cartTotals < lineItemsTotals ? [] : lineItems,
		} );
	};

	const handleConfirm = (
		event: stripeJs.StripeExpressCheckoutElementConfirmEvent
	) => {
		expressPaymentType.current =
			'woocommerce_payments_' + event.expressPaymentType;
		onSubmit();
	};

	const handleElementsRef = useCallback( ( el: StripeElements ) => {
		// @ts-expect-error TODO not sure
		elementsRef.current = el;
	}, [] );

	return (
		<div style={ { minHeight: '40px' } }>
			<Elements
				stripe={ stripePromise }
				options={ {
					mode: 'payment',
					paymentMethodCreation: 'manual',
					// ensuring that the total amount is transformed to the correct format.
					amount: ! isPreview
						? transformPrice( billing.cartTotal.value, {
								currency_minor_unit:
									billing.currency.minorUnit ?? 0,
						  } )
						: 10,
					currency: ! isPreview
						? billing.currency.code.toLowerCase()
						: 'usd',
					appearance: getExpressCheckoutButtonAppearance(
						buttonAttributes
					),
					// @ts-expect-error due to the strict typing on Stripe's side.
					locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
					...getPaymentMethodsOverride( expressPaymentMethod ),
				} }
			>
				<GetElementsRef setElements={ handleElementsRef } />
				<ExpressCheckoutElement
					onClick={ handleClick }
					onConfirm={ handleConfirm }
					onReady={ onReadyHandler }
					onCancel={ onCancelHandler }
				/>
			</Elements>
		</div>
	);
};

export default ExpressCheckoutContainer;
