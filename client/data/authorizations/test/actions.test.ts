/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { submitCaptureAuthorization, updateAuthorization } from '../actions';

describe( 'submitCaptureAuthorization', () => {
	const mockAuthorization = {
		authorization_id: '42',
		authorized_on: 'Aug 31, 2022 / 3:41AM',
		capture_by: 'capture_by',
		order: {
			number: 52,
			customer_url: 'https://example.com',
			url: 'https://example.com',
		},
		risk_level: 'risk_level',
		amount: 'amount',
		customer_email: 'customer_email',
		customer_country: 'customer_country',
		customer_name: 'customer_name',
		payment_intent_id: 'pi_4242',
	};

	test( 'should capture authorization and show success notice.', () => {
		const generator = submitCaptureAuthorization( '42', 52, 'pi_4242' );

		expect( generator.next().value ).toEqual(
			dispatch( 'wc/payments', 'startResolution', 'getAuthorization', [
				'42',
			] )
		);

		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/orders/${ 52 }/capture_authorization`,
				method: 'post',
				data: {
					payment_intent_id: 'pi_4242',
				},
			} )
		);

		expect( generator.next( mockAuthorization ).value ).toEqual(
			updateAuthorization( [ mockAuthorization ] )
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
				'core/notices',
				'createSuccessNotice',
				'You have captured the payment.'
			)
		);

		expect( generator.next().value ).toEqual(
			dispatch( 'wc/payments', 'finishResolution', 'getAuthorization', [
				'42',
			] )
		);

		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'should show notice on error', () => {
		const generator = submitCaptureAuthorization( '42', 52, 'pi_4242' );
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
