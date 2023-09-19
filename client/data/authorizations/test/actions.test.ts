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
					'There has been an error capturing the payment for order #42. Please try again later.'
				)
			);
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
					'There has been an error canceling the payment for order #42. Please try again later.'
				)
			);
		} );
	} );
} );
