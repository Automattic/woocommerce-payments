/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { dismissDisputeReadinessCard } from '../actions';

const readinessPayload = {
	overview: {
		enabled: true,
		isDismissed: true,
	},
};
const errorResponse = { code: 'error' };

describe( 'dismissDisputeReadinessCard action', () => {
	let generator = null;

	beforeEach( () => {
		generator = dismissDisputeReadinessCard();
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: '/wc/v3/payments/dispute-readiness/dismiss',
				method: 'POST',
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'updates dispute readiness on success', () => {
		expect( generator.next( readinessPayload ).value ).toEqual(
			controls.dispatch(
				'wc/payments',
				'updateDisputeReadiness',
				readinessPayload
			)
		);
	} );

	test( 'updates dispute readiness error on failure', () => {
		expect( generator.throw( errorResponse ).value ).toEqual(
			controls.dispatch(
				'wc/payments',
				'updateErrorForDisputeReadiness',
				errorResponse
			)
		);
	} );
} );
