/**
 * External dependencies
 */
import { PaymentElement, useElements } from '@stripe/react-stripe-js';
import {
	getPaymentMethods,
	// eslint-disable-next-line import/no-unresolved
} from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from 'react';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import { usePaymentCompleteHandler, usePaymentFailHandler } from './hooks';
import { useCustomerData, getStripeElementOptions } from './utils';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { validateElements } from 'wcpay/checkout/utils/validate-elements';
import { PAYMENT_METHOD_ERROR } from 'wcpay/checkout/constants';
import { CardSkeleton } from './components/card-skeleton';
import { ApmSkeleton } from './components/apm-skeleton';
import SkeletonContext from './components/skeleton-context';

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
			// Trim to avoid Stripe AVS mismatches on leading/trailing whitespace.
			postal_code: billingData.postcode?.trim(),
			state: billingData.state,
		},
	};
};

const getFraudPreventionToken = () => {
	return window.wcpayFraudPreventionToken ?? '';
};

const noop = () => null;

const PaymentProcessor = ( {
	api,
	activePaymentMethod,
	testingInstructions,
	eventRegistration: { onPaymentSetup, onCheckoutSuccess, onCheckoutFail },
	emitResponse,
	components: { Skeleton: CoreSkeleton } = {},
	paymentMethodId,
	upeMethods,
	errorMessage,
	shouldSavePayment,
	fingerprint,
	onLoadError = noop,
	theme,
} ) => {
	const elements = useElements();
	const hasLoadErrorRef = useRef( false );

	const [ isStripeReady, setIsStripeReady ] = useState( false );
	const [ showSkeleton, setShowSkeleton ] = useState( true );
	const [ cardRowCount, setCardRowCount ] = useState( 2 );
	const isCardMethod = paymentMethodId === 'card';
	const wrapperRef = useRef( null );

	// Dynamically adjust skeleton layout and min-height based on wrapper
	// width to match Stripe's responsive card field layout (1/2/3-row).
	useEffect( () => {
		if ( ! isCardMethod || ! wrapperRef.current ) {
			return;
		}

		const el = wrapperRef.current;
		const observer = new ResizeObserver( ( entries ) => {
			const width = entries[ 0 ].contentRect.width;
			// Stripe renders card fields in:
			// - 1 row above ~660px
			// - 2 rows between ~415px and ~660px
			// - 3 rows below ~415px
			let rows;
			let minHeight;
			if ( width >= 660 ) {
				rows = 1;
				minHeight = '70px';
			} else if ( width >= 415 ) {
				rows = 2;
				minHeight = '145px';
			} else {
				rows = 3;
				minHeight = '220px';
			}
			setCardRowCount( rows );
			el.style.minHeight = minHeight;
		} );

		observer.observe( el );
		return () => {
			observer.disconnect();
			el.style.minHeight = '';
		};
	}, [ isCardMethod ] );

	// Remove skeleton from DOM after fade-out transition completes.
	const handleSkeletonTransitionEnd = useCallback( () => {
		setShowSkeleton( false );
	}, [] );

	const handleStripeReady = useCallback( () => {
		setIsStripeReady( true );
	}, [] );

	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const isTestMode = getUPEConfig( 'testMode' );
	const gatewayId = upeMethods[ paymentMethodId ].gatewayId;
	const gatewayConfig = getPaymentMethods()[ gatewayId ];
	const billingData = useCustomerData();

	useEffect(
		() =>
			onPaymentSetup( () => {
				async function handlePaymentProcessing() {
					if ( gatewayId !== activePaymentMethod ) {
						return;
					}

					if ( hasLoadErrorRef.current ) {
						return {
							type: 'error',
							message: __(
								'Invalid or missing payment details. Please ensure the provided payment method is correctly entered.',
								'woocommerce-payments'
							),
						};
					}

					if ( errorMessage ) {
						return {
							type: 'error',
							message: errorMessage,
						};
					}

					if (
						gatewayConfig.supports.showSaveOption &&
						shouldSavePayment &&
						! paymentMethodsConfig[ paymentMethodId ].isReusable
					) {
						return {
							type: 'error',
							message: __(
								'This payment method cannot be saved for future use.',
								'woocommerce-payments'
							),
						};
					}

					try {
						await validateElements( elements );
					} catch ( e ) {
						return {
							type: 'error',
							message: e.message,
						};
					}

					const stripeForUPE = await api.getStripeForUPE(
						paymentMethodId
					);

					const result = await stripeForUPE.createPaymentMethod( {
						elements,
						params: {
							billing_details: getBillingDetails( billingData ),
						},
					} );

					if ( result.error ) {
						return {
							// We return a `success` type even when there's an error since we want the checkout request to go
							// through, so we can have this attempt recorded in an Order.
							type: 'success',
							meta: {
								paymentMethodData: {
									payment_method: gatewayId,
									'wcpay-payment-method': PAYMENT_METHOD_ERROR,
									'wcpay-payment-method-error-code':
										result.error.code,
									'wcpay-payment-method-error-decline-code':
										result.error.decline_code,
									'wcpay-payment-method-error-message':
										result.error.message,
									'wcpay-payment-method-error-type':
										result.error.type,
									'wcpay-fraud-prevention-token': getFraudPreventionToken(),
									'wcpay-fingerprint': fingerprint,
								},
							},
						};
					}

					return {
						type: 'success',
						meta: {
							paymentMethodData: {
								payment_method: gatewayId,
								'wcpay-payment-method': result.paymentMethod.id,
								'wcpay-fraud-prevention-token': getFraudPreventionToken(),
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
			elements,
			fingerprint,
			gatewayConfig,
			paymentMethodId,
			paymentMethodsConfig,
			shouldSavePayment,
			gatewayId,
			errorMessage,
			onPaymentSetup,
			billingData,
		]
	);

	usePaymentCompleteHandler(
		api,
		onCheckoutSuccess,
		emitResponse,
		shouldSavePayment
	);

	usePaymentFailHandler( onCheckoutFail, emitResponse );

	const setHasLoadError = ( event ) => {
		hasLoadErrorRef.current = true;
		onLoadError( event );
	};

	return (
		<SkeletonContext.Provider value={ CoreSkeleton }>
			{ isTestMode && (
				<p
					className={ clsx( 'content', {
						[ `theme--${ theme }` ]: theme,
					} ) }
					dangerouslySetInnerHTML={ {
						__html: testingInstructions,
					} }
				/>
			) }
			{ /* Skeleton overlay for Stripe PaymentElement loading state.
				   Positioned absolutely over the iframe mount point and fades out
				   when Stripe fires the `ready` event. */ }
			<div
				ref={ wrapperRef }
				className={ clsx(
					'wcpay-payment-element-wrapper',
					! isCardMethod && 'is-apm'
				) }
			>
				{ showSkeleton &&
					( isCardMethod ? (
						<CardSkeleton
							isHidden={ isStripeReady }
							onTransitionEnd={ handleSkeletonTransitionEnd }
							rowCount={ cardRowCount }
						/>
					) : (
						<ApmSkeleton
							isHidden={ isStripeReady }
							onTransitionEnd={ handleSkeletonTransitionEnd }
						/>
					) ) }
				<PaymentElement
					options={ getStripeElementOptions(
						shouldSavePayment,
						paymentMethodsConfig
					) }
					onReady={ handleStripeReady }
					onLoadError={ setHasLoadError }
					className="wcpay-payment-element"
				/>
			</div>
		</SkeletonContext.Provider>
	);
};

export default PaymentProcessor;
