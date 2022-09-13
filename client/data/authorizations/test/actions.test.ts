/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { Authorization } from 'wcpay/types/authorizations';

/**
 * Internal dependencies
 */
import { submitCaptureAuthorization, updateAuthorization } from '../actions';
import authorizationsFixture from './authorizations.fixture.json';

describe( 'submitCaptureAuthorization', () => {
	const {
		order: mockOrder,
		payment_intent_id: mockPaymentIntentId,
	} = authorizationsFixture[ 0 ];

	test( 'should capture authorization and show success notice.', () => {
		const generator = submitCaptureAuthorization(
			mockPaymentIntentId,
			mockOrder.number
		);

		expect( generator.next().value ).toEqual(
			dispatch( 'wc/payments', 'startResolution', 'getAuthorization', [
				mockPaymentIntentId,
			] )
		);

		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/orders/${ mockOrder.number }/capture_authorization`,
				method: 'post',
				data: {
					payment_intent_id: mockPaymentIntentId,
				},
			} )
		);

		expect( generator.next( authorizationsFixture[ 0 ] ).value ).toEqual(
			updateAuthorization( {
				payment_intent_id: mockPaymentIntentId,
				captured: true,
			} as Authorization )
		);

		expect( generator.next().value ).toEqual(
			dispatch(
				'wc/payments',
				'invalidateResolutionForStoreSelector',
				'getAuthorizations'
			)
		);

		expect( generator.next().value ).toEqual(
			dispatch(
				'wc/payments',
				'invalidateResolutionForStoreSelector',
				'getAuthorizationsSummary'
			)
		);

		expect( generator.next().value ).toEqual(
			dispatch(
				'wc/payments',
				'invalidateResolutionForStoreSelector',
				'getTimeline'
			)
		);

		expect( generator.next().value ).toEqual(
			dispatch(
				'core/notices',
				'createSuccessNotice',
				'You have captured the payment.'
			)
		);

		expect( generator.next().value ).toEqual(
			dispatch( 'wc/payments', 'finishResolution', 'getAuthorization', [
				mockPaymentIntentId,
			] )
		);

		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'should show notice on error', () => {
		const generator = submitCaptureAuthorization( 'pi_4242', 52 );
		generator.next();

		expect( generator.throw( { code: 'error' } ).value ).toEqual(
			dispatch(
				'core/notices',
				'createErrorNotice',
				'There has been an error capturing the payment. Please try again later.'
			)
		);
	} );
} );
