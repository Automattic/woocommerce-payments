/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { DisputeDetails } from '../';

describe( 'Dispute details screen', () => {
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
			created: 1572590800,
			// eslint-disable-next-line camelcase
			evidence_details: {
				// eslint-disable-next-line camelcase
				due_by: 1573199200,
			},
			reason,
			status: 'needs_response',
			order: {
				number: '1',
				url: 'http://test.local/order/1',
			},
		};

		const { container } = render(
			<DisputeDetails
				dispute={ dispute }
				showPlaceholder={ false }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
