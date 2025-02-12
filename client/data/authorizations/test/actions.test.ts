/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';
import { Authorization } from 'wcpay/types/authorizations';

/**
 * Internal dependencies
 */
import {
	submitCancelAuthorization,
	submitCaptureAuthorization,
	updateAuthorization,
} from '../actions';
import authorizationsFixture from './authorizations.fixture.json';
import { STORE_NAME } from 'wcpay/data/constants';

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		connect: {
			country: string;
		};
		currencyData: {
			[ key: string ]: {
				code: string;
				symbol: string;
				symbolPosition: string;
				thousandSeparator: string;
				decimalSeparator: string;
				precision: number;
			};
		};
	};
};

describe( 'Authorizations actions', () => {
	describe( 'submitCaptureAuthorization', () => {
		const {
			order_id: mockOrderId,
			payment_intent_id: mockPaymentIntentId,
		} = authorizationsFixture[ 0 ];

		test( 'should capture authorization and show success notice.', () => {
			const generator = submitCaptureAuthorization(
				mockPaymentIntentId,
				mockOrderId
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'startResolution',
					'getAuthorization',
					[ mockPaymentIntentId ]
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'setIsRequestingAuthorization',
					true
				)
			);

			expect( generator.next().value ).toEqual(
				apiFetch( {
					path: `/wc/v3/payments/orders/${ mockOrderId }/capture_authorization`,
					method: 'post',
					data: {
						payment_intent_id: mockPaymentIntentId,
					},
				} )
			);

			expect(
				generator.next( {
					id: mockPaymentIntentId,
					status: 'succeeded',
				} ).value
			).toEqual(
				updateAuthorization( {
					payment_intent_id: mockPaymentIntentId,
					captured: true,
				} as Authorization )
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'invalidateResolutionForStoreSelector',
					'getAuthorizations'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'invalidateResolutionForStoreSelector',
					'getAuthorizationsSummary'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'invalidateResolutionForStoreSelector',
					'getFraudOutcomeTransactions'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'invalidateResolutionForStoreSelector',
					'getFraudOutcomeTransactionsSummary'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'invalidateResolutionForStoreSelector',
					'getTimeline'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'invalidateResolutionForStoreSelector',
					'getPaymentIntent'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'invalidateResolutionForStoreSelector',
					'getTransactions'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'core/notices',
					'createSuccessNotice',
					'Payment for order #254 captured successfully.'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'finishResolution',
					'getAuthorization',
					[ mockPaymentIntentId ]
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'setIsRequestingAuthorization',
					false
				)
			);

			expect( generator.next().done ).toStrictEqual( true );
		} );

		test( 'should show notice on error', () => {
			const generator = submitCaptureAuthorization( 'pi_4242', 42 );
			generator.next();

			expect( generator.throw( { code: 'error' } ).value ).toEqual(
				controls.dispatch(
					'core/notices',
					'createErrorNotice',
					'There has been an error capturing the payment for order #42. Unable to process the payment. Please try again later.'
				)
			);
		} );

		describe( 'error handling', () => {
			beforeEach( () => {
				global.wcpaySettings = {
					zeroDecimalCurrencies: [],
					connect: {
						country: 'US',
					},
					currencyData: {
						USD: {
							code: 'USD',
							symbol: '$',
							symbolPosition: 'left',
							thousandSeparator: ',',
							decimalSeparator: '.',
							precision: 2,
						},
					},
				};
			} );

			it( 'should create error notice with API error message', () => {
				const generator = submitCaptureAuthorization( 'pi_123', 123 );

				// Mock the start of the capture process
				expect( generator.next().value ).toEqual(
					controls.dispatch(
						STORE_NAME,
						'startResolution',
						'getAuthorization',
						[ 'pi_123' ]
					)
				);

				expect( generator.next().value ).toEqual(
					controls.dispatch(
						STORE_NAME,
						'setIsRequestingAuthorization',
						true
					)
				);

				// Mock API error response
				const apiError = {
					code: 'wcpay_refunded_order_uncapturable',
					message:
						'Payment cannot be captured for partially or fully refunded orders.',
					data: { status: 400 },
				};

				// Simulate API error
				expect( generator.throw( apiError ).value ).toEqual(
					controls.dispatch(
						'core/notices',
						'createErrorNotice',
						'There has been an error capturing the payment for order #123. Payment cannot be processed for partially or fully refunded orders.'
					)
				);

				// Verify cleanup in finally block
				expect( generator.next().value ).toEqual(
					controls.dispatch(
						STORE_NAME,
						'finishResolution',
						'getAuthorization',
						[ 'pi_123' ]
					)
				);

				expect( generator.next().value ).toEqual(
					controls.dispatch(
						STORE_NAME,
						'setIsRequestingAuthorization',
						false
					)
				);
			} );

			it( 'should create error notice with amount too small error details', () => {
				const generator = submitCaptureAuthorization( 'pi_123', 123 );

				// Skip initial dispatch calls
				generator.next();
				generator.next();

				// Mock API error for amount too small
				const apiError = {
					code: 'wcpay_capture_error_amount_too_small',
					data: {
						status: 400,
						extra_details: {
							minimum_amount: 50,
							minimum_amount_currency: 'USD',
						},
					},
				};

				expect( generator.throw( apiError ).value ).toEqual(
					controls.dispatch(
						'core/notices',
						'createErrorNotice',
						'There has been an error capturing the payment for order #123. The minimum amount that can be processed is $0.50 USD.'
					)
				);
			} );

			it( 'should create error notice with amount too small when amount details are missing', () => {
				const generator = submitCaptureAuthorization( 'pi_123', 123 );

				// Skip initial dispatch calls
				generator.next();
				generator.next();

				// Mock API error for amount too small
				const apiError = {
					code: 'wcpay_capture_error_amount_too_small',
					data: {
						status: 400,
					},
				};

				expect( generator.throw( apiError ).value ).toEqual(
					controls.dispatch(
						'core/notices',
						'createErrorNotice',
						'There has been an error capturing the payment for order #123. The payment amount is too small to be processed.'
					)
				);
			} );

			it( 'should create error notice with fallback message when API error has no message', () => {
				const generator = submitCaptureAuthorization( 'pi_123', 123 );

				// Skip initial dispatch calls
				generator.next();
				generator.next();

				// Mock API error without message
				const apiError = {
					code: 'unknown_error',
					data: { status: 500 },
				};

				expect( generator.throw( apiError ).value ).toEqual(
					controls.dispatch(
						'core/notices',
						'createErrorNotice',
						'There has been an error capturing the payment for order #123. Unable to process the payment. Please try again later.'
					)
				);
			} );

			it( 'should show default error notice for unknown error code', () => {
				const generator = submitCaptureAuthorization(
					'pi_unknown',
					999
				);

				// Start the generator to the point where it would throw an error
				generator.next();
				generator.next();

				// Mock an API error with an unknown error code
				const apiError = {
					code: 'unknown_error_code',
					data: { status: 500 },
				};

				// Expect the default error message to be dispatched
				expect( generator.throw( apiError ).value ).toEqual(
					controls.dispatch(
						'core/notices',
						'createErrorNotice',
						'There has been an error capturing the payment for order #999. Unable to process the payment. Please try again later.'
					)
				);
			} );
		} );
	} );

	describe( 'submitCancelAuthorization', () => {
		const {
			order_id: mockOrderId,
			payment_intent_id: mockPaymentIntentId,
		} = authorizationsFixture[ 0 ];

		test( 'should capture authorization and show success notice.', () => {
			const generator = submitCancelAuthorization(
				mockPaymentIntentId,
				mockOrderId
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'startResolution',
					'getAuthorization',
					[ mockPaymentIntentId ]
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'setIsRequestingAuthorization',
					true
				)
			);

			expect( generator.next().value ).toEqual(
				apiFetch( {
					path: `/wc/v3/payments/orders/${ mockOrderId }/cancel_authorization`,
					method: 'post',
					data: {
						payment_intent_id: mockPaymentIntentId,
					},
				} )
			);

			expect(
				generator.next( {
					id: mockPaymentIntentId,
					status: 'succeeded',
				} ).value
			).toEqual(
				updateAuthorization( {
					payment_intent_id: mockPaymentIntentId,
					captured: true,
				} as Authorization )
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'invalidateResolutionForStoreSelector',
					'getAuthorizations'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'invalidateResolutionForStoreSelector',
					'getAuthorizationsSummary'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'invalidateResolutionForStoreSelector',
					'getFraudOutcomeTransactions'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'invalidateResolutionForStoreSelector',
					'getFraudOutcomeTransactionsSummary'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'invalidateResolutionForStoreSelector',
					'getTimeline'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'invalidateResolutionForStoreSelector',
					'getPaymentIntent'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'core/notices',
					'createSuccessNotice',
					'Payment for order #254 canceled successfully.'
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'finishResolution',
					'getAuthorization',
					[ mockPaymentIntentId ]
				)
			);

			expect( generator.next().value ).toEqual(
				controls.dispatch(
					'wc/payments',
					'setIsRequestingAuthorization',
					false
				)
			);

			expect( generator.next().done ).toStrictEqual( true );
		} );

		test( 'should show notice on error', () => {
			const generator = submitCancelAuthorization( 'pi_4242', 42 );
			generator.next();

			expect( generator.throw( { code: 'error' } ).value ).toEqual(
				controls.dispatch(
					'core/notices',
					'createErrorNotice',
					'There has been an error canceling the payment for order #42. Unable to process the payment. Please try again later.'
				)
			);
		} );

		describe( 'error handling', () => {
			it( 'should create error notice with API error message', () => {
				const generator = submitCancelAuthorization( 'pi_123', 123 );

				// Skip initial dispatch calls
				generator.next();
				generator.next();

				// Mock API error response
				const apiError = {
					code: 'wcpay_payment_uncapturable',
					message: 'The payment cannot be canceled at this time.',
					data: { status: 400 },
				};

				expect( generator.throw( apiError ).value ).toEqual(
					controls.dispatch(
						'core/notices',
						'createErrorNotice',
						'There has been an error canceling the payment for order #123. This payment cannot be processed in its current state.'
					)
				);
			} );

			it( 'should create error notice with fallback message when API error has no message', () => {
				const generator = submitCancelAuthorization( 'pi_123', 123 );

				// Skip initial dispatch calls
				generator.next();
				generator.next();

				// Mock API error without message
				const apiError = {
					code: 'unknown_error',
					data: { status: 500 },
				};

				expect( generator.throw( apiError ).value ).toEqual(
					controls.dispatch(
						'core/notices',
						'createErrorNotice',
						'There has been an error canceling the payment for order #123. Unable to process the payment. Please try again later.'
					)
				);
			} );
		} );
	} );
} );
