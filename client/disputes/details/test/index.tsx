/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import * as React from 'react';

/**
 * Internal dependencies
 */
import DisputeDetails from '../';
import { useDispute } from 'data/index';
import { DisputeReason, DisputeStatus } from 'wcpay/types/disputes';

jest.mock( 'data/index', () => ( {
	useDispute: jest.fn(),
} ) );

const mockUseDispute = useDispute as jest.MockedFunction< typeof useDispute >;

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
	};
};

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

	const statuses = [
		'warning_needs_response',
		'warning_under_review',
		'warning_closed',
		'needs_response',
		'under_review',
		'charge_refunded',
		'won',
		'lost',
	];

	test.each( reasons )( 'renders correctly for %s dispute', ( reason ) => {
		const dispute: any = {
			id: 'dp_asdfghjkl',
			amount: 1000,
			currency: 'usd',
			created: 1572590800,
			evidence_details: {
				due_by: 1573199200,
			},
			reason: reason as DisputeReason,
			status: 'needs_response' as DisputeStatus,
			order: {
				number: '1',
				url: 'http://test.local/order/1',
			},
		};

		mockUseDispute.mockReturnValue( {
			dispute: dispute as any,
			isLoading: false,
			doAccept: jest.fn(),
		} );

		const { container } = render(
			<DisputeDetails query={ { id: 'dp_asdfghjkl' } } />
		);
		expect( container ).toMatchSnapshot();
	} );

	test.each( statuses )(
		'renders correctly for %s dispute status',
		( status ) => {
			const dispute = {
				id: 'dp_asdfghjkl',
				amount: 1000,
				currency: 'usd',
				created: 1572590800,
				evidence_details: {
					due_by: 1573199200,
				},
				reason: 'fraudulent' as DisputeReason,
				status: status as DisputeStatus,
				order: {
					number: '1',
					url: 'http://test.local/order/1',
				},
			};

			mockUseDispute.mockReturnValue( {
				dispute: dispute as any,
				isLoading: false,
				doAccept: jest.fn(),
			} );

			const { container } = render(
				<DisputeDetails query={ { id: 'dp_asdfghjkl' } } />
			);
			expect( container ).toMatchSnapshot();
		}
	);
} );
