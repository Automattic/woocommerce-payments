/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import Info from '../';
import { Dispute, Evidence } from 'wcpay/types/disputes';
import { Charge } from 'wcpay/types/charges';

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
	};
};

describe( 'Dispute info', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
		};
	} );

	test( 'renders correctly', () => {
		const dispute: any = {
			status: 'needs_response',
			id: '',
			evidence_details: {
				has_evidence: true,
				due_by: 1573199200,
			},
			metadata: {},
			productType: '',
			order: {
				number: '1',
				url: 'http://test.local/order/1',
			} as unknown,
			evidence: {} as Evidence,
			fileSize: {},
			reason: 'fraudulent',
			charge: {
				id: 'ch_mock',
				billing_details: {
					name: 'Mock customer',
				},
			} as Charge,
			amount: 1000,
			currency: 'usd',
			created: 1572590800,
			balance_transactions: [],
		};

		const { container: info } = render(
			<Info dispute={ dispute as Dispute } isLoading={ false } />
		);
		expect( info ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		const { container: info } = render(
			<Info isLoading={ true } dispute={ {} as Dispute } />
		);
		expect( info ).toMatchSnapshot();
	} );
} );
