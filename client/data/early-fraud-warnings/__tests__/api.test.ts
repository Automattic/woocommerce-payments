/** @format */

/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { setEarlyFraudWarningDismissed } from '../api';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

describe( 'setEarlyFraudWarningDismissed', () => {
	it( 'posts the dismissal for the order', async () => {
		( apiFetch as unknown as jest.Mock ).mockResolvedValue( {
			dismissed: true,
		} );

		const response = await setEarlyFraudWarningDismissed( 42, true );

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wc/v3/payments/early_fraud_warnings/42/dismiss',
			method: 'POST',
			data: { dismissed: true },
		} );
		expect( response ).toEqual( { dismissed: true } );
	} );
} );
