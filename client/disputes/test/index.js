/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DisputesList from '../';
import { useDisputes } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useDisputes: jest.fn(),
} ) );

describe( 'Disputes list', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
		};
	} );

	test( 'renders correctly', () => {
		useDisputes.mockReturnValue( {
			isLoading: false,
			disputes: [
				{
					id: 'dp_asdfghjkl',
					amount: 1000,
					currency: 'usd',
					created: 1572590800,
					evidence_details: {
						due_by: 1573199200,
					},
					reason: 'fraudulent',
					status: 'needs_response',
					charge: {
						id: 'ch_mock',
						payment_method_details: {
							card: {
								brand: 'visa',
							},
						},
						billing_details: {
							name: 'Mock customer',
							email: 'mock@customer.net',
							address: {
								country: 'US',
							},
						},
					},
					order: {
						number: '1',
						url: 'http://test.local/order/1',
					},
				},
				{
					id: 'dp_zxcvbnm',
					amount: 1050,
					currency: 'usd',
					created: 1572480800,
					evidence_details: {
						due_by: 1573099200,
					},
					reason: 'general',
					status: 'under_review',
					// dispute without order or charge information
				},
			],
		} );

		const { container: list } = render( <DisputesList /> );
		expect( list ).toMatchSnapshot();
	} );
} );
