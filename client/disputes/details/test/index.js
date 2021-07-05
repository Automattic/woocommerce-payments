/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DisputeDetails from '../';
import { useDispute } from 'data';

jest.mock( 'data', () => ( {
	useDispute: jest.fn(),
} ) );

describe( 'Dispute details screen', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
		};
	} );

	const reasons = [
		'bank_cannot_process',
		'check_returned',
		'credit_not_processed',
		'customer_initiated',
		'debit_not_authorized',
		'duplicate',
		'fraudulent',
		'general',
		'incorrect_account_details',
		'insufficient_funds',
		'product_not_received',
		'product_unacceptable',
		'subscription_canceled',
		'unrecognized',
	];

	test.each( reasons )( 'renders correctly for %s dispute', ( reason ) => {
		const dispute = {
			id: 'dp_asdfghjkl',
			amount: 1000,
			currency: 'usd',
			created: 1572590800,
			evidence_details: {
				due_by: 1573199200,
			},
			reason,
			status: 'needs_response',
			order: {
				number: '1',
				url: 'http://test.local/order/1',
			},
		};

		useDispute.mockReturnValue( { dispute, isLoading: false } );

		const { container } = render(
			<DisputeDetails query={ { id: 'dp_asdfghjkl' } } />
		);
		expect( container ).toMatchSnapshot();
	} );
} );
