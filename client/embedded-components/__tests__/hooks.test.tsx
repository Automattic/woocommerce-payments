/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { createKycAccountSession } from '../hooks';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

describe( 'embedded component hooks', () => {
	beforeEach( () => {
		mockApiFetch.mockResolvedValue( {
			clientSecret: 'test-secret',
			publishableKey: 'test-key',
			locale: 'en_US',
		} );
		window.history.pushState(
			{},
			'',
			'/?capabilities=card_payments,transfers'
		);
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'omits company structure when it is unset', async () => {
		await createKycAccountSession( {
			country: 'JP',
			business_type: 'company',
			'company.structure': undefined,
			mcc: 'software_services',
		} );

		expect( mockApiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/wc/v3/payments/onboarding/kyc/session',
				method: 'POST',
				data: {
					self_assessment: {
						country: 'JP',
						business_type: 'company',
						mcc: 'software_services',
					},
					capabilities: 'card_payments,transfers',
					mode: 'live',
				},
			} )
		);
	} );
} );
